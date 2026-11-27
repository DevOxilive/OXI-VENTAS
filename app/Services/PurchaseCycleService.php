<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchProduct;
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
                ->where('is_central_warehouse', false)
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
                $item->update(['physically_reviewed' => (bool) ($input['physically_reviewed'] ?? false)]);
                $this->syncPlannedBatches($item, $input);
                $plan = collect($input['branch_allocations'] ?? []);
                // La compra central puede repartir a cualquier sucursal activa.
                // Las solicitudes originales siguen siendo la sugerencia, no una
                // restricción que impida surtir otra tienda.
                $permitted = Branch::query()->where('active', true)->pluck('id')->map(fn ($id) => (int) $id);
                if ($plan->isNotEmpty()) {
                    $ids = $plan->pluck('branch_id')->map(fn ($id) => (int) $id);
                    $invalid = $ids->unique()->count() !== $ids->count()
                        || $ids->contains(fn ($id) => ! $permitted->contains($id))
                        || abs((float) $plan->sum('quantity') - (float) $item->purchased_quantity) > 0.0009
                        || ($item->base_unit !== 'kg' && $plan->contains(fn ($row) => abs($row['quantity'] - round($row['quantity'])) > 0.0009));
                    if ($invalid) {
                        throw ValidationException::withMessages(['items' => "Revisa la distribución de {$item->product_name}: debe coincidir con la compra y sus sucursales."]);
                    }
                }
                $item->update(['branch_allocations' => $plan->values()->all()]);
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
            $this->syncCentralWarehouseOrder($order);
            PurchaseOrder::query()
                ->where('general_purchase_order_id', $order->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $order->load('branchOrders.items');

            $invalidItem = $order->items->first(fn (GeneralPurchaseOrderItem $item) => ! $item->unavailable
                && (! $item->physically_reviewed
                    || empty($item->branch_allocations)
                    || ! $item->plannedBatches()->exists()
                    || (float) $item->package_quantity <= 0
                    || ($item->purchase_presentation === 'Caja' && (float) $item->units_per_package <= 0)
                    || (float) $item->purchase_price <= 0)
            );

            if ($invalidItem) {
                throw ValidationException::withMessages([
                    'items' => "Revisa físicamente y completa cantidad, precio, lotes y distribución de {$invalidItem->product_name}.",
                ]);
            }

            $incompleteBatchItem = $order->items->first(function (GeneralPurchaseOrderItem $item) {
                if ($item->unavailable || ! $item->plannedBatches()->exists()) return false;

                return abs((float) $item->plannedBatches()->sum('presentation_quantity') - (float) $item->package_quantity) > 0.0009;
            });
            if ($incompleteBatchItem) {
                throw ValidationException::withMessages([
                    'items' => "Completa la cantidad de lotes de {$incompleteBatchItem->product_name}.",
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
            $planned = collect($generalItem->branch_allocations ?? [])->keyBy('branch_id');
            $branchRemaining = $planned->map(fn ($row) => (float) $row['quantity'])->all();

            foreach ($allocations as $index => $allocation) {
                /** @var PurchaseOrderItem $branchItem */
                $branchItem = $allocation['item'];
                $ratio = (float) $branchItem->requested_quantity / $requestedTotal;
                $isLast = $index === $lastIndex;

                $allocatedQuantity = $isLast
                    ? $remainingQuantity
                    : round((float) $generalItem->purchased_quantity * $ratio, 2);
                if ($planned->isNotEmpty()) {
                    $branchId = $allocation['order']->branch_id;
                    $sameBranch = $allocations->filter(fn ($row) => $row['order']->branch_id === $branchId);
                    $lastForBranch = $sameBranch->last()['item']->id === $branchItem->id;
                    $branchRequested = max(0.001, (float) $sameBranch->sum(fn ($row) => $row['item']->requested_quantity));
                    $plannedQuantity = (float) ($planned->get($branchId)['quantity'] ?? 0);
                    $share = $plannedQuantity * (float) $branchItem->requested_quantity / $branchRequested;
                    $branchRemaining[$branchId] ??= $plannedQuantity;
                    $allocatedQuantity = $lastForBranch ? $branchRemaining[$branchId]
                        : min($branchRemaining[$branchId], $generalItem->base_unit === 'kg' ? floor($share * 1000) / 1000 : floor($share));
                    $branchRemaining[$branchId] = round($branchRemaining[$branchId] - $allocatedQuantity, 3);
                }
                $branchItem->update([
                    'purchased_quantity' => $allocatedQuantity,
                    'received_quantity' => null,
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

    private function syncCentralWarehouseOrder(GeneralPurchaseOrder $order): void
    {
        $warehouse = Branch::query()
            ->where('is_central_warehouse', true)
            ->where('active', true)
            ->first();

        if (! $warehouse) return;

        $items = $order->items->map(function (GeneralPurchaseOrderItem $item) use ($warehouse) {
            $allocation = collect($item->branch_allocations ?? [])
                ->first(fn (array $row) => (int) ($row['branch_id'] ?? 0) === (int) $warehouse->id);

            return [
                'item' => $item,
                'quantity' => round((float) ($allocation['quantity'] ?? 0), 3),
            ];
        })->filter(fn (array $row) => $row['quantity'] > 0)->values();

        if ($items->isEmpty()) return;

        $centralOrder = PurchaseOrder::query()->firstOrCreate(
            [
                'general_purchase_order_id' => $order->id,
                'branch_id' => $warehouse->id,
            ],
            [
                'purchase_cycle_id' => $order->purchase_cycle_id,
                'user_id' => $order->created_by,
                'folio' => "ALM-{$order->folio}",
                'source' => PurchaseOrder::SOURCE_CENTRAL,
                'status' => PurchaseOrder::STATUS_REVIEW,
                'review_status' => PurchaseOrder::REVIEW_PENDING,
                'generated_at' => now(),
                'purchased_at' => $order->purchased_at,
            ],
        );

        foreach ($items as ['item' => $generalItem, 'quantity' => $quantity]) {
            $branchProduct = BranchProduct::query()->firstOrCreate(
                [
                    'branch_id' => $warehouse->id,
                    'product_id' => $generalItem->product_id,
                ],
                [
                    'stock' => 0,
                    'min_stock' => 0,
                    'status' => BranchProduct::STATUS_ACTIVE,
                ],
            );

            $centralOrder->items()->updateOrCreate(
                ['product_id' => $generalItem->product_id],
                [
                    'branch_product_id' => $branchProduct->id,
                    'current_stock' => $branchProduct->stock,
                    'min_stock' => $branchProduct->min_stock,
                    'requested_quantity' => $quantity,
                    'purchase_presentation' => $generalItem->purchase_presentation,
                    'package_quantity' => $generalItem->package_quantity,
                    'units_per_package' => $generalItem->units_per_package,
                    'estimated_price' => $generalItem->purchase_price,
                    'estimated_total' => round($quantity * (float) $generalItem->net_unit_cost, 2),
                    'status' => PurchaseOrderItem::STATUS_REQUESTED,
                ],
            );
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
                $prices = ['cost_per_box' => $boxCost];
                if ($product->box_pricing_mode === 'percentage' && $product->box_margin_percentage !== null) {
                    $prices['sale_price_per_box'] = round($boxCost * (1 + ((float) $product->box_margin_percentage / 100)), 2);
                }
                $product->update($prices);
                continue;
            }

            if ($item->purchase_presentation === 'Paquete') {
                $product->update(['cost_per_package' => (float) $item->purchase_price]);
                continue;
            }

            $pieceCost = (float) ($item->net_unit_cost ?: $item->purchase_price);
            $prices = [
                'cost_per_piece' => $pieceCost,
                'cost' => $pieceCost,
            ];
            if ($product->piece_pricing_mode === 'percentage' && $product->piece_margin_percentage !== null) {
                $prices['sale_price_per_piece'] = round($pieceCost * (1 + ((float) $product->piece_margin_percentage / 100)), 2);
                $prices['sale_price'] = $prices['sale_price_per_piece'];
            }
            $pieceSalePrice = $prices['sale_price_per_piece'] ?? $product->sale_price_per_piece ?? $product->sale_price;
            $prices['margin_percentage'] = round((((float) $pieceSalePrice - $pieceCost) / $pieceCost) * 100, 2);
            $product->update($prices);
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
            $accessibleBranchIds = $user->hasPermission('inventory.purchase-orders.general.create')
                ? Branch::query()->where('active', true)->pluck('id')
                : collect($user->accessibleBranchIds());
            foreach ($orders as $branchOrder) {
                $belongsToThisDraft = (int) $branchOrder->general_purchase_order_id === (int) $draft->id;

                if (
                    $branchOrder->status !== PurchaseOrder::STATUS_GENERATED
                    || $branchOrder->review_status !== PurchaseOrder::REVIEW_APPROVED
                    || ($branchOrder->general_purchase_order_id && ! $belongsToThisDraft)
                    || ! $accessibleBranchIds->contains((int) $branchOrder->branch_id)
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
                'folio' => $draft->folio ?: $this->makeGeneralPurchaseOrderFolio(),
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

    /** Genera el consecutivo de las órdenes de compra generales. */
    private function makeGeneralPurchaseOrderFolio(): string
    {
        $prefix = 'OC-General';
        $lastSequence = GeneralPurchaseOrder::query()
            ->where('folio', 'like', "{$prefix}-%")
            ->lockForUpdate()
            ->pluck('folio')
            ->map(function (?string $folio): int {
                if (! $folio || ! preg_match('/-(\d+)$/', $folio, $matches)) {
                    return 0;
                }

                return (int) $matches[1];
            })
            ->max();

        return sprintf('%s-%d', $prefix, max(1, $lastSequence + 1));
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
            ? ($presentations->count() === 1 && $presentations->first() === 'Gramo' ? 'Gramo' : 'Kilo')
            : match ($presentations->count() === 1 ? $presentations->first() : null) {
                'Caja' => 'Caja',
                'Paquete' => 'Paquete',
                default => 'Pieza',
            };
        $unitsPerPackage = match ($purchasePresentation) {
            'Caja' => max(1, (int) $product?->pieces_per_box),
            'Paquete' => max(1, (int) $product?->pieces_per_package),
            default => 1,
        };
        $packageQuantity = in_array($purchasePresentation, ['Caja', 'Paquete', 'Gramo'], true)
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

        if ($inventoryUnit === 'kg' && ! in_array($presentation, ['Kilo', 'Gramo'], true)) {
            throw ValidationException::withMessages(['items' => "{$product->name} debe comprarse en kilos o gramos."]);
        }

        if ($inventoryUnit !== 'kg' && ! in_array($presentation, ['Pieza', 'Paquete', 'Caja'], true)) {
            throw ValidationException::withMessages(['items' => "La presentación de {$product->name} no es válida."]);
        }

        if ($presentation === 'Caja' && (! $product->has_box_presentation || (int) $product->pieces_per_box < 2)) {
            throw ValidationException::withMessages(['items' => "{$product->name} no tiene una caja configurada."]);
        }

        if ($presentation === 'Paquete' && (! $product->has_package_presentation || (int) $product->pieces_per_package < 2)) {
            throw ValidationException::withMessages(['items' => "{$product->name} no tiene un paquete configurado."]);
        }

        $packageQuantity = $unavailable ? 0 : (float) ($input['package_quantity'] ?? 0);
        $unitsPerPackage = $unavailable
            ? 0
            : match ($presentation) {
                'Caja' => (int) $product->pieces_per_box,
                'Paquete' => (int) $product->pieces_per_package,
                default => 1,
            };

        if ($presentation !== 'Kilo' && abs($packageQuantity - round($packageQuantity)) > 0.0000001) {
            throw ValidationException::withMessages(['items' => 'Las piezas, paquetes, cajas y gramos deben capturarse en cantidades enteras.']);
        }

        if ($presentation === 'Kilo') {
            $packageQuantity = round($packageQuantity, 3);
        }
        $purchasePrice = $unavailable ? 0 : (float) ($input['purchase_price'] ?? 0);
        $purchasedQuantity = $presentation === 'Gramo'
            ? $packageQuantity / 1000
            : $packageQuantity * $unitsPerPackage;
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

    private function syncPlannedBatches(GeneralPurchaseOrderItem $item, array $input): void
    {
        $batches = collect($input['batches'] ?? []);
        $item->plannedBatches()->delete();

        if ($item->unavailable || $batches->isEmpty()) {
            return;
        }

        $presentationTotal = round((float) $batches->sum(fn (array $batch) => (float) ($batch['quantity'] ?? 0)), 3);
        if ($presentationTotal - (float) $item->package_quantity > 0.0009) {
            throw ValidationException::withMessages([
                'items' => "Los lotes de {$item->product_name} superan la cantidad comprada.",
            ]);
        }

        foreach ($batches as $batch) {
            $presentationQuantity = round((float) $batch['quantity'], 3);
            $baseQuantity = match ($item->purchase_presentation) {
                'Gramo' => $presentationQuantity / 1000,
                'Caja', 'Paquete' => $presentationQuantity * (float) $item->units_per_package,
                default => $presentationQuantity,
            };

            $lotAllocations = collect($batch['allocations'] ?? []);
            if ($lotAllocations->isNotEmpty()) {
                $eligibleBranches = Branch::query()
                    ->where('active', true)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
                if ($lotAllocations->pluck('branch_id')->unique()->count() !== $lotAllocations->count()
                    || $lotAllocations->contains(fn ($row) => ! in_array((int) $row['branch_id'], $eligibleBranches)
                        || ($item->base_unit !== 'kg' && abs($row['quantity'] - round($row['quantity'])) > 0.0009))
                    || abs((float) $lotAllocations->sum('quantity') - $baseQuantity) > 0.0009) {
                    throw ValidationException::withMessages(['items' => "Revisa la distribución del lote de {$item->product_name}."]);
                }
            }
            $item->plannedBatches()->create([
                'allocations' => $batch['allocations'] ?? [],
                'supplier' => $batch['supplier'] ?? null,
                'lot_number' => filled($batch['lot_number'] ?? null)
                    ? mb_strtoupper(trim((string) $batch['lot_number']), 'UTF-8')
                    : null,
                'expiration_date' => $batch['expiration_date'] ?? null,
                'purchase_presentation' => $item->purchase_presentation,
                'presentation_quantity' => $presentationQuantity,
                'base_quantity' => round($baseQuantity, 3),
            ]);
        }
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
