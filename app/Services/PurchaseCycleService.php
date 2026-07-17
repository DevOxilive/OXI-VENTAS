<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\GeneralPurchaseOrder;
use App\Models\GeneralPurchaseOrderItem;
use App\Models\PurchaseCycle;
use App\Models\PurchaseCycleBranch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseCycleService
{
    public function currentOpenCycle(?User $user = null): PurchaseCycle
    {
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
        });
    }

    public function registerOrder(PurchaseOrder $order, ?User $user = null): PurchaseCycle
    {
        return DB::transaction(function () use ($order, $user) {
            $cycle = $this->currentOpenCycle($user);
            $participation = $this->participationFor($cycle, $order->branch_id);

            if ($participation->purchase_order_id
                && (int) $participation->purchase_order_id !== (int) $order->id) {
                throw ValidationException::withMessages([
                    'cycle' => 'Esta sucursal ya envió su solicitud para el ciclo de compra actual.',
                ]);
            }

            $participation->update([
                'purchase_order_id' => $order->id,
                'submitted_without_items' => false,
                'submitted_at' => now(),
            ]);

            $order->update(['purchase_cycle_id' => $cycle->id]);

            return $cycle;
        });
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

    public function consolidate(PurchaseCycle $cycle, ?User $user = null, array $branchIds = []): ?GeneralPurchaseOrder
    {
        return DB::transaction(function () use ($cycle, $user, $branchIds) {
            $cycle = PurchaseCycle::query()->lockForUpdate()->findOrFail($cycle->id);
            $cycle->load(['branches.branch', 'branches.order']);

            if ($cycle->status !== PurchaseCycle::STATUS_OPEN) {
                throw ValidationException::withMessages([
                    'cycle' => 'Este ciclo ya fue consolidado.',
                ]);
            }

            $selectedBranchIds = collect($branchIds)
                ->map(fn ($branchId) => (int) $branchId)
                ->filter()
                ->unique()
                ->values();

            if ($selectedBranchIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'branch_ids' => 'Selecciona al menos una sucursal para generar la orden general.',
                ]);
            }

            $selectedBranches = $cycle->branches->filter(function (PurchaseCycleBranch $branch) use ($selectedBranchIds) {
                return $selectedBranchIds->contains((int) $branch->branch_id);
            });

            if ($selectedBranches->count() !== $selectedBranchIds->count()) {
                throw ValidationException::withMessages([
                    'branch_ids' => 'Una o mas sucursales seleccionadas no forman parte del ciclo actual.',
                ]);
            }

            $missing = $selectedBranches->filter(fn (PurchaseCycleBranch $branch) => ! $branch->submitted_at);

            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'branch_ids' => 'Estas sucursales aun no han enviado o cerrado su solicitud: '.$missing->pluck('branch.name')->join(', ').'.',
                ]);
            }

            $orderIds = $selectedBranches->pluck('purchase_order_id')->filter()->values();

            if ($orderIds->isEmpty()) {
                $cycle->update([
                    'status' => PurchaseCycle::STATUS_COMPLETED,
                    'consolidated_at' => now(),
                    'completed_at' => now(),
                ]);

                return null;
            }

            $sourceItems = PurchaseOrderItem::query()
                ->with(['product.barcodes', 'order.branch'])
                ->whereIn('purchase_order_id', $orderIds)
                ->get();

            $generalOrder = GeneralPurchaseOrder::create([
                'purchase_cycle_id' => $cycle->id,
                'created_by' => $user?->id,
                'status' => GeneralPurchaseOrder::STATUS_PURCHASING,
            ]);

            $generalOrder->update([
                'folio' => sprintf('OCG-%s-%04d', now()->format('Ymd'), $generalOrder->id),
            ]);

            foreach ($sourceItems->groupBy('product_id') as $productId => $items) {
                $this->createGeneralItem($generalOrder, $productId, $items);
            }

            $generalOrder->update([
                'estimated_total' => $generalOrder->items()->sum('estimated_total'),
            ]);

            PurchaseOrder::query()
                ->whereIn('id', $orderIds)
                ->update(['general_purchase_order_id' => $generalOrder->id]);

            $cycle->update([
                'status' => PurchaseCycle::STATUS_CONSOLIDATED,
                'consolidated_at' => now(),
            ]);

            return $generalOrder;
        });
    }

    public function saveCapture(GeneralPurchaseOrder $order, array $payload): GeneralPurchaseOrder
    {
        return DB::transaction(function () use ($order, $payload) {
            $order = GeneralPurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status !== GeneralPurchaseOrder::STATUS_PURCHASING) {
                throw ValidationException::withMessages([
                    'order' => 'La orden general completada ya no puede modificarse.',
                ]);
            }

            $incomingItems = collect($payload['items'])->keyBy('id');
            $order->items()->each(function (GeneralPurchaseOrderItem $item) use ($incomingItems) {
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
            $order->load(['items', 'branchOrders.items']);

            $invalidItem = $order->items->first(fn (GeneralPurchaseOrderItem $item) => ! $item->unavailable
                && ((float) $item->package_quantity <= 0
                    || (float) $item->units_per_package <= 0
                    || (float) $item->package_price <= 0)
            );

            if ($invalidItem) {
                throw ValidationException::withMessages([
                    'items' => "Completa presentación, bultos, contenido y precio de {$invalidItem->product_name}.",
                ]);
            }

            foreach ($order->branchOrders as $branchOrder) {
                foreach ($branchOrder->items as $branchItem) {
                    $generalItem = $order->items->firstWhere('product_id', $branchItem->product_id);

                    if (! $generalItem || $generalItem->unavailable) {
                        $branchItem->update([
                            'purchased_quantity' => 0,
                            'actual_price' => 0,
                            'discount_amount' => 0,
                            'actual_total' => 0,
                            'status' => PurchaseOrderItem::STATUS_UNAVAILABLE,
                        ]);

                        continue;
                    }

                    $coverage = min(
                        1,
                        (float) $generalItem->purchased_quantity / max(0.01, (float) $generalItem->requested_quantity)
                    );
                    $allocatedQuantity = round((float) $branchItem->requested_quantity * $coverage, 2);
                    $netUnitCost = (float) $generalItem->net_unit_cost;
                    $normalUnitCost = (float) $generalItem->units_per_package > 0
                        ? (float) $generalItem->package_price / (float) $generalItem->units_per_package
                        : $netUnitCost;
                    $discount = max(0, ($normalUnitCost - $netUnitCost) * $allocatedQuantity);

                    $branchItem->update([
                        'purchased_quantity' => $allocatedQuantity,
                        'actual_price' => $netUnitCost,
                        'discount_amount' => round($discount, 2),
                        'actual_total' => round($allocatedQuantity * $netUnitCost, 2),
                        'status' => $coverage < 1
                            || $discount > 0
                            || abs($netUnitCost - (float) $branchItem->estimated_price) > 0.009
                            ? PurchaseOrderItem::STATUS_ADJUSTED
                            : PurchaseOrderItem::STATUS_PURCHASED,
                    ]);
                }

                $branchOrder->update([
                    'actual_total' => $branchOrder->items()->sum('actual_total'),
                    'status' => PurchaseOrder::STATUS_COMPLETED,
                    'purchased_at' => $order->purchased_at,
                    'completed_by' => $user?->id,
                    'completed_at' => now(),
                ]);
            }

            $order->update([
                'status' => GeneralPurchaseOrder::STATUS_COMPLETED,
                'completed_by' => $user?->id,
                'completed_at' => now(),
            ]);

            $order->cycle()->update([
                'status' => PurchaseCycle::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            return $order->fresh(['items', 'branchOrders']);
        });
    }

    private function participationFor(PurchaseCycle $cycle, int $branchId): PurchaseCycleBranch
    {
        $participation = $cycle->branches()->where('branch_id', $branchId)->first();

        if (! $participation) {
            throw ValidationException::withMessages([
                'cycle' => 'La sucursal no forma parte del ciclo de compra actual.',
            ]);
        }

        return $participation;
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

        $order->items()->create([
            'product_id' => $productId ?: null,
            'product_name' => $product?->name ?? 'Producto sin nombre',
            'product_code' => $product?->barcodes?->first()?->code,
            'base_unit' => $product?->unit ?: 'pieza',
            'requested_quantity' => $requestedQuantity,
            'estimated_unit_price' => round($estimatedUnitPrice, 2),
            'estimated_total' => round($estimatedTotal, 2),
            'purchase_presentation' => 'Paquete',
            'package_quantity' => $requestedQuantity,
            'units_per_package' => 1,
            'package_price' => round($estimatedUnitPrice, 2),
            'purchased_quantity' => $requestedQuantity,
            'gross_total' => round($estimatedTotal, 2),
            'actual_total' => round($estimatedTotal, 2),
            'net_unit_cost' => round($estimatedUnitPrice, 4),
        ]);
    }

    private function applyCapture(GeneralPurchaseOrderItem $item, array $input): void
    {
        $unavailable = (bool) ($input['unavailable'] ?? false);
        $packageQuantity = $unavailable ? 0 : (float) ($input['package_quantity'] ?? 0);
        $unitsPerPackage = $unavailable ? 0 : (float) ($input['units_per_package'] ?? 0);
        $packagePrice = $unavailable ? 0 : (float) ($input['package_price'] ?? 0);
        $purchasedQuantity = $packageQuantity * $unitsPerPackage;
        $grossTotal = $packageQuantity * $packagePrice;
        $actualTotal = $unavailable ? 0 : min($grossTotal, (float) ($input['actual_total'] ?? $grossTotal));

        if (! $unavailable && (float) ($input['actual_total'] ?? 0) > $grossTotal) {
            throw ValidationException::withMessages([
                'items' => "El total pagado de {$item->product_name} no puede superar el subtotal de sus bultos.",
            ]);
        }
        $discount = max(0, $grossTotal - $actualTotal);

        $item->update([
            'purchase_presentation' => $input['purchase_presentation'] ?? 'Paquete',
            'package_quantity' => $packageQuantity,
            'units_per_package' => $unitsPerPackage,
            'package_price' => $packagePrice,
            'purchased_quantity' => $purchasedQuantity,
            'gross_total' => round($grossTotal, 2),
            'discount_amount' => round($discount, 2),
            'actual_total' => round($actualTotal, 2),
            'net_unit_cost' => $purchasedQuantity > 0 ? round($actualTotal / $purchasedQuantity, 4) : 0,
            'unavailable' => $unavailable,
            'promotion_notes' => $input['promotion_notes'] ?? null,
        ]);
    }

    private function refreshGeneralTotals(GeneralPurchaseOrder $order, array $payload): void
    {
        $order->update([
            'purchased_at' => $payload['purchased_at'] ?? now()->toDateString(),
            'notes' => $payload['notes'] ?? null,
            'gross_total' => $order->items()->sum('gross_total'),
            'discount_total' => $order->items()->sum('discount_amount'),
            'actual_total' => $order->items()->sum('actual_total'),
        ]);
    }
}
