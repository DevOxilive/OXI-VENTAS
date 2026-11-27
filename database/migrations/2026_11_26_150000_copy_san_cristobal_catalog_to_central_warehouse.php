<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $warehouseId = DB::table('branches')
            ->where('is_central_warehouse', true)
            ->value('id');
        $sourceBranchId = DB::table('branches')
            ->where('name', 'San Cristobal')
            ->value('id');

        if (! $warehouseId || ! $sourceBranchId) return;

        DB::table('branch_products')
            ->where('branch_id', $sourceBranchId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->each(function (object $product) use ($warehouseId) {
                DB::table('branch_products')->updateOrInsert(
                    [
                        'branch_id' => $warehouseId,
                        'product_id' => $product->product_id,
                    ],
                    [
                        'barcode' => $product->barcode,
                        'stock' => 0,
                        'min_stock' => $product->min_stock,
                        'status' => $product->status,
                        'tracks_batches' => $product->tracks_batches,
                        'tracks_expiration' => $product->tracks_expiration,
                        'inactive_candidate_after_days' => $product->inactive_candidate_after_days,
                        'entry_date' => $product->entry_date,
                        'season_start_date' => $product->season_start_date,
                        'season_end_date' => $product->season_end_date,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
            });
    }

    public function down(): void
    {
        // El catálogo creado no se elimina automáticamente para no borrar inventario ya recibido.
    }
};
