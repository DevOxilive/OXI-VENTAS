<?php

namespace App\Http\Controllers\Inventory;

use App\Events\InventoryStockUpdated;
use App\Http\Controllers\Controller;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Models\StockMovementBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductBatchController extends Controller
{
    public function update(Request $request, ProductBatch $productBatch)
    {
        $validated = $request->validate([
            'lot_number' => ['nullable', 'string', 'max:255'],
            'expiration_date' => ['nullable', 'date'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'received_at' => ['nullable', 'date'],
            'quantity' => ['required', 'numeric', 'min:0'],
        ]);

        $branchProduct = DB::transaction(function () use ($validated, $productBatch, $request) {
            $productBatch = ProductBatch::whereKey($productBatch->id)
                ->lockForUpdate()
                ->firstOrFail();

            $branchProduct = $productBatch->branchProduct()
                ->lockForUpdate()
                ->firstOrFail();

            $previousQuantity = (float) $productBatch->quantity;
            $newQuantity = (float) $validated['quantity'];
            $difference = $newQuantity - $previousQuantity;

            $previousStock = (float) ProductBatch::where('branch_product_id', $branchProduct->id)
                ->where('status', ProductBatch::STATUS_ACTIVE)
                ->where('quantity', '>', 0)
                ->sum('quantity');

            $status = $newQuantity <= 0
                ? ProductBatch::STATUS_DEPLETED
                : ProductBatch::STATUS_ACTIVE;

            $productBatch->update([
                'lot_number' => $validated['lot_number'] ?? null,
                'expiration_date' => $validated['expiration_date'] ?? null,
                'supplier' => $validated['supplier'] ?? null,
                'received_at' => $validated['received_at'] ?? null,
                'quantity' => $newQuantity,
                'status' => $status,
            ]);

            $newStock = (float) ProductBatch::where('branch_product_id', $branchProduct->id)
                ->where('status', ProductBatch::STATUS_ACTIVE)
                ->where('quantity', '>', 0)
                ->sum('quantity');

            $branchProduct->update([
                'stock' => $newStock,
            ]);

            if (round($difference, 3) !== 0.0) {
                $movement = StockMovement::create([
                    'branch_product_id' => $branchProduct->id,
                    'user_id' => $request->user()?->id,
                    'type' => StockMovement::TYPE_ADJUSTMENT,
                    'reason' => StockMovement::REASON_INVENTORY_DIFFERENCE,
                    'quantity' => abs($difference),
                    'previous_stock' => $previousStock,
                    'new_stock' => $newStock,
                    'notes' => 'Corrección manual de lote',
                ]);

                StockMovementBatch::create([
                    'stock_movement_id' => $movement->id,
                    'product_batch_id' => $productBatch->id,
                    'quantity' => abs($difference),
                    'previous_batch_quantity' => $previousQuantity,
                    'new_batch_quantity' => $newQuantity,
                    'allocation_method' => StockMovementBatch::ALLOCATION_MANUAL,
                ]);
            }

            return $branchProduct->fresh([
                'product.category',
                'product.barcodes',
                'batches',
                'movements.user',
                'movements.batches.productBatch',
            ]);
        });

        event(new InventoryStockUpdated($branchProduct));

        return back()->with('success', 'Lote actualizado correctamente.');
    }
}