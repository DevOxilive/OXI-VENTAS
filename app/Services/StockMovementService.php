<?php

namespace App\Services;

use App\Events\InventoryStockUpdated;
use App\Events\RealtimeActivityLogged;
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
        float $quantity,
        ?string $notes = null,
        ?int $userId = null,
        array $batches = [],
        string $batchAllocationMethod = StockMovementBatch::ALLOCATION_MANUAL,
        array $manualBatches = []
    ): StockMovement {
        if ((float) $quantity === 0.0) {
            throw new InvalidArgumentException('La cantidad no puede ser 0.');
        }

        $movement = DB::transaction(function () use (
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
            return $this->performMovement(
                branchProduct: $branchProduct,
                type: $type,
                reason: $reason,
                quantity: $quantity,
                notes: $notes,
                userId: $userId,
                batches: $batches,
                batchAllocationMethod: $batchAllocationMethod,
                manualBatches: $manualBatches,
            );
        });

        $this->broadcastInventoryRefresh($branchProduct->id, $type);

        return $movement;
    }

    public function distributeIncoming(
        BranchProduct $sourceBranchProduct,
        string $reason,
        float $quantity,
        ?string $notes = null,
        ?int $userId = null,
        array $batch = [],
        array $branchAllocations = []
    ): array {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a 0.');
        }

        $normalizedAllocations = collect($branchAllocations)
            ->map(function ($allocation) {
                return [
                    'branch_id' => (int) ($allocation['branch_id'] ?? 0),
                    'quantity' => (float) ($allocation['quantity'] ?? 0),
                ];
            })
            ->filter(fn ($allocation) => $allocation['branch_id'] > 0 && $allocation['quantity'] > 0)
            ->values();

        if ($normalizedAllocations->isEmpty()) {
            throw new InvalidArgumentException('Debes seleccionar al menos una sucursal para la entrada.');
        }

        if (round($normalizedAllocations->sum('quantity'), 3) !== round($quantity, 3)) {
            throw new InvalidArgumentException('La suma asignada a sucursales debe coincidir con la cantidad total.');
        }

        $movements = DB::transaction(function () use (
            $sourceBranchProduct,
            $reason,
            $notes,
            $userId,
            $batch,
            $normalizedAllocations
        ) {
            $sourceBranchProduct = BranchProduct::whereKey($sourceBranchProduct->id)
                ->lockForUpdate()
                ->firstOrFail();

            return $normalizedAllocations
                ->map(function ($allocation) use ($sourceBranchProduct, $reason, $notes, $userId, $batch) {
                    $targetBranchProduct = $this->resolveTargetBranchProduct(
                        sourceBranchProduct: $sourceBranchProduct,
                        targetBranchId: $allocation['branch_id'],
                    );

                    $movement = $this->performMovement(
                        branchProduct: $targetBranchProduct,
                        type: StockMovement::TYPE_IN,
                        reason: $reason,
                        quantity: $allocation['quantity'],
                        notes: $notes,
                        userId: $userId,
                        batches: [[
                            'lot_number' => $batch['lot_number'] ?? null,
                            'expiration_date' => $batch['expiration_date'] ?? null,
                            'received_at' => $batch['received_at'] ?? null,
                            'supplier' => $batch['supplier'] ?? null,
                            'quantity' => $allocation['quantity'],
                        ]],
                    );

                    return [
                        'branch_id' => $targetBranchProduct->branch_id,
                        'branch_product_id' => $targetBranchProduct->id,
                        'movement_id' => $movement->id,
                        'quantity' => $allocation['quantity'],
                    ];
                })
                ->all();
        });

        collect($movements)
            ->pluck('branch_product_id')
            ->filter()
            ->unique()
            ->each(fn ($branchProductId) => $this->broadcastInventoryRefresh(
                $branchProductId,
                StockMovement::TYPE_IN,
            ));

        return $movements;
    }

    private function activityActionLabel(string $type): string
    {
        return match ($type) {
            StockMovement::TYPE_IN => 'registro una entrada de',
            StockMovement::TYPE_OUT => 'registro una salida de',
            StockMovement::TYPE_ADJUSTMENT => 'ajusto',
            default => 'actualizo',
        };
    }

    private function validateMovement(string $type, string $reason, float $quantity): void
    {
        $validTypes = [
            StockMovement::TYPE_IN,
            StockMovement::TYPE_OUT,
            StockMovement::TYPE_ADJUSTMENT,
        ];

        if (!in_array($type, $validTypes, true)) {
            throw new InvalidArgumentException('Tipo de movimiento invalido.');
        }

        $validReasonsByType = [
            StockMovement::TYPE_IN => [
                StockMovement::REASON_PURCHASE,
            ],
            StockMovement::TYPE_OUT => [
                StockMovement::REASON_SALE,
                StockMovement::REASON_DAMAGED,
                StockMovement::REASON_EXPIRED,
                StockMovement::REASON_OTHER,
            ],
            StockMovement::TYPE_ADJUSTMENT => [
                StockMovement::REASON_INVENTORY_DIFFERENCE,
            ],
        ];

        if (!in_array($reason, $validReasonsByType[$type], true)) {
            throw new InvalidArgumentException('Motivo de movimiento invalido para este tipo.');
        }

        if ($type !== StockMovement::TYPE_ADJUSTMENT && $quantity <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a 0.');
        }
    }

    private function ensureBatchTrackingForIncomingMovement(
        BranchProduct $branchProduct,
        array $batches
    ): void {
        if (count($batches) === 0) {
            throw new InvalidArgumentException('Debes registrar al menos un lote para la entrada.');
        }

        foreach ($batches as $batch) {
            if (empty($batch['lot_number'])) {
                throw new InvalidArgumentException('El numero de lote es obligatorio para la entrada.');
            }

            if (empty($batch['expiration_date'])) {
                throw new InvalidArgumentException('La caducidad es obligatoria para la entrada.');
            }

            if (empty($batch['received_at'])) {
                throw new InvalidArgumentException('La fecha de ingreso es obligatoria para la entrada.');
            }
        }

        $hasExpirationDate = collect($batches)
            ->contains(fn ($batch) => !empty($batch['expiration_date']));

        $branchProduct->update([
            'tracks_batches' => true,
            'tracks_expiration' => (bool) $branchProduct->tracks_expiration || $hasExpirationDate,
        ]);

        $branchProduct->refresh();
    }

    private function createIncomingBatches(
        StockMovement $movement,
        BranchProduct $branchProduct,
        array $batches,
        float $expectedQuantity
    ): void {
        $totalBatchQuantity = collect($batches)
            ->sum(fn ($batch) => (float) ($batch['quantity'] ?? 0));

        if (round($totalBatchQuantity, 3) !== round($expectedQuantity, 3)) {
            throw new InvalidArgumentException('La suma de las entradas/lotes debe coincidir con la cantidad total.');
        }

        foreach ($batches as $batch) {
            $batchQuantity = (float) ($batch['quantity'] ?? 0);

            if ($batchQuantity <= 0) {
                throw new InvalidArgumentException('La cantidad por entrada/lote debe ser mayor a 0.');
            }

            $productBatch = ProductBatch::create([
                'branch_product_id' => $branchProduct->id,
                'lot_number' => $batch['lot_number'] ?? null,
                'expiration_date' => $batch['expiration_date'] ?? null,
                'initial_quantity' => $batchQuantity,
                'quantity' => $batchQuantity,
                'supplier' => $batch['supplier'] ?? null,
                'received_at' => $batch['received_at'],
                'status' => ProductBatch::STATUS_ACTIVE,
            ]);

            StockMovementBatch::create([
                'stock_movement_id' => $movement->id,
                'product_batch_id' => $productBatch->id,
                'quantity' => $batchQuantity,
                'previous_batch_quantity' => 0,
                'new_batch_quantity' => $batchQuantity,
                'allocation_method' => StockMovementBatch::ALLOCATION_MANUAL,
            ]);
        }
    }

    private function consumeManualBatches(
        StockMovement $movement,
        BranchProduct $branchProduct,
        array $manualBatches,
        float $expectedQuantity
    ): void {
        $totalManualQuantity = collect($manualBatches)
            ->sum(fn ($batch) => (float) ($batch['quantity'] ?? 0));

        if (round($totalManualQuantity, 3) !== round($expectedQuantity, 3)) {
            throw new InvalidArgumentException('La suma manual de entradas/lotes debe coincidir con la cantidad total.');
        }

        foreach ($manualBatches as $manualBatch) {
            $batch = ProductBatch::whereKey($manualBatch['id'] ?? null)
                ->where('branch_product_id', $branchProduct->id)
                ->lockForUpdate()
                ->firstOrFail();

            $quantityToConsume = (float) ($manualBatch['quantity'] ?? 0);

            if ($quantityToConsume <= 0) {
                throw new InvalidArgumentException('La cantidad por entrada/lote debe ser mayor a 0.');
            }

            if ((float) $batch->quantity < $quantityToConsume) {
                throw new InvalidArgumentException('No hay suficiente stock en una de las entradas/lotes seleccionadas.');
            }

            $previousBatchQuantity = (float) $batch->quantity;
            $newBatchQuantity = $previousBatchQuantity - $quantityToConsume;

            $batch->update([
                'quantity' => $newBatchQuantity,
            ]);

            StockMovementBatch::create([
                'stock_movement_id' => $movement->id,
                'product_batch_id' => $batch->id,
                'quantity' => $quantityToConsume,
                'previous_batch_quantity' => $previousBatchQuantity,
                'new_batch_quantity' => $newBatchQuantity,
                'allocation_method' => StockMovementBatch::ALLOCATION_MANUAL,
            ]);
        }
    }

    private function applyBatchAdjustment(
        StockMovement $movement,
        BranchProduct $branchProduct,
        float $quantity,
        array $manualBatches
    ): void {
        if ($quantity < 0) {
            $this->consumeManualBatches(
                movement: $movement,
                branchProduct: $branchProduct,
                manualBatches: $manualBatches,
                expectedQuantity: abs($quantity)
            );

            return;
        }

        if ($quantity > 0) {
            $this->createIncomingBatches(
                movement: $movement,
                branchProduct: $branchProduct,
                batches: count($manualBatches) > 0
                    ? $manualBatches
                    : [[
                        'lot_number' => null,
                        'expiration_date' => null,
                        'quantity' => $quantity,
                        'supplier' => null,
                    ]],
                expectedQuantity: $quantity
            );
        }
    }

    private function getCurrentBranchProductStock(BranchProduct $branchProduct, bool $syncStoredStock = true): float
    {
        $batchQuery = ProductBatch::where('branch_product_id', $branchProduct->id)
            ->whereIn('status', [
                ProductBatch::STATUS_ACTIVE,
                ProductBatch::STATUS_SEASONAL,
            ])
            ->where('quantity', '>', 0);

        $hasTrackedInventory = (bool) $branchProduct->tracks_batches
            || ProductBatch::where('branch_product_id', $branchProduct->id)->exists();

        $stock = $hasTrackedInventory
            ? (float) $batchQuery->sum('quantity')
            : (float) $branchProduct->stock;

        if ($syncStoredStock && (float) $branchProduct->stock !== $stock) {
            $branchProduct->update([
                'stock' => $stock,
            ]);
        }

        return $stock;
    }

    private function performMovement(
        BranchProduct $branchProduct,
        string $type,
        string $reason,
        float $quantity,
        ?string $notes = null,
        ?int $userId = null,
        array $batches = [],
        string $batchAllocationMethod = StockMovementBatch::ALLOCATION_MANUAL,
        array $manualBatches = []
    ): StockMovement {
        $branchProduct = BranchProduct::whereKey($branchProduct->id)
            ->lockForUpdate()
            ->firstOrFail();

        $this->validateMovement($type, $reason, $quantity);

        $previousStock = $this->getCurrentBranchProductStock($branchProduct);

        if ($type === StockMovement::TYPE_IN) {
            $this->ensureBatchTrackingForIncomingMovement($branchProduct, $batches);

            if (count($batches) === 0) {
                $batches = [[
                    'lot_number' => null,
                    'expiration_date' => null,
                    'quantity' => $quantity,
                    'supplier' => null,
                ]];
            }
        }

        if ($type === StockMovement::TYPE_OUT) {
            if ($batchAllocationMethod !== StockMovementBatch::ALLOCATION_MANUAL) {
                throw new InvalidArgumentException('La salida debe indicar seleccion manual de entradas/lotes.');
            }

            if (count($manualBatches) === 0) {
                throw new InvalidArgumentException('Debes seleccionar al menos una entrada/lote para la salida.');
            }
        }

        if ($type === StockMovement::TYPE_ADJUSTMENT && $quantity < 0 && count($manualBatches) === 0) {
            throw new InvalidArgumentException('Debes seleccionar al menos una entrada/lote para el ajuste negativo.');
        }

        $projectedStock = match ($type) {
            StockMovement::TYPE_IN => $previousStock + $quantity,
            StockMovement::TYPE_OUT => $previousStock - $quantity,
            StockMovement::TYPE_ADJUSTMENT => $previousStock + $quantity,
            default => throw new InvalidArgumentException('Tipo de movimiento invalido.'),
        };

        if ($projectedStock < 0) {
            throw new InvalidArgumentException('No hay stock suficiente para realizar este movimiento.');
        }

        $movement = StockMovement::create([
            'branch_product_id' => $branchProduct->id,
            'type' => $type,
            'reason' => $reason,
            'quantity' => $quantity,
            'previous_stock' => $previousStock,
            'new_stock' => $projectedStock,
            'user_id' => $userId ?? Auth::id(),
            'notes' => $notes,
        ]);

        if ($type === StockMovement::TYPE_IN) {
            $this->createIncomingBatches(
                movement: $movement,
                branchProduct: $branchProduct,
                batches: $batches,
                expectedQuantity: $quantity
            );
        }

        if ($type === StockMovement::TYPE_OUT) {
            $this->consumeManualBatches(
                movement: $movement,
                branchProduct: $branchProduct,
                manualBatches: $manualBatches,
                expectedQuantity: $quantity
            );
        }

        if ($type === StockMovement::TYPE_ADJUSTMENT) {
            $this->applyBatchAdjustment(
                movement: $movement,
                branchProduct: $branchProduct,
                quantity: $quantity,
                manualBatches: $manualBatches
            );
        }

        $newStock = $this->getCurrentBranchProductStock($branchProduct);

        $movement->update([
            'new_stock' => $newStock,
        ]);

        if ($type === StockMovement::TYPE_IN && $reason === StockMovement::REASON_PURCHASE) {
            $branchProduct->update([
                'last_restocked_at' => now(),
            ]);
        }

        return $movement;
    }

    private function broadcastInventoryRefresh(
        int $branchProductId,
        string $movementType,
    ): void
    {
        $branchProduct = BranchProduct::with('product:id,name')
            ->find($branchProductId);

        if (!$branchProduct) {
            return;
        }

        event(new InventoryStockUpdated($branchProduct));
        event(RealtimeActivityLogged::message(
            $this->activityActionLabel($movementType),
            'stock del producto',
            $branchProduct->product?->name,
            'Inventario',
            $movementType,
        ));
    }

    private function resolveTargetBranchProduct(
        BranchProduct $sourceBranchProduct,
        int $targetBranchId
    ): BranchProduct {
        BranchProduct::updateOrCreate(
            [
                'branch_id' => $targetBranchId,
                'product_id' => $sourceBranchProduct->product_id,
            ],
            [
                'stock' => 0,
                'min_stock' => $sourceBranchProduct->min_stock ?? 0,
                'status' => $sourceBranchProduct->status ?? BranchProduct::STATUS_ACTIVE,
                'tracks_batches' => (bool) $sourceBranchProduct->tracks_batches,
                'tracks_expiration' => (bool) $sourceBranchProduct->tracks_expiration,
                'season_start_date' => $sourceBranchProduct->season_start_date,
                'season_end_date' => $sourceBranchProduct->season_end_date,
                'inactive_candidate_after_days' => $sourceBranchProduct->inactive_candidate_after_days ?? 90,
            ]
        );

        return BranchProduct::where('branch_id', $targetBranchId)
            ->where('product_id', $sourceBranchProduct->product_id)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
