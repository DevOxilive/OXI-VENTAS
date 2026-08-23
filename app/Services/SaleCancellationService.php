<?php

namespace App\Services;

use App\Events\InventoryStockUpdated;
use App\Events\RealtimeActivityLogged;
use App\Models\BranchProduct;
use App\Models\EmployeeCreditCharge;
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
    /** Registra una devolución parcial o total sin alterar el ticket original. */
    public function cancel(Sale $sale, User $user, string $reason, array $items): SaleCancellation
    {
        return DB::transaction(function () use ($sale, $user, $reason, $items) {
            $sale = Sale::query()->with([
                'details.product', 'details.stockMovements.batches', 'details.cancellationDetails',
            ])->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($sale->status !== 'completed') {
                throw ValidationException::withMessages(['sale' => 'Solo se pueden devolver productos de ventas completadas.']);
            }

            $requestedItems = collect($items)->keyBy(fn (array $item) => (int) $item['sale_detail_id']);
            if ($requestedItems->count() !== count($items)) {
                throw ValidationException::withMessages(['items' => 'Un producto del ticket fue seleccionado más de una vez.']);
            }
            if ($requestedItems->diffKeys($sale->details->keyBy('id'))->isNotEmpty()) {
                throw ValidationException::withMessages(['items' => 'Uno de los productos seleccionados no pertenece a este ticket.']);
            }

            $cancellation = SaleCancellation::create([
                'sale_id' => $sale->id, 'branch_id' => $sale->branch_id,
                'payment_method_id' => $sale->payment_method_id, 'cancelled_by_user_id' => $user->id,
                'cash_box_number' => (string) ($sale->cash_box_number ?: '1'),
                'amount' => 0, 'reason' => $reason, 'cancelled_at' => now(),
            ]);

            $amount = 0;
            foreach ($sale->details as $detail) {
                if ($requested = $requestedItems->get($detail->id)) {
                    $amount += $this->returnSaleDetail($sale, $detail, $cancellation, $user, (float) $requested['quantity']);
                }
            }
            if ($amount <= 0) {
                throw ValidationException::withMessages(['items' => 'Selecciona al menos un producto válido para devolver.']);
            }
            $cancellation->update(['amount' => round($amount, 2)]);

            $sale->load('details.cancellationDetails');
            $allReturned = $sale->details->every(function (SaleDetail $detail) {
                return (float) $detail->cancellationDetails->sum('quantity') + 0.000001 >= (float) $detail->quantity;
            });
            if ($allReturned) {
                $sale->update(['status' => 'cancelled', 'cancelled_at' => $cancellation->cancelled_at]);
            }

            $this->adjustEmployeeCreditForReturn($sale, $amount);

            return $cancellation->fresh(['sale', 'details.product', 'details.returnStockMovement.batches.productBatch', 'cancelledBy:id,name']);
        }, 3);
    }

    private function returnSaleDetail(Sale $sale, SaleDetail $detail, SaleCancellation $cancellation, User $user, float $quantity): float
    {
        $branchProduct = BranchProduct::query()->where('branch_id', $sale->branch_id)->where('product_id', $detail->product_id)->lockForUpdate()->first();
        if (! $branchProduct) {
            throw ValidationException::withMessages(['items' => 'No se encontró el producto '.$detail->product?->name.' en la sucursal original.']);
        }

        $remainingQuantity = (float) $detail->quantity - (float) $detail->cancellationDetails->sum('quantity');
        if ($quantity <= 0 || $quantity - $remainingQuantity > 0.000001) {
            throw ValidationException::withMessages(['items' => 'La cantidad a devolver de '.$detail->product?->name.' excede lo pendiente del ticket.']);
        }
        $isKilogram = strtolower((string) ($detail->product?->inventory_unit ?? $detail->product?->unit ?? 'pza')) === 'kg';
        if (($detail->sale_unit === 'box' || ! $isKilogram) && abs($quantity - round($quantity)) > 0.000001) {
            throw ValidationException::withMessages(['items' => 'Las piezas y cajas deben devolverse en cantidades enteras.']);
        }

        $baseQuantity = $detail->sale_unit === 'box' ? $quantity * (float) $detail->pieces_per_box : $quantity;
        $subtotal = round($quantity * (float) $detail->unit_price, 2);
        $cancellationDetail = SaleCancellationDetail::create([
            'sale_cancellation_id' => $cancellation->id, 'sale_detail_id' => $detail->id,
            'branch_product_id' => $branchProduct->id, 'product_id' => $detail->product_id,
            'barcode_id' => $detail->barcode_id, 'quantity' => $quantity, 'sale_unit' => $detail->sale_unit,
            'base_quantity' => $baseQuantity, 'pieces_per_box' => $detail->pieces_per_box,
            'unit_price' => $detail->unit_price, 'subtotal' => $subtotal,
        ]);

        $originalMovements = $detail->stockMovements->where('type', StockMovement::TYPE_OUT)->where('reason', StockMovement::REASON_SALE)->values();
        if ($originalMovements->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'La venta no tiene el movimiento de inventario vinculado para devolver '.$detail->product?->name.'.']);
        }

        $returnMovement = $this->createReturnMovement($sale, $detail, $cancellation, $cancellationDetail, $branchProduct, $originalMovements, $baseQuantity, $user);
        $cancellationDetail->update(['return_stock_movement_id' => $returnMovement->id]);
        $this->broadcastInventoryRefresh($branchProduct);

        return $subtotal;
    }

    private function createReturnMovement(Sale $sale, SaleDetail $detail, SaleCancellation $cancellation, SaleCancellationDetail $cancellationDetail, BranchProduct $branchProduct, Collection $originalMovements, float $quantity, User $user): StockMovement
    {
        $previousStock = (float) $branchProduct->stock;
        $movement = StockMovement::create([
            'branch_product_id' => $branchProduct->id, 'sale_id' => $sale->id, 'sale_detail_id' => $detail->id,
            'sale_cancellation_id' => $cancellation->id, 'sale_cancellation_detail_id' => $cancellationDetail->id,
            'type' => StockMovement::TYPE_IN, 'reason' => StockMovement::REASON_RETURN, 'quantity' => $quantity,
            'unit_cost' => $detail->unit_cost, 'previous_stock' => $previousStock, 'new_stock' => $previousStock + $quantity,
            'user_id' => $user->id, 'notes' => 'Devolución parcial o total del ticket '.$sale->folio,
        ]);

        $originalBatches = $originalMovements->flatMap(fn (StockMovement $movement) => $movement->batches)->values();
        if ($originalBatches->isNotEmpty()) {
            $this->restoreOriginalBatches($movement, $detail, $branchProduct, $originalBatches, $quantity);
            $movement->update(['new_stock' => (float) ProductBatch::query()->where('branch_product_id', $branchProduct->id)->whereIn('status', [ProductBatch::STATUS_ACTIVE, ProductBatch::STATUS_SEASONAL])->sum('quantity')]);
        }
        $branchProduct->update(['stock' => $movement->new_stock]);

        return $movement;
    }

    private function restoreOriginalBatches(StockMovement $returnMovement, SaleDetail $detail, BranchProduct $branchProduct, Collection $originalBatches, float $quantity): void
    {
        $previousReturns = StockMovementBatch::query()
            ->selectRaw('stock_movement_batches.product_batch_id, COALESCE(SUM(stock_movement_batches.quantity), 0) as returned_quantity')
            ->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_batches.stock_movement_id')
            ->where('stock_movements.sale_detail_id', $detail->id)->where('stock_movements.type', StockMovement::TYPE_IN)
            ->where('stock_movements.reason', StockMovement::REASON_RETURN)->groupBy('stock_movement_batches.product_batch_id')
            ->pluck('returned_quantity', 'stock_movement_batches.product_batch_id');

        $remaining = $quantity;
        foreach ($originalBatches as $originalBatch) {
            if ($remaining <= 0.000001) break;
            $available = max(0, (float) $originalBatch->quantity - (float) ($previousReturns[$originalBatch->product_batch_id] ?? 0));
            $restoreQuantity = min($available, $remaining);
            if ($restoreQuantity <= 0.000001) continue;

            $batch = ProductBatch::query()->whereKey($originalBatch->product_batch_id)->where('branch_product_id', $branchProduct->id)->lockForUpdate()->first();
            if (! $batch) throw ValidationException::withMessages(['items' => 'No se encontró uno de los lotes originales del producto devuelto.']);

            $previousQuantity = (float) $batch->quantity;
            $batch->update(['quantity' => $previousQuantity + $restoreQuantity]);
            StockMovementBatch::create([
                'stock_movement_id' => $returnMovement->id, 'product_batch_id' => $batch->id, 'quantity' => $restoreQuantity,
                'previous_batch_quantity' => $previousQuantity, 'new_batch_quantity' => $previousQuantity + $restoreQuantity,
                'allocation_method' => StockMovementBatch::ALLOCATION_MANUAL,
            ]);
            $remaining -= $restoreQuantity;
        }
        if ($remaining > 0.000001) throw ValidationException::withMessages(['items' => 'La devolución excede las cantidades disponibles en los lotes originales.']);
    }

    private function broadcastInventoryRefresh(BranchProduct $branchProduct): void
    {
        try {
            event(new InventoryStockUpdated($branchProduct));
            event(RealtimeActivityLogged::message('registró una devolución de', 'stock del producto', $branchProduct->product?->name, 'Inventario', StockMovement::TYPE_IN));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function adjustEmployeeCreditForReturn(Sale $sale, float $amount): void
    {
        $charge = EmployeeCreditCharge::query()->where('sale_id', $sale->id)->lockForUpdate()->first();
        if (! $charge) return;

        $outstanding = (float) $charge->outstanding_amount;
        $applied = min($outstanding, $amount);
        $remaining = round($outstanding - $applied, 2);
        $charge->update(['outstanding_amount' => $remaining, 'status' => $remaining <= 0 ? 'paid' : 'open']);
        $creditBalance = round($amount - $applied, 2);
        if ($creditBalance > 0) {
            $charge->account()->lockForUpdate()->first()->increment('credit_balance', $creditBalance);
        }
    }
}
