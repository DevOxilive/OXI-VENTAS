<?php

namespace App\Http\Controllers\Inventory;

use App\Events\InventoryStockUpdated;
use App\Http\Controllers\Controller;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Models\StockMovementBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
            'season_start_date' => ['nullable', 'date'],
            'season_end_date' => ['nullable', 'date', 'after_or_equal:season_start_date'],
            'status' => [
                'required',
                Rule::in([
                    ProductBatch::STATUS_ACTIVE,
                    ProductBatch::STATUS_INACTIVE,
                    ProductBatch::STATUS_SEASONAL,
                ]),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $branchProduct = DB::transaction(function () use ($validated, $productBatch, $request) {
            $productBatch = ProductBatch::whereKey($productBatch->id)
                ->lockForUpdate()
                ->firstOrFail();

            $branchProduct = $productBatch->branchProduct()
                ->lockForUpdate()
                ->firstOrFail();

            $previousData = [
                'lot_number' => $productBatch->lot_number,
                'expiration_date' => optional($productBatch->expiration_date)->format('Y-m-d'),
                'supplier' => $productBatch->supplier,
                'received_at' => optional($productBatch->received_at)->format('Y-m-d'),
                'quantity' => (float) $productBatch->quantity,
                'season_start_date' => optional($productBatch->season_start_date)->format('Y-m-d'),
                'season_end_date' => optional($productBatch->season_end_date)->format('Y-m-d'),
                'status' => $productBatch->status,
            ];

            $newData = [
                'lot_number' => $validated['lot_number'] ?? null,
                'expiration_date' => $validated['expiration_date'] ?? null,
                'supplier' => $validated['supplier'] ?? null,
                'received_at' => $validated['received_at'] ?? null,
                'quantity' => (float) $validated['quantity'],
                'season_start_date' => $validated['status'] === ProductBatch::STATUS_SEASONAL
                    ? ($validated['season_start_date'] ?? null)
                    : null,
                'season_end_date' => $validated['status'] === ProductBatch::STATUS_SEASONAL
                    ? ($validated['season_end_date'] ?? null)
                    : null,
                'status' => $validated['status'],
            ];

            $changedFields = collect($newData)
                ->filter(fn($value, $field) => (string) ($previousData[$field] ?? '') !== (string) ($value ?? ''))
                ->keys()
                ->values()
                ->all();

            if (empty($changedFields)) {
                return $branchProduct->fresh([
                    'product.category',
                    'product.barcodes',
                    'batches',
                    'movements.user',
                    'movements.batches.productBatch',
                ]);
            }

            $previousQuantity = $previousData['quantity'];
            $newQuantity = $newData['quantity'];
            $difference = $newQuantity - $previousQuantity;

            $previousStock = (float) ProductBatch::where('branch_product_id', $branchProduct->id)
                ->whereIn('status', [
                    ProductBatch::STATUS_ACTIVE,
                    ProductBatch::STATUS_SEASONAL,
                ])
                ->sum('quantity');

            $productBatch->update($newData);

            $newStock = (float) ProductBatch::where('branch_product_id', $branchProduct->id)
                ->whereIn('status', [
                    ProductBatch::STATUS_ACTIVE,
                    ProductBatch::STATUS_SEASONAL,
                ])
                ->sum('quantity');

            $branchProduct->update([
                'stock' => $newStock,
            ]);

            $movementNotes = $this->buildMovementNotes(
                changedFields: $changedFields,
                userNotes: $validated['notes'] ?? null
            );

            $movement = StockMovement::create([
                'branch_product_id' => $branchProduct->id,
                'user_id' => $request->user()?->id,
                'type' => StockMovement::TYPE_ADJUSTMENT,
                'reason' => StockMovement::REASON_INVENTORY_DIFFERENCE,
                'quantity' => abs($difference),
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'notes' => $movementNotes,
            ]);

            StockMovementBatch::create([
                'stock_movement_id' => $movement->id,
                'product_batch_id' => $productBatch->id,
                'quantity' => abs($difference),
                'previous_batch_quantity' => $previousQuantity,
                'new_batch_quantity' => $newQuantity,
                'allocation_method' => StockMovementBatch::ALLOCATION_MANUAL,
            ]);

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

    private function buildMovementNotes(array $changedFields, ?string $userNotes = null): string
    {
        $labels = [
            'lot_number' => 'número de lote',
            'expiration_date' => 'fecha de caducidad',
            'supplier' => 'proveedor',
            'received_at' => 'fecha de ingreso',
            'quantity' => 'cantidad',
            'season_start_date' => 'inicio de temporada',
            'season_end_date' => 'fin de temporada',
            'status' => 'estado',
        ];

        $changedLabels = collect($changedFields)
            ->map(fn($field) => $labels[$field] ?? $field)
            ->implode(', ');

        $baseNote = "Ajuste manual de lote. Campos modificados: {$changedLabels}.";

        if ($userNotes) {
            return "{$baseNote} Nota: {$userNotes}";
        }

        return $baseNote;
    }
}