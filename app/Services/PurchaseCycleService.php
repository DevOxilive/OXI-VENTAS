<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\GeneralPurchaseOrder;
use App\Models\GeneralPurchaseOrderItem;
use App\Models\Product;
use App\Models\PurchaseCycle;
use App\Models\PurchaseCycleBranch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class PurchaseCycleService
{
    public function currentOpenCycle(?User $user = null): PurchaseCycle
    {
        return Cache::lock('purchase-cycle:current-open', 15)->block(5, function () use ($user) {
            return DB::transaction(function () use ($user) {
            $cycle = PurchaseCycle::query()
                ->where('status', PurchaseCycle::STATUS_OPEN)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($cycle) {
                return $cycle;
            }

            $cycle = PurchaseCycle::create([
                'status' => PurchaseCycle::STATUS_OPEN,
                'created_by' => $user?->id,
                'opened_at' => now(),
            ]);

            $cycle->update([
                'folio' => sprintf('CC-%s-%04d', now()->format('Ymd'), $cycle->id),
            ]);

            $branchIds = Branch::query()
                ->where('active', true)
                ->orderBy('id')
                ->pluck('id');

            foreach ($branchIds as $branchId) {
                $pendingOrder = PurchaseOrder::query()
                    ->where('branch_id', $branchId)
                    ->where('status', PurchaseOrder::STATUS_GENERATED)
                    ->whereNull('purchase_cycle_id')
                    ->whereNull('general_purchase_order_id')
                    ->whereDate('generated_at', today())
                    ->latest('generated_at')
                    ->latest('id')
                    ->first();

                $cycle->branches()->create([
                    'branch_id' => $branchId,
                    'purchase_order_id' => $pendingOrder?->id,
                    'submitted_at' => $pendingOrder ? now() : null,
                ]);

                $pendingOrder?->update(['purchase_cycle_id' => $cycle->id]);
            }

                return $cycle;
            }, 3);
        });
    }

    public function registerOrder(PurchaseOrder $order, ?User $user = null): PurchaseCycle
    {
        return DB::transaction(function () use ($order, $user) {
            $cycle = $this->currentOpenCycle($user);
            $participation = $this->participationFor($cycle, $order->branch_id);
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);

            $participation->update([
                'purchase_order_id' => $participation->purchase_order_id ?: $order->id,
                'submitted_without_items' => false,
                'submitted_at' => $participation->submitted_at ?: now(),
            ]);

            $order->update(['purchase_cycle_id' => $cycle->id]);

            return $cycle;
        }, 3);
    }

    public function registerEmptyBranch(Branch $branch, ?User $user = null): PurchaseCycle
    {
        return DB::transaction(function () use ($branch, $user) {
            $cycle = $this->currentOpenCycle($user);
            $participation = $this->participationFor($cycle, $branch->id);

            if ($participation->purchase_order_id) {
                throw ValidationException::withMessages([
                    'cycle' => 'La sucursal ya envió una orden con productos para este ciclo.',
                ]);
            }

            $participation->update([
                'submitted_without_items' => true,
                'submitted_at' => now(),
            ]);

            return $cycle;
        });
    }

    public function consolidate(
        PurchaseCycle $cycle,
        User $user,
        array $orderIds
    ): GeneralPurchaseOrder {
        return $this->persistGeneralSelection($cycle, $user, $orderIds);
    }

    public function saveCapture(GeneralPurchaseOrder $order, array $payload): GeneralPurchaseOrder
    {
        return DB::transaction(function () use ($order, $payload) {
            $order = GeneralPurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);
            $this->assertRecordVersion($order, $payload['record_version'] ?? null);

            if ($order->status !== GeneralPurchaseOrder::STATUS_PURCHASING) {
                throw ValidationException::withMessages([
                    'order' => 'La orden general completada ya no puede modificarse.',
                ]);
            }

            $incomingItems = collect($payload['items'])->keyBy('id');
            $order->items()->with('product')->each(function (GeneralPurchaseOrderItem $item) use ($incomingItems) {
                $input = $incomingItems->get($item->id);

                if (! $input) {
                    throw ValidationException::withMessages([
                        'items' => 'Falta información de uno o más productos.',
                    ]);
                }

                $this->applyCapture($item, $input);
            });

            $this->refreshGeneralTotals($order, $payload);

            return $order->fresh(['items']);
        });
    }

    public function complete(GeneralPurchaseOrder $order, array $payload, ?User $user = null): GeneralPurchaseOrder
    {
        return DB::transaction(function () use ($order, $payload, $user) {
            $order = $this->saveCapture($order, $payload);
            $order->load('items');
            PurchaseOrder::query()
                ->where('general_purchase_order_id', $order->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $order->load('branchOrders.items');

            $invalidItem = $order->items->first(fn (GeneralPurchaseOrderItem $item) => ! $item->unavailable
                && ((float) $item->package_quantity <= 0
                    || ($item->purchase_presentation === 'Caja' && (float) $item->units_per_package <= 0)
                    || (float) $item->purchase_price <= 0)
            );

            if ($invalidItem) {
                throw ValidationException::withMessages([
                    'items' => "Completa la cantidad y el precio de compra de {$invalidItem->product_name}.",
                ]);
            }

            $this->updateProductCosts($order);
            $this->allocateCapturedItemsToBranches($order);

            foreach ($order->branchOrders as $branchOrder) {
                $branchOrder->update([
                    'actual_total' => round((float) $branchOrder->items->sum(
                        fn (PurchaseOrderItem $item) => (float) $item->actual_total
                    ), 2),
                    'status' => PurchaseOrder::STATUS_REVIEW,
                    'purchased_at' => $order->purchased_at,
                    'completed_by' => null,
                    'completed_at' => null,
                ]);
            }

            $order->update([
                'status' => GeneralPurchaseOrder::STATUS_COMPLETED,
                'completed_by' => $user?->id,
                'completed_at' => now(),
            ]);

            $cycle = PurchaseCycle::query()->lockForUpdate()->findOrFail($order->purchase_cycle_id);
            $hasOtherActiveGeneralOrders = $cycle->generalOrders()
                ->whereKeyNot($order->id)
                ->where('status', '!=', GeneralPurchaseOrder::STATUS_COMPLETED)
                ->exists();
            $hasPendingBranchOrders = $cycle->orders()
                ->where('status', PurchaseOrder::STATUS_GENERATED)
                ->whereNull('general_purchase_order_id')
                ->exists();

            if (! $hasOtherActiveGeneralOrders && ! $hasPendingBranchOrders) {
                $cycle->update([
                    'status' => PurchaseCycle::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);
            }

            return $order->fresh(['items', 'branchOrders']);
        }, 3);
    }

    private function allocateCapturedItemsToBranches(GeneralPurchaseOrder $order): void
    {
        foreach ($order->items as $generalItem) {
            $allocations = $order->branchOrders
                ->map(fn (PurchaseOrder $branchOrder) => [
                    'order' => $branchOrder,
                    'item' => $branchOrder->items->firstWhere('product_id', $generalItem->product_id),
                ])
                ->filter(fn (array $allocation) => $allocation['item'])
                ->values();

            if ($allocations->isEmpty()) {
                continue;
            }

            if ($generalItem->unavailable) {
                $allocations->each(fn (array $allocation) => $allocation['item']->update([
                    'purchased_quantity' => 0,
                    'received_quantity' => 0,
                    'actual_price' => 0,
                    'discount_amount' => 0,
                    'actual_total' => 0,
                    'status' => PurchaseOrderItem::STATUS_UNAVAILABLE,
                ]));

                continue;
            }

            $requestedTotal = max(0.01, (float) $allocations->sum(
                fn (array $allocation) => (float) $allocation['item']->requested_quantity
            ));
            $remainingQuantity = round((float) $generalItem->purchased_quantity, 2);
            $unitCost = round((float) $generalItem->net_unit_cost, 4);
            $lastIndex = $allocations->count() - 1;

            foreach ($allocations as $index => $allocation) {
                /** @var PurchaseOrderItem $branchItem */
                $branchItem = $allocation['item'];
                $ratio = (float) $branchItem->requested_quantity / $requestedTotal;
                $isLast = $index === $lastIndex;

                $allocatedQuantity = $isLast
                    ? $remainingQuantity
                    : round((float) $generalItem->purchased_quantity * $ratio, 2);
                $branchItem->update([
                    'purchased_quantity' => $allocatedQuantity,
                    'received_quantity' => $allocatedQuantity,
                    'actual_price' => $unitCost,
                    'discount_amount' => 0,
                    'actual_total' => round($allocatedQuantity * $unitCost, 2),
                    'status' => abs($allocatedQuantity - (float) $branchItem->requested_quantity) > 0.009
                        ? PurchaseOrderItem::STATUS_ADJUSTED
                        : PurchaseOrderItem::STATUS_PURCHASED,
                ]);

                $remainingQuantity = round($remainingQuantity - $allocatedQuantity, 2);
            }
        }
    }

    private function updateProductCosts(GeneralPurchaseOrder $order): void
    {
        foreach ($order->items as $item) {
            if ($item->unavailable || ! $item->product_id || (float) $item->purchase_price <= 0) {
                continue;
            }

            $product = Product::query()->find($item->product_id);

            if (! $product) {
                continue;
            }

            if ($item->purchase_presentation === 'Caja') {
                $boxCost = (float) $item->purchase_price;
                $boxSalePrice = round($boxCost * (1 + ((float) $product->margin_percentage / 100)), 4);
                $product->update([
                    'cost_per_box' => $boxCost,
                    'sale_price_per_box' => $boxSalePrice,
                ]);
                continue;
            }

            $pieceCost = (float) $item->purchase_price;
            $pieceSalePrice = round($pieceCost * (1 + ((float) $product->margin_percentage / 100)), 4);
            $product->update([
                'cost_per_piece' => $pieceCost,
                'sale_price_per_piece' => $pieceSalePrice,
                'cost' => $pieceCost,
                'sale_price' => $pieceSalePrice,
            ]);
        }
    }

    private function participationFor(PurchaseCycle $cycle, int $branchId): PurchaseCycleBranch
    {
        $participation = $cycle->branches()->where('branch_id', $branchId)->lockForUpdate()->first();

        if (! $participation) {
            throw ValidationException::withMessages([
                'cycle' => 'La sucursal no forma parte del ciclo de compra actual.',
            ]);
        }

        return $participation;
    }

    private function persistGeneralSelection(
        PurchaseCycle $cycle,
        User $user,
        array $orderIds
    ): GeneralPurchaseOrder {
        return DB::transaction(function () use ($cycle, $user, $orderIds) {
            $cycle = PurchaseCycle::query()->lockForUpdate()->findOrFail($cycle->id);
            $selectedOrderIds = collect($orderIds)
                ->map(fn ($orderId) => (int) $orderId)
                ->filter()
                ->unique()
                ->values();

            if ($selectedOrderIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'order_ids' => 'Selecciona al menos una orden de compra para continuar.',
                ]);
            }

            $draft = null;

            $draft = GeneralPurchaseOrder::create([
                'purchase_cycle_id' => $cycle->id,
                'created_by' => $user->id,
                'status' => GeneralPurchaseOrder::STATUS_DRAFT,
            ]);

            $orders = PurchaseOrder::query()
                ->with(['branch', 'items.product.barcodes'])
                ->whereIn('id', $selectedOrderIds)
                ->lockForUpdate()
                ->get();

            if ($orders->count() !== $selectedOrderIds->count()) {
                throw ValidationException::withMessages([
                    'order_ids' => 'Una o más órdenes seleccionadas ya no están disponibles.',
                ]);
            }

            $user->loadMissing('role');
            $accessibleBranchIds = collect($user->accessibleBranchIds());
            $isInventoryUser = $user->role?->name === 'Inventario';

            foreach ($orders as $branchOrder) {
                $belongsToThisDraft = (int) $branchOrder->general_purchase_order_id === (int) $draft->id;

                if (
                    $branchOrder->status !== PurchaseOrder::STATUS_GENERATED
                    || $branchOrder->review_status !== PurchaseOrder::REVIEW_APPROVED
                    || ! $branchOrder->assigned_to_user_id
                    || ($branchOrder->general_purchase_order_id && ! $belongsToThisDraft)
                    || ! $accessibleBranchIds->contains((int) $branchOrder->branch_id)
                    || ($isInventoryUser && (int) $branchOrder->assigned_to_user_id !== (int) $user->id)
                ) {
                    throw ValidationException::withMessages([
                        'order_ids' => "La orden {$branchOrder->folio} no está disponible para esta orden general.",
                    ]);
                }
            }

            PurchaseOrder::query()
                ->where('general_purchase_order_id', $draft->id)
                ->whereNotIn('id', $selectedOrderIds)
                ->update(['general_purchase_order_id' => null]);

            $draft->items()->delete();
            $draft->update([
                'purchase_cycle_id' => $cycle->id,
                'status' => GeneralPurchaseOrder::STATUS_DRAFT,
                'estimated_total' => 0,
            ]);

            PurchaseOrder::query()
                ->whereIn('id', $selectedOrderIds)
                ->update([
                    'purchase_cycle_id' => $cycle->id,
                    'general_purchase_order_id' => $draft->id,
                ]);

            $sourceItems = $orders->flatMap(fn (PurchaseOrder $branchOrder) => $branchOrder->items);

            foreach ($sourceItems->groupBy('product_id') as $productId => $items) {
                $this->createGeneralItem($draft, $productId, $items);
            }

            $draft->update([
                'estimated_total' => 0,
            ]);

            $draft->update([
                'folio' => $draft->folio ?: sprintf('OCG-%s-%04d', now()->format('Ymd'), $draft->id),
                'status' => GeneralPurchaseOrder::STATUS_PURCHASING,
            ]);

            $hasPendingOrders = PurchaseOrder::query()
                ->where('purchase_cycle_id', $cycle->id)
                ->where('status', PurchaseOrder::STATUS_GENERATED)
                ->whereNull('general_purchase_order_id')
                ->exists();

            if (! $hasPendingOrders) {
                $cycle->update([
                    'status' => PurchaseCycle::STATUS_CONSOLIDATED,
                    'consolidated_at' => now(),
                ]);
            }

            return $draft->fresh(['items', 'branchOrders.branch']);
        });
    }

    private function assertRecordVersion($record, ?string $recordVersion): void
    {
        if (!$recordVersion || !$record->updated_at?->equalTo(Carbon::parse($recordVersion))) {
            throw ValidationException::withMessages([
                'record_version' => 'Otra persona modifico esta orden. Revisa la informacion actual antes de guardar nuevamente.',
            ]);
        }
    }

    private function createGeneralItem(
        GeneralPurchaseOrder $order,
        int|string $productId,
        Collection $items
    ): void {
        $first = $items->first();
        $product = $first?->product;
        $requestedQuantity = (float) $items->sum('requested_quantity');
        $estimatedTotal = (float) $items->sum('estimated_total');
        $estimatedUnitPrice = $requestedQuantity > 0 ? $estimatedTotal / $requestedQuantity : 0;
        $inventoryUnit = $product?->inventory_unit ?? $product?->unit ?? 'pza';
        $presentations = $items
            ->pluck('purchase_presentation')
            ->filter()
            ->unique()
            ->values();
        $purchasePresentation = $inventoryUnit === 'kg'
            ? 'Kilo'
            : ($presentations->count() === 1 && $presentations->first() === 'Caja' ? 'Caja' : 'Pieza');
        $unitsPerPackage = $purchasePresentation === 'Caja'
            ? max(1, (int) $product?->pieces_per_box)
            : 1;
        $packageQuantity = $purchasePresentation === 'Caja'
            ? (float) $items->sum('package_quantity')
            : $requestedQuantity;

        $order->items()->create([
            'product_id' => $productId ?: null,
            'product_name' => $product?->name ?? 'Producto sin nombre',
            'product_description' => $product?->description,
            'product_code' => $product?->barcodes?->first()?->code,
            'base_unit' => $inventoryUnit,
            'requested_quantity' => $requestedQuantity,
            'estimated_unit_price' => round($estimatedUnitPrice, 2),
            'estimated_total' => 0,
            'purchase_presentation' => $purchasePresentation,
            'package_quantity' => $packageQuantity,
            'units_per_package' => $unitsPerPackage,
            'purchase_price' => round($estimatedUnitPrice, 2),
            'purchased_quantity' => $requestedQuantity,
            'gross_total' => 0,
            'actual_total' => 0,
            'net_unit_cost' => 0,
        ]);
    }

    private function applyCapture(GeneralPurchaseOrderItem $item, array $input): void
    {
        $product = $item->product;
        if (! $product || $product->inventory_quantity_mode === 'legacy_presentation') {
            throw ValidationException::withMessages([
                'items' => 'Hay productos con existencias históricas pendientes de conciliar.',
            ]);
        }

        $unavailable = (bool) ($input['unavailable'] ?? false);
        $presentation = (string) ($input['purchase_presentation'] ?? 'Pieza');
        $inventoryUnit = $product->inventory_unit ?? $product->unit ?? 'pza';

        if ($inventoryUnit === 'kg' && $presentation !== 'Kilo') {
            throw ValidationException::withMessages(['items' => "{$product->name} debe comprarse en kilogramos."]);
        }

        if ($inventoryUnit !== 'kg' && ! in_array($presentation, ['Pieza', 'Caja'], true)) {
            throw ValidationException::withMessages(['items' => "La presentación de {$product->name} no es válida."]);
        }

        if ($presentation === 'Caja' && (! $product->has_box_presentation || (int) $product->pieces_per_box < 2)) {
            throw ValidationException::withMessages(['items' => "{$product->name} no tiene una caja configurada."]);
        }

        $packageQuantity = $unavailable ? 0 : (float) ($input['package_quantity'] ?? 0);
        $unitsPerPackage = $unavailable
            ? 0
            : ($presentation === 'Caja' ? (int) $product->pieces_per_box : 1);

        if ($presentation !== 'Kilo' && abs($packageQuantity - round($packageQuantity)) > 0.0000001) {
            throw ValidationException::withMessages(['items' => 'Las piezas y cajas deben capturarse en cantidades enteras.']);
        }

        if ($presentation === 'Kilo') {
            $packageQuantity = round($packageQuantity, 3);
        }
        $purchasePrice = $unavailable ? 0 : (float) ($input['purchase_price'] ?? 0);
        $purchasedQuantity = $packageQuantity * $unitsPerPackage;
        $grossTotal = $packageQuantity * $purchasePrice;
        $discountAmount = $unavailable ? 0 : (float) ($input['discount_amount'] ?? 0);
        $actualTotal = max(0, $grossTotal - $discountAmount);

        $item->update([
            'purchase_presentation' => $presentation,
            'package_quantity' => $packageQuantity,
            'units_per_package' => $unitsPerPackage,
            'purchase_price' => $purchasePrice,
            'purchased_quantity' => $purchasedQuantity,
            'gross_total' => round($grossTotal, 2),
            'discount_amount' => round($discountAmount, 2),
            'actual_total' => round($actualTotal, 2),
            'net_unit_cost' => $purchasedQuantity > 0 ? round($actualTotal / $purchasedQuantity, 4) : 0,
            'unavailable' => $unavailable,
            'purchase_notes' => $input['purchase_notes'] ?? null,
        ]);
    }

    private function refreshGeneralTotals(GeneralPurchaseOrder $order, array $payload): void
    {
        $order->update([
            'purchased_at' => $payload['purchased_at'] ?? now()->toDateString(),
            'gross_total' => round((float) $order->items->sum(fn ($item) => (float) $item->gross_total), 2),
            'discount_total' => round((float) $order->items->sum(fn ($item) => (float) $item->discount_amount), 2),
            'actual_total' => round((float) $order->items->sum(fn ($item) => (float) $item->actual_total), 2),
        ]);
    }
}
