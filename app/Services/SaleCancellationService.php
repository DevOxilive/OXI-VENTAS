<?php

namespace App\Services;

use App\Events\InventoryStockUpdated;
use App\Events\RealtimeActivityLogged;
use App\Models\BranchProduct;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleCancellation;
use App\Models\SaleCancellationDetail;
use App\Models\SaleDetail;
use App\Models\StockMovement;
use App\Models\StockMovementBatch;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class SaleCancellationService
{
    public function cancel(Sale $sale, User $user, string $reason): SaleCancellation
    {
        return DB::transaction(function () use ($sale, $user, $reason) {
            $sale = Sale::query()
                ->with([
                    'details.product',
                    'details.stockMovements.batches',
                    'cancellation',
                ])
                ->whereKey($sale->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($sale->status === 'cancelled' || $sale->cancellation) {
                throw ValidationException::withMessages([
                    'sale' => 'Esta venta ya tiene una cancelacion registrada.',
                ]);
            }

            if ($sale->status !== 'completed') {
                throw ValidationException::withMessages([
                    'sale' => 'Solo se pueden cancelar ventas completadas.',
                ]);
            }

            if ($sale->details->isEmpty()) {
                throw ValidationException::withMessages([
                    'sale' => 'La venta no tiene productos para devolver.',
                ]);
            }

            $cancellation = SaleCancellation::create([
                'sale_id' => $sale->id,
                'branch_id' => $sale->branch_id,
                'payment_method_id' => $sale->payment_method_id,
                'cancelled_by_user_id' => $user->id,
                'cash_box_number' => (string) ($sale->cash_box_number ?: '1'),
                'amount' => $sale->total,
                'reason' => $reason,
                'cancelled_at' => now(),
            ]);

            foreach ($sale->details as $detail) {
                $this->returnSaleDetail($sale, $detail, $cancellation, $user);
            }

            $sale->update([
                'status' => 'cancelled',
                'cancelled_at' => $cancellation->cancelled_at,
            ]);

            $cancellation->load([
                'sale',
                'details.product',
                'details.returnStockMovement.batches.productBatch',
                'cancelledBy:id,name',
            ]);

            return $cancellation;
        }, 3);
    }

    private function returnSaleDetail(Sale $sale, SaleDetail $detail, SaleCancellation $cancellation, User $user): void
    {
        $branchProduct = BranchProduct::query()
            ->where('branch_id', $sale->branch_id)
            ->where('product_id', $detail->product_id)
            ->lockForUpdate()
            ->first();

        if (! $branchProduct) {
            throw ValidationException::withMessages([
                'sale' => 'No se encontro el producto de la venta en la sucursal original.',
            ]);
        }

        $baseQuantity = (float) ($detail->base_quantity ?? $detail->quantity);
        $cancellationDetail = SaleCancellationDetail::create([
            'sale_cancellation_id' => $cancellation->id,
            'sale_detail_id' => $detail->id,
            'branch_product_id' => $branchProduct->id,
            'product_id' => $detail->product_id,
            'barcode_id' => $detail->barcode_id,
            'quantity' => $detail->quantity,
            'sale_unit' => $detail->sale_unit,
            'base_quantity' => $baseQuantity,
            'pieces_per_box' => $detail->pieces_per_box,
            'unit_price' => $detail->unit_price,
            'subtotal' => $detail->subtotal,
        ]);

        $originalMovements = $detail->stockMovements
            ->where('type', StockMovement::TYPE_OUT)
            ->where('reason', StockMovement::REASON_SALE)
            ->values();

        if ($originalMovements->isEmpty()) {
            $originalMovements = $this->resolveLegacySaleMovements(
                sale: $sale,
                detail: $detail,
                branchProduct: $branchProduct,
                quantity: $baseQuantity,
            );
        }

        $returnMovement = $this->createReturnMovement(
            sale: $sale,
            detail: $detail,
            cancellation: $cancellation,
            cancellationDetail: $cancellationDetail,
            branchProduct: $branchProduct,
            originalMovements: $originalMovements,
            quantity: $baseQuantity,
            user: $user,
        );

        $cancellationDetail->update([
            'return_stock_movement_id' => $returnMovement->id,
        ]);

        $this->broadcastInventoryRefresh($branchProduct);
    }

    private function resolveLegacySaleMovements(
        Sale $sale,
        SaleDetail $detail,
        BranchProduct $branchProduct,
        float $quantity
    ): Collection {
        if (! $sale->date) {
            return collect();
        }

        $movement = StockMovement::query()
            ->with('batches')
            ->where('branch_product_id', $branchProduct->id)
            ->where('type', StockMovement::TYPE_OUT)
            ->where('reason', StockMovement::REASON_SALE)
            ->whereNull('sale_id')
            ->whereNull('sale_detail_id')
            ->where('quantity', $quantity)
            ->whereBetween('created_at', [
                $sale->date->copy()->subMinutes(5),
                $sale->date->copy()->addMinutes(5),
            ])
            ->orderByRaw('ABS(TIMESTAMPDIFF(SECOND, created_at, ?))', [$sale->date])
            ->lockForUpdate()
            ->first();

        if (! $movement) {
            return collect();
        }

        $movement->update([
            'sale_id' => $sale->id,
            'sale_detail_id' => $detail->id,
        ]);

        return collect([$movement->fresh('batches')]);
    }

    private function createReturnMovement(
        Sale $sale,
        SaleDetail $detail,
        SaleCancellation $cancellation,
        SaleCancellationDetail $cancellationDetail,
        BranchProduct $branchProduct,
        $originalMovements,
        float $quantity,
        User $user
    ): StockMovement {
        $previousStock = (float) $branchProduct->stock;
        $newStock = $previousStock + $quantity;
        $hasBatchEvidence = $originalMovements
            ->flatMap(fn (StockMovement $movement) => $movement->batches)
            ->isNotEmpty();
        $requiresBatchEvidence = (bool) $branchProduct->tracks_batches
            || ProductBatch::query()->where('branch_product_id', $branchProduct->id)->exists();

        if ($requiresBatchEvidence && ! $hasBatchEvidence) {
            throw ValidationException::withMessages([
                'sale' => 'La venta no tiene lotes vinculados para regresar el inventario automaticamente.',
            ]);
        }

        $movement = StockMovement::create([
            'branch_product_id' => $branchProduct->id,
            'sale_id' => $sale->id,
            'sale_detail_id' => $detail->id,
            'sale_cancellation_id' => $cancellation->id,
            'sale_cancellation_detail_id' => $cancellationDetail->id,
            'type' => StockMovement::TYPE_IN,
            'reason' => StockMovement::REASON_RETURN,
            'quantity' => $quantity,
            'unit_cost' => $detail->unit_cost,
            'previous_stock' => $previousStock,
            'new_stock' => $newStock,
            'user_id' => $user->id,
            'notes' => 'Devolucion por cancelacion de ticket '.$sale->folio,
        ]);

        $returnedFromBatches = false;

        foreach ($originalMovements as $originalMovement) {
            foreach ($originalMovement->batches as $originalBatchMovement) {
                $productBatch = ProductBatch::query()
                    ->whereKey($originalBatchMovement->product_batch_id)
                    ->where('branch_product_id', $branchProduct->id)
                    ->lockForUpdate()
                    ->first();

                if (! $productBatch) {
                    throw ValidationException::withMessages([
                        'sale' => 'No se encontro uno de los lotes originales de la venta.',
                    ]);
                }

                $batchQuantity = (float) $originalBatchMovement->quantity;
                $previousBatchQuantity = (float) $productBatch->quantity;
                $newBatchQuantity = $previousBatchQuantity + $batchQuantity;

                $productBatch->update([
                    'quantity' => $newBatchQuantity,
                ]);

                StockMovementBatch::create([
                    'stock_movement_id' => $movement->id,
                    'product_batch_id' => $productBatch->id,
                    'quantity' => $batchQuantity,
                    'previous_batch_quantity' => $previousBatchQuantity,
                    'new_batch_quantity' => $newBatchQuantity,
                    'allocation_method' => StockMovementBatch::ALLOCATION_MANUAL,
                ]);

                $returnedFromBatches = true;
            }
        }

        $branchProduct->update([
            'stock' => $newStock,
        ]);

        if ($returnedFromBatches) {
            $movement->update([
                'new_stock' => (float) ProductBatch::query()
                    ->where('branch_product_id', $branchProduct->id)
                    ->whereIn('status', [
                        ProductBatch::STATUS_ACTIVE,
                        ProductBatch::STATUS_SEASONAL,
                    ])
                    ->sum('quantity'),
            ]);

            $branchProduct->update([
                'stock' => $movement->new_stock,
            ]);
        }

        return $movement;
    }

    private function broadcastInventoryRefresh(BranchProduct $branchProduct): void
    {
        try {
            event(new InventoryStockUpdated($branchProduct));
            event(RealtimeActivityLogged::message(
                'registro una devolucion de',
                'stock del producto',
                $branchProduct->product?->name,
                'Inventario',
                StockMovement::TYPE_IN,
            ));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
