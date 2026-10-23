<?php

namespace App\Services;

use App\Events\InventoryStockUpdated;
use App\Events\RealtimeActivityLogged;
use App\Events\SaleReturnChanged;
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
    public function __construct(private StockMovementService $stockMovementService) {}

    /** Registra una devolución parcial o total sin alterar el ticket original. */
    public function cancel(Sale $sale, User $user, string $reason, array $items): SaleCancellation
    {
        return DB::transaction(function () use ($sale, $user, $reason, $items) {
            $sale = Sale::query()->with([
                'details.product', 'details.stockMovements.batches', 'details.activeCancellationDetails',
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

            // Se valida y bloquea todo antes de escribir. Así una devolución con
            // varios productos nunca puede quedar registrada a medias.
            $preparedItems = $this->prepareReturnItems($sale, $requestedItems);

            $cancellation = SaleCancellation::create([
                'sale_id' => $sale->id, 'branch_id' => $sale->branch_id,
                'payment_method_id' => $sale->payment_method_id, 'cancelled_by_user_id' => $user->id,
                'cash_box_number' => (string) ($sale->cash_box_number ?: '1'),
                'amount' => 0, 'reason' => $reason, 'cancelled_at' => now(),
            ]);

            $amount = 0;
            foreach ($preparedItems as $preparedItem) {
                $amount += $this->returnSaleDetail(
                    sale: $sale,
                    detail: $preparedItem['detail'],
                    cancellation: $cancellation,
                    user: $user,
                    quantity: $preparedItem['quantity'],
                    baseQuantity: $preparedItem['base_quantity'],
                    subtotal: $preparedItem['subtotal'],
                    branchProduct: $preparedItem['branch_product'],
                    originalMovements: $preparedItem['original_movements'],
                );
            }
            if ($amount <= 0) {
                throw ValidationException::withMessages(['items' => 'Selecciona al menos un producto válido para devolver.']);
            }
            $cancellation->update(['amount' => round($amount, 2)]);

            $sale->load('details.activeCancellationDetails');
            $allReturned = $sale->details->every(function (SaleDetail $detail) {
                return (float) $detail->activeCancellationDetails->sum('quantity') + 0.000001 >= (float) $detail->quantity;
            });
            if ($allReturned) {
                $sale->update(['status' => 'cancelled', 'cancelled_at' => $cancellation->cancelled_at]);
            }

            $financialEffect = $this->adjustEmployeeCreditForReturn($sale, $amount);
            $cancellation->update($financialEffect ?? [
                'application_type' => $this->isCashPayment($sale) ? 'cash_refund' : 'payment_refund',
            ]);

            event(new SaleReturnChanged(
                action: 'registered',
                saleId: (int) $sale->id,
                cancellationId: (int) $cancellation->id,
                branchId: (int) $sale->branch_id,
                amount: (float) $cancellation->amount,
                applicationType: (string) $cancellation->application_type,
                saleStatus: (string) $sale->status,
            ));

            event(RealtimeActivityLogged::message(
                'registró una devolución del',
                'ticket',
                $sale->folio,
                'Ventas',
                'RETURN',
            ));

            return $cancellation->fresh(['sale', 'details.product', 'details.returnStockMovement.batches.productBatch', 'cancelledBy:id,name']);
        }, 3);
    }

    /** Anula una devolución conservando todos sus registros y creando la contrapartida de inventario. */
    public function voidCancellation(Sale $sale, SaleCancellation $cancellation, User $user, string $reason): SaleCancellation
    {
        return DB::transaction(function () use ($sale, $cancellation, $user, $reason) {
            $sale = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $cancellation = SaleCancellation::query()
                ->with(['details.returnStockMovement.batches'])
                ->whereKey($cancellation->id)
                ->where('sale_id', $sale->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($cancellation->isVoided()) {
                throw ValidationException::withMessages(['return' => 'Esta devolución ya fue anulada.']);
            }

            $branchProducts = BranchProduct::query()
                ->whereIn('id', $cancellation->details->pluck('branch_product_id')->filter())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($cancellation->details as $detail) {
                $branchProduct = $branchProducts->get($detail->branch_product_id);
                if (! $branchProduct || ! $detail->returnStockMovement) {
                    throw ValidationException::withMessages(['return' => 'No se encontró el movimiento de inventario de uno de los productos devueltos.']);
                }
                if ($detail->void_stock_movement_id) {
                    throw ValidationException::withMessages(['return' => 'El inventario de esta devolución ya fue revertido.']);
                }

                $manualBatches = $detail->returnStockMovement->batches
                    ->map(fn (StockMovementBatch $batch) => [
                        'id' => (int) $batch->product_batch_id,
                        'quantity' => (float) $batch->quantity,
                    ])
                    ->values()
                    ->all();

                $movement = $this->stockMovementService->move(
                    branchProduct: $branchProduct,
                    type: StockMovement::TYPE_OUT,
                    reason: StockMovement::REASON_OTHER,
                    quantity: (float) $detail->base_quantity,
                    notes: 'Anulación de devolución DEV-'.str_pad((string) $cancellation->id, 6, '0', STR_PAD_LEFT).': '.$reason,
                    userId: $user->id,
                    manualBatches: $manualBatches,
                    saleId: $sale->id,
                    saleDetailId: $detail->sale_detail_id,
                    saleCancellationId: $cancellation->id,
                    saleCancellationDetailId: $detail->id,
                );

                $detail->update(['void_stock_movement_id' => $movement->id]);
            }

            $this->restoreEmployeeCreditForVoidedReturn($sale, $cancellation);

            $cancellation->update([
                'voided_by_user_id' => $user->id,
                'void_reason' => $reason,
                'voided_at' => now(),
            ]);

            $this->recalculateSaleReturnStatus($sale);

            event(new SaleReturnChanged(
                action: 'voided',
                saleId: (int) $sale->id,
                cancellationId: (int) $cancellation->id,
                branchId: (int) $sale->branch_id,
                amount: (float) $cancellation->amount,
                applicationType: (string) $cancellation->application_type,
                saleStatus: (string) $sale->fresh()->status,
            ));

            event(RealtimeActivityLogged::message(
                'anuló la devolución del',
                'ticket',
                $sale->folio,
                'Ventas',
                'RETURN_VOID',
            ));

            return $cancellation->fresh([
                'sale', 'details.product', 'details.returnStockMovement', 'details.voidStockMovement',
                'cancelledBy:id,name', 'voidedBy:id,name',
            ]);
        }, 3);
    }

    private function prepareReturnItems(Sale $sale, Collection $requestedItems): Collection
    {
        $details = $sale->details->keyBy('id');
        $productIds = $requestedItems->keys()
            ->map(fn (int $detailId) => (int) $details->get($detailId)->product_id)
            ->unique()
            ->sort()
            ->values();

        $branchProducts = BranchProduct::query()
            ->where('branch_id', $sale->branch_id)
            ->whereIn('product_id', $productIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('product_id');

        return $requestedItems->map(function (array $requested, int $detailId) use ($details, $branchProducts) {
            /** @var SaleDetail $detail */
            $detail = $details->get($detailId);
            /** @var BranchProduct|null $branchProduct */
            $branchProduct = $branchProducts->get($detail->product_id);
            if (! $branchProduct) {
                throw ValidationException::withMessages(['items' => 'No se encontró el producto '.$detail->product?->name.' en la sucursal original.']);
            }

            $quantity = (float) $requested['quantity'];
            $remainingQuantity = (float) $detail->quantity - (float) $detail->activeCancellationDetails->sum('quantity');
            if ($quantity <= 0 || $quantity - $remainingQuantity > 0.000001) {
                throw ValidationException::withMessages(['items' => 'La cantidad a devolver de '.$detail->product?->name.' excede lo pendiente del ticket.']);
            }

            $isKilogram = strtolower((string) ($detail->product?->inventory_unit ?? $detail->product?->unit ?? 'pza')) === 'kg';
            if (($detail->sale_unit === 'box' || ! $isKilogram) && abs($quantity - round($quantity)) > 0.000001) {
                throw ValidationException::withMessages(['items' => 'Las piezas y cajas deben devolverse en cantidades enteras.']);
            }

            $originalMovements = $detail->stockMovements
                ->where('type', StockMovement::TYPE_OUT)
                ->where('reason', StockMovement::REASON_SALE)
                ->values();
            if ($originalMovements->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'La venta no tiene el movimiento de inventario vinculado para devolver '.$detail->product?->name.'.']);
            }

            return [
                'detail' => $detail,
                'branch_product' => $branchProduct,
                'original_movements' => $originalMovements,
                'quantity' => $quantity,
                'base_quantity' => $detail->sale_unit === 'box'
                    ? $quantity * (float) $detail->pieces_per_box
                    : $quantity,
                'subtotal' => round($quantity * (float) $detail->unit_price, 2),
            ];
        })->values();
    }

    private function returnSaleDetail(
        Sale $sale,
        SaleDetail $detail,
        SaleCancellation $cancellation,
        User $user,
        float $quantity,
        float $baseQuantity,
        float $subtotal,
        BranchProduct $branchProduct,
        Collection $originalMovements,
    ): float {

        $cancellationDetail = SaleCancellationDetail::create([
            'sale_cancellation_id' => $cancellation->id, 'sale_detail_id' => $detail->id,
            'branch_product_id' => $branchProduct->id, 'product_id' => $detail->product_id,
            'barcode_id' => $detail->barcode_id, 'quantity' => $quantity, 'sale_unit' => $detail->sale_unit,
            'base_quantity' => $baseQuantity, 'pieces_per_box' => $detail->pieces_per_box,
            'unit_price' => $detail->unit_price, 'subtotal' => $subtotal,
        ]);

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
        } elseif ($branchProduct->batches()->exists()) {
            // Las ventas antiguas sin lote también deben quedar representadas
            // en los lotes si el producto empezó a manejarlos después de venderse.
            $batch = ProductBatch::create([
                'branch_product_id' => $branchProduct->id,
                'lot_number' => null,
                'initial_quantity' => $quantity,
                'quantity' => $quantity,
                'received_at' => now()->toDateString(),
                'status' => ProductBatch::STATUS_ACTIVE,
                'has_real_lot' => false,
            ]);
            StockMovementBatch::create([
                'stock_movement_id' => $movement->id,
                'product_batch_id' => $batch->id,
                'quantity' => $quantity,
                'previous_batch_quantity' => 0,
                'new_batch_quantity' => $quantity,
                'allocation_method' => StockMovementBatch::ALLOCATION_MANUAL,
            ]);
            $movement->update(['new_stock' => (float) $branchProduct->batches()->whereIn('status', [ProductBatch::STATUS_ACTIVE, ProductBatch::STATUS_SEASONAL])->sum('quantity')]);
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
            if ($remaining <= 0.000001) {
                break;
            }
            $available = max(0, (float) $originalBatch->quantity - (float) ($previousReturns[$originalBatch->product_batch_id] ?? 0));
            $restoreQuantity = min($available, $remaining);
            if ($restoreQuantity <= 0.000001) {
                continue;
            }

            $batch = ProductBatch::query()->whereKey($originalBatch->product_batch_id)->where('branch_product_id', $branchProduct->id)->lockForUpdate()->first();
            if (! $batch) {
                throw ValidationException::withMessages(['items' => 'No se encontró uno de los lotes originales del producto devuelto.']);
            }

            $previousQuantity = (float) $batch->quantity;
            $batch->update(['quantity' => $previousQuantity + $restoreQuantity]);
            StockMovementBatch::create([
                'stock_movement_id' => $returnMovement->id, 'product_batch_id' => $batch->id, 'quantity' => $restoreQuantity,
                'previous_batch_quantity' => $previousQuantity, 'new_batch_quantity' => $previousQuantity + $restoreQuantity,
                'allocation_method' => StockMovementBatch::ALLOCATION_MANUAL,
            ]);
            $remaining -= $restoreQuantity;
        }
        if ($remaining > 0.000001) {
            throw ValidationException::withMessages(['items' => 'La devolución excede las cantidades disponibles en los lotes originales.']);
        }
    }

    private function broadcastInventoryRefresh(BranchProduct $branchProduct): void
    {
        try {
            event(new InventoryStockUpdated($branchProduct));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function adjustEmployeeCreditForReturn(Sale $sale, float $amount): ?array
    {
        $charge = EmployeeCreditCharge::query()->where('sale_id', $sale->id)->lockForUpdate()->first();
        if (! $charge) {
            return null;
        }

        $account = $charge->account()->lockForUpdate()->firstOrFail();
        $balanceBefore = max(0, (float) $account->charges()
            ->where('status', 'open')
            ->sum('outstanding_amount') - (float) $account->credit_balance);

        $outstanding = (float) $charge->outstanding_amount;
        $applied = min($outstanding, $amount);
        $remaining = round($outstanding - $applied, 2);
        $charge->update(['outstanding_amount' => $remaining, 'status' => $remaining <= 0 ? 'paid' : 'open']);
        $creditBalance = round($amount - $applied, 2);
        if ($creditBalance > 0) {
            $account->increment('credit_balance', $creditBalance);
        }

        return [
            'application_type' => 'account_adjustment',
            'account_balance_before' => round($balanceBefore, 2),
            'account_balance_after' => round(max(0, $balanceBefore - $amount), 2),
            'credit_charge_reduction' => round($applied, 2),
            'credit_balance_increase' => round($creditBalance, 2),
        ];
    }

    private function restoreEmployeeCreditForVoidedReturn(Sale $sale, SaleCancellation $cancellation): void
    {
        if ($cancellation->application_type !== 'account_adjustment') {
            return;
        }

        $charge = EmployeeCreditCharge::query()->where('sale_id', $sale->id)->lockForUpdate()->first();
        if (! $charge) {
            throw ValidationException::withMessages(['return' => 'No se encontró el cargo de crédito que debe restaurarse.']);
        }

        $account = $charge->account()->lockForUpdate()->firstOrFail();
        $chargeReduction = (float) $cancellation->credit_charge_reduction;
        $creditBalanceIncrease = (float) $cancellation->credit_balance_increase;

        // Compatibilidad con devoluciones registradas antes de guardar el desglose exacto.
        if ($chargeReduction <= 0 && $creditBalanceIncrease <= 0) {
            $balanceReduction = max(0, (float) $cancellation->account_balance_before - (float) $cancellation->account_balance_after);
            $chargeReduction = min((float) $cancellation->amount, $balanceReduction);
            $creditBalanceIncrease = max(0, (float) $cancellation->amount - $chargeReduction);
        }

        if ((float) $account->credit_balance + 0.000001 < $creditBalanceIncrease) {
            throw ValidationException::withMessages(['return' => 'El saldo a favor generado por esta devolución ya no está disponible para revertirse.']);
        }

        if ($creditBalanceIncrease > 0) {
            $account->update([
                'credit_balance' => round((float) $account->credit_balance - $creditBalanceIncrease, 2),
            ]);
        }

        $restoredOutstanding = min(
            (float) $charge->amount,
            round((float) $charge->outstanding_amount + $chargeReduction, 2),
        );
        $charge->update([
            'outstanding_amount' => $restoredOutstanding,
            'status' => $restoredOutstanding > 0 ? 'open' : 'paid',
        ]);
    }

    private function recalculateSaleReturnStatus(Sale $sale): void
    {
        $sale->load([
            'details.activeCancellationDetails',
            'activeCancellations:id,sale_id,cancelled_at',
        ]);

        $allReturned = $sale->details->isNotEmpty() && $sale->details->every(
            fn (SaleDetail $detail) => (float) $detail->activeCancellationDetails->sum('quantity') + 0.000001 >= (float) $detail->quantity
        );

        $sale->update([
            'status' => $allReturned ? 'cancelled' : 'completed',
            'cancelled_at' => $allReturned ? $sale->activeCancellations->max('cancelled_at') : null,
        ]);
    }

    private function isCashPayment(Sale $sale): bool
    {
        $sale->loadMissing('paymentMethod:id,name');
        $name = mb_strtolower((string) $sale->paymentMethod?->name);

        return str_contains($name, 'efectivo') || str_contains($name, 'cash');
    }
}
