<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('branches', 'next_sale_folio')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->unsignedBigInteger('next_sale_folio')->nullable();
            });
        }

        if (! Schema::hasIndex('sales', 'sales_branch_folio_unique')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->unique(['branch_id', 'folio'], 'sales_branch_folio_unique');
            });
        }

        if (Schema::hasIndex('sales', 'sales_folio_unique')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropUnique('sales_folio_unique');
            });
        }

        // Cada sucursal continúa desde su último ticket; no se reinicia el historial.
        DB::table('branches')->select('id')->chunkById(200, function ($branches) {
            foreach ($branches as $branch) {
                DB::transaction(function () use ($branch) {
                    $lockedBranch = DB::table('branches')->where('id', $branch->id)->lockForUpdate()->first();
                    if (! $lockedBranch) {
                        return;
                    }

                    $nextNumber = max(1, (int) $lockedBranch->next_sale_folio);
                    DB::table('sales')->where('branch_id', $branch->id)->select('id', 'folio')
                        ->chunkById(1000, function ($sales) use (&$nextNumber) {
                            foreach ($sales as $sale) {
                                if (preg_match('/^V-(\d+)$/', (string) $sale->folio, $matches)) {
                                    $nextNumber = max($nextNumber, (int) $matches[1] + 1);
                                }
                            }
                        });

                    DB::table('branches')->where('id', $branch->id)->update(['next_sale_folio' => $nextNumber]);
                });
            }
        });
    }

    public function down(): void
    {
        if (DB::table('sales')->whereNotNull('folio')->groupBy('folio')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('No se puede restaurar la numeración global: existen folios compartidos entre sucursales.');
        }

        if (! Schema::hasIndex('sales', 'sales_folio_unique')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->unique('folio');
            });
        }

        if (Schema::hasIndex('sales', 'sales_branch_folio_unique')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropUnique('sales_branch_folio_unique');
            });
        }

        if (Schema::hasColumn('branches', 'next_sale_folio')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->dropColumn('next_sale_folio');
            });
        }
    }
};
