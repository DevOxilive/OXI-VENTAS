<?php

namespace App\Services;

use App\Events\InventoryStockUpdated;
use App\Models\BranchProduct;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Models\StockMovementBatch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockMovementService
{
    public function move(
        BranchProduct $branchProduct,
        string $type,
        string $reason,
        int $quantity,
        ?string $notes = null,
        ?int $userId = null,
        array $batches = [],
        string $batchAllocationMethod = 'UNKNOWN',
        array $manualBatches = []
    ): StockMovement {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a 0.');
        }

        return DB::transaction(function () use (
            $branchProduct,
            $type,
            $reason,
            $quantity,
            $notes,
            $userId,
            $batches,
            $batchAllocationMethod,
            $manualBatches
        ) {
            $branchProduct = BranchProduct::whereKey($branchProduct->id)
                ->lockForUpdate()
                ->firstOrFail();

            $previousStock = (int) $branchProduct->stock;
            $tracksBatches = (bool) $branchProduct->tracks_batches;

            $newStock = match ($type) {
                'IN' => $previousStock + $quantity,
                'OUT' => $previousStock - $quantity,
                'ADJUSTMENT' => $quantity,
                default => throw new InvalidArgumentException('Tipo de movimiento inválido.'),
            };

            if ($newStock < 0) {
                throw new InvalidArgumentException('No hay stock suficiente para realizar este movimiento.');
            }

            $branchProduct->update([
                'stock' => $newStock,
            ]);

            $movement = StockMovement::create([
                'branch_product_id' => $branchProduct->id,
                'type' => $type,
                'reason' => $reason,
                'quantity' => $quantity,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'user_id' => $userId ?? Auth::id(),
                'notes' => $notes,
            ]);

            if ($type === 'IN') {

                if (! $tracksBatches) {
                    $branchProduct->update([
                        'tracks_batches' => true,
                        'tracks_expiration' => true,
                    ]);

                    $tracksBatches = true;
                }

                /**
                 * Si no se registraron lotes manualmente,
                 * creamos un batch técnico sin lote.
                 */
                if (count($batches) === 0) {

                    $batches = [
                        [
                            'lot_number' => null,
                            'expiration_date' => null,
                            'quantity' => $quantity,
                            'supplier' => null,
                        ],
                    ];
                }

                $this->createIncomingBatches(
                    movement: $movement,
                    branchProduct: $branchProduct,
                    batches: $batches,
                    expectedQuantity: $quantity
                );
            }

            if ($tracksBatches && $type === 'OUT') {
                if ($batchAllocationMethod === 'MANUAL') {
                    $this->consumeManualBatches(
                        movement: $movement,
                        branchProduct: $branchProduct,
                        manualBatches: $manualBatches,
                        expectedQuantity: $quantity
                    );
                } else {
                    $this->consumeBatchesByFefo(
                        movement: $movement,
                        branchProduct: $branchProduct,
                        quantity: $quantity
                    );
                }
            }

            $branchProduct->refresh();

            event(new InventoryStockUpdated($branchProduct));

            return $movement;
        });
    }

    private function createIncomingBatches(
        StockMovement $movement,
        BranchProduct $branchProduct,
        array $batches,
        int $expectedQuantity
    ): void {
        $totalBatchQuantity = collect($batches)
            ->sum(fn($batch) => (int) $batch['quantity']);

        if ($totalBatchQuantity !== $expectedQuantity) {
            throw new InvalidArgumentException(
                'La suma de los lotes debe coincidir con la cantidad total.'
            );
        }

        foreach ($batches as $batch) {
            $batchQuantity = (int) $batch['quantity'];

            $productBatch = ProductBatch::create([
                'branch_product_id' => $branchProduct->id,
                'lot_number' => $batch['lot_number'] ?? null,
                'expiration_date' => $batch['expiration_date'] ?? null,
                'initial_quantity' => $batchQuantity,
                'quantity' => $batchQuantity,
                'supplier' => $batch['supplier'] ?? null,
                'received_at' => now()->toDateString(),
                'status' => 'ACTIVE',
            ]);

            StockMovementBatch::create([
                'stock_movement_id' => $movement->id,
                'product_batch_id' => $productBatch->id,
                'quantity' => $batchQuantity,
                'previous_batch_quantity' => 0,
                'new_batch_quantity' => $batchQuantity,
                'allocation_method' => 'MANUAL',
            ]);
        }
    }

    private function consumeBatchesByFefo(
        StockMovement $movement,
        BranchProduct $branchProduct,
        int $quantity
    ): void {
        $remainingQuantity = $quantity;

        $batches = $branchProduct->activeBatches()
            ->orderByRaw('expiration_date IS NULL')
            ->orderBy('expiration_date')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $previousBatchQuantity = (int) $batch->quantity;
            $quantityToConsume = min($previousBatchQuantity, $remainingQuantity);
            $newBatchQuantity = $previousBatchQuantity - $quantityToConsume;

            $batch->update([
                'quantity' => $newBatchQuantity,
                'status' => $newBatchQuantity <= 0 ? 'DEPLETED' : 'ACTIVE',
            ]);

            StockMovementBatch::create([
                'stock_movement_id' => $movement->id,
                'product_batch_id' => $batch->id,
                'quantity' => $quantityToConsume,
                'previous_batch_quantity' => $previousBatchQuantity,
                'new_batch_quantity' => $newBatchQuantity,
                'allocation_method' => 'FEFO_AUTO',
            ]);

            $remainingQuantity -= $quantityToConsume;
        }

        if ($remainingQuantity > 0) {
            throw new InvalidArgumentException('No hay suficiente stock disponible por lotes.');
        }
    }

    private function consumeManualBatches(
        StockMovement $movement,
        BranchProduct $branchProduct,
        array $manualBatches,
        int $expectedQuantity
    ): void {
        if (count($manualBatches) === 0) {
            throw new InvalidArgumentException(
                'Debes seleccionar al menos un lote para la salida manual.'
            );
        }

        $totalManualQuantity = collect($manualBatches)
            ->sum(fn($batch) => (int) $batch['quantity']);

        if ($totalManualQuantity !== $expectedQuantity) {
            throw new InvalidArgumentException(
                'La suma manual de lotes debe coincidir con la cantidad total.'
            );
        }

        foreach ($manualBatches as $manualBatch) {
            $batch = ProductBatch::whereKey($manualBatch['product_batch_id'])
                ->where('branch_product_id', $branchProduct->id)
                ->lockForUpdate()
                ->first();

            if (! $batch) {
                throw new InvalidArgumentException(
                    'Uno de los lotes seleccionados no pertenece a este producto de sucursal.'
                );
            }

            if ($batch->status !== 'ACTIVE' || (int) $batch->quantity <= 0) {
                throw new InvalidArgumentException(
                    'Uno de los lotes seleccionados no está disponible.'
                );
            }

            $quantityToConsume = (int) $manualBatch['quantity'];
            $previousBatchQuantity = (int) $batch->quantity;

            if ($quantityToConsume > $previousBatchQuantity) {
                throw new InvalidArgumentException(
                    'La cantidad excede el stock disponible del lote.'
                );
            }

            $newBatchQuantity = $previousBatchQuantity - $quantityToConsume;

            $batch->update([
                'quantity' => $newBatchQuantity,
                'status' => $newBatchQuantity <= 0 ? 'DEPLETED' : 'ACTIVE',
            ]);

            StockMovementBatch::create([
                'stock_movement_id' => $movement->id,
                'product_batch_id' => $batch->id,
                'quantity' => $quantityToConsume,
                'previous_batch_quantity' => $previousBatchQuantity,
                'new_batch_quantity' => $newBatchQuantity,
                'allocation_method' => 'MANUAL',
            ]);
        }
    }
}
