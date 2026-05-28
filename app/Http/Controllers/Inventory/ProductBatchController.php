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

            $previousQuantity = (float) $productBatch->quantity;
            $newQuantity = (float) $validated['quantity'];

            $difference = $newQuantity - $previousQuantity;

            $status = $newQuantity <= 0 ? 'EXHAUSTED' : 'ACTIVE';

            $productBatch->update([
                'lot_number' => $validated['lot_number'],
                'expiration_date' => $validated['expiration_date'],
                'supplier' => $validated['supplier'],
                'received_at' => $validated['received_at'],
                'quantity' => $newQuantity,
                'status' => $status,
            ]);

            $branchProduct = $productBatch->branchProduct;

            $previousStock = (float) $branchProduct->stock;
            $newStock = $previousStock + $difference;

            $branchProduct->update([
                'stock' => $newStock,
            ]);

            if ($difference !== 0.0) {
                $movement = StockMovement::create([
                    'branch_product_id' => $branchProduct->id,
                    'user_id' => $request->user()?->id,
                    'type' => 'ADJUSTMENT',
                    'reason' => 'MANUAL',
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
                    'allocation_method' => 'MANUAL',
                ]);
            }

            return $branchProduct->fresh();
        });

        event(new InventoryStockUpdated($branchProduct));

        return back()->with('success', 'Lote actualizado correctamente.');
    }
}
