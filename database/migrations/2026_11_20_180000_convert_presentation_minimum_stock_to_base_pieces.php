<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Los mínimos históricos de productos con presentación estaban guardados
     * como si fueran piezas. A partir de esta versión representan cajas o
     * paquetes completos, mientras la comparación de inventario permanece en
     * piezas para conservar alertas y movimientos correctos.
     */
    public function up(): void
    {
        DB::table('branch_products')
            ->join('products', 'products.id', '=', 'branch_products.product_id')
            ->where('products.inventory_unit', 'pza')
            ->where(function ($query) {
                $query->where(function ($query) {
                    $query->where('products.has_box_presentation', true)
                        ->where('products.pieces_per_box', '>=', 2);
                })->orWhere(function ($query) {
                    $query->where('products.has_box_presentation', false)
                        ->where('products.has_package_presentation', true)
                        ->where('products.pieces_per_package', '>=', 2);
                });
            })
            ->select([
                'branch_products.id',
                'branch_products.min_stock',
                'products.has_box_presentation',
                'products.pieces_per_box',
                'products.pieces_per_package',
            ])
            ->orderBy('branch_products.id')
            ->chunkById(100, function ($branchProducts) {
                foreach ($branchProducts as $branchProduct) {
                    $factor = $branchProduct->has_box_presentation
                        ? (int) $branchProduct->pieces_per_box
                        : (int) $branchProduct->pieces_per_package;

                    DB::table('branch_products')
                        ->where('id', $branchProduct->id)
                        ->update(['min_stock' => (float) $branchProduct->min_stock * $factor]);
                }
            }, 'branch_products.id', 'id');
    }

    /**
     * No se revierte automáticamente para no reinterpretar mínimos ya editados.
     */
    public function down(): void
    {
    }
};
