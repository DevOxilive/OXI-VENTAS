<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class PendingPurchaseOrderEditor
{
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.branch_product_id' => ['required', 'integer', 'distinct', 'exists:branch_products,id'],
            'items.*.requested_quantity' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999.99'],
            'record_version' => ['required', 'date'],
        ];
    }

    public function update(
        PurchaseOrder $purchaseOrder,
        Branch $branch,
        array $items,
        User $editor,
        bool $editedByInventory,
        ?string $recordVersion = null,
    ): PurchaseOrder {
        return DB::transaction(function () use ($purchaseOrder, $branch, $items, $editor, $editedByInventory, $recordVersion) {
            $purchaseOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);

            if (!$recordVersion || !$purchaseOrder->updated_at?->equalTo(Carbon::parse($recordVersion))) {
                throw ValidationException::withMessages([
                    'record_version' => 'Otra persona modifico esta orden. Revisa la informacion actual antes de guardar nuevamente.',
                ]);
            }

            abort_unless($purchaseOrder->branch_id === $branch->id, 404);
            abort_unless(
                $purchaseOrder->status === PurchaseOrder::STATUS_GENERATED,
                422,
                'Solo se pueden modificar órdenes pendientes.'
            );
            abort_if(
                $purchaseOrder->general_purchase_order_id,
                422,
                'La orden ya forma parte de una orden general y no puede modificarse.'
            );

            $branchProductIds = collect($items)
                ->pluck('branch_product_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $branchProducts = BranchProduct::query()
                ->with('product')
                ->where('branch_id', $branch->id)
                ->whereIn('id', $branchProductIds)
                ->get()
                ->keyBy('id');

            if ($branchProducts->count() !== $branchProductIds->count()) {
                throw ValidationException::withMessages([
                    'items' => 'Todos los productos deben pertenecer a la sucursal de la orden.',
                ]);
            }

            $purchaseOrder->items()
                ->whereNotIn('branch_product_id', $branchProductIds)
                ->delete();

            foreach ($items as $item) {
                $branchProduct = $branchProducts->get((int) $item['branch_product_id']);
                $requestedQuantity = (float) $item['requested_quantity'];
                $estimatedPrice = (float) ($branchProduct->product?->cost_per_piece ?? $branchProduct->product?->cost ?? 0);

                $purchaseOrder->items()->updateOrCreate(
                    ['branch_product_id' => $branchProduct->id],
                    [
                        'product_id' => $branchProduct->product_id,
                        'current_stock' => $branchProduct->stock,
                        'min_stock' => $branchProduct->min_stock,
                        'requested_quantity' => $requestedQuantity,
                        'estimated_price' => $estimatedPrice,
                        'estimated_total' => $estimatedPrice * $requestedQuantity,
                        'status' => PurchaseOrderItem::STATUS_REQUESTED,
                    ],
                );
            }

            $purchaseOrder->load('items');
            $changes = [
                'estimated_total' => $purchaseOrder->items->sum(
                    fn (PurchaseOrderItem $item) => (float) $item->estimated_total
                ),
            ];

            if ($editedByInventory) {
                $changes['inventory_edited_by'] = $editor->id;
                $changes['inventory_edited_at'] = now();
            }

            $changes['review_status'] = PurchaseOrder::REVIEW_PENDING;
            $changes['reviewed_by'] = null;
            $changes['reviewed_at'] = null;

            $purchaseOrder->update($changes);

            return $purchaseOrder->fresh(['branch', 'user', 'items.branchProduct.product']);
        });
    }
}
