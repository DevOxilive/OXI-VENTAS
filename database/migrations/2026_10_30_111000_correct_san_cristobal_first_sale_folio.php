<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $branches = DB::table('branches')->whereIn('name', ['San Cristobal', 'San Cristóbal'])
                ->lockForUpdate()->get(['id']);

            if ($branches->count() > 1) {
                throw new RuntimeException('Hay más de una sucursal San Cristóbal; identifica la venta antes de corregir su folio.');
            }

            if ($branches->isEmpty()) {
                return;
            }

            $branchId = $branches->first()->id;
            $sale = DB::table('sales')->where('branch_id', $branchId)->where('folio', 'V-000298')
                ->lockForUpdate()->first();

            if (! $sale) {
                return;
            }

            if (DB::table('sales')->where('branch_id', $branchId)->where('folio', 'V-000001')->exists()) {
                throw new RuntimeException('San Cristóbal ya tiene el folio V-000001; no se sobrescribió ningún ticket.');
            }

            DB::table('sales')->where('id', $sale->id)->update(['folio' => 'V-000001']);

            $nextNumber = 2;
            DB::table('sales')->where('branch_id', $branchId)->select('id', 'folio')
                ->chunkById(1000, function ($sales) use (&$nextNumber) {
                    foreach ($sales as $existingSale) {
                        if (preg_match('/^V-(\d+)$/', (string) $existingSale->folio, $matches)) {
                            $nextNumber = max($nextNumber, (int) $matches[1] + 1);
                        }
                    }
                });

            DB::table('branches')->where('id', $branchId)->update(['next_sale_folio' => $nextNumber]);
        });
    }

    public function down(): void
    {
        // La corrección autorizada de este ticket no se deshace al revertir código.
    }
};
