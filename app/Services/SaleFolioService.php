<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use LogicException;

class SaleFolioService
{
    public function nextForBranch(int $branchId): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('El folio debe reservarse dentro de la misma transacción que registra la venta.');
        }

        $branch = DB::table('branches')->where('id', $branchId)->lockForUpdate()->first();
        if (! $branch) {
            throw new LogicException('La sucursal de la venta ya no está disponible.');
        }
        $number = $branch->next_sale_folio === null ? 1 : (int) $branch->next_sale_folio;

        if ($branch->next_sale_folio === null) {
            DB::table('sales')->where('branch_id', $branchId)->select('id', 'folio')
                ->chunkById(1000, function ($sales) use (&$number) {
                    foreach ($sales as $sale) {
                        if (preg_match('/^V-(\d+)$/', (string) $sale->folio, $matches)) {
                            $number = max($number, (int) $matches[1] + 1);
                        }
                    }
                });
        }

        // Los tickets históricos conservan su folio; nunca se reutiliza uno en la misma sucursal.
        do {
            $folio = 'V-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
            $number++;
        } while (DB::table('sales')->where('branch_id', $branchId)->where('folio', $folio)->exists());

        DB::table('branches')->where('id', $branchId)->update(['next_sale_folio' => $number]);

        return $folio;
    }
}
