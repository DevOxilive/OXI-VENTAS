<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleCancellationDetail;
use App\Models\SaleDetail;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SalesDailySummaryService
{
    /** Registra la cantidad base vendida en el resumen de la fecha original del ticket. */
    public function recordSale(Sale $sale, SaleDetail $detail): void
    {
        $this->adjust(
            sale: $sale,
            productId: (int) $detail->product_id,
            quantity: $this->baseQuantity($detail->base_quantity, $detail->quantity),
        );
    }

    /** Resta del resumen sólo la cantidad realmente devuelta. */
    public function recordReturn(Sale $sale, SaleCancellationDetail $detail): void
    {
        $this->adjust(
            sale: $sale,
            productId: (int) $detail->product_id,
            quantity: -$this->baseQuantity($detail->base_quantity, $detail->quantity),
        );
    }

    /** Reintegra al resumen una devolución que fue anulada. */
    public function restoreVoidedReturn(Sale $sale, SaleCancellationDetail $detail): void
    {
        $this->adjust(
            sale: $sale,
            productId: (int) $detail->product_id,
            quantity: $this->baseQuantity($detail->base_quantity, $detail->quantity),
        );
    }

    private function adjust(Sale $sale, int $productId, float $quantity): void
    {
        $saleDate = $sale->date->toDateString();
        $key = [
            'sale_date' => $saleDate,
            'branch_id' => (int) $sale->branch_id,
            'product_id' => $productId,
        ];

        // Se crea el renglón vacío si es la primera operación diaria. La llave
        // única evita duplicados y el bloqueo conserva el acumulado ante cajas simultáneas.
        DB::table('sales_daily_summaries')->insertOrIgnore([
            ...$key,
            'quantity' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $summary = DB::table('sales_daily_summaries')
            ->where($key)
            ->lockForUpdate()
            ->first();

        if (! $summary) {
            throw new RuntimeException('No se pudo preparar el resumen diario de ventas.');
        }

        DB::table('sales_daily_summaries')
            ->where('id', $summary->id)
            ->update([
                'quantity' => round((float) $summary->quantity + $quantity, 3),
                'updated_at' => now(),
            ]);
    }

    private function baseQuantity(mixed $baseQuantity, mixed $quantity): float
    {
        return (float) ($baseQuantity ?? $quantity ?? 0);
    }
}
