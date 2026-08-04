<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('inventory_unit', 10)->nullable()->after('unit');
            $table->boolean('has_box_presentation')->default(false)->after('pieces_per_box');
            $table->decimal('cost_per_piece', 12, 4)->nullable()->after('cost');
            $table->decimal('sale_price_per_piece', 12, 4)->nullable()->after('sale_price');
            $table->decimal('cost_per_box', 12, 4)->nullable()->after('cost_per_piece');
            $table->decimal('sale_price_per_box', 12, 4)->nullable()->after('sale_price_per_piece');
            $table->string('inventory_quantity_mode', 20)->default('base')->after('has_box_presentation');
        });

        // Existing box products keep their historical quantities untouched until
        // the inventory migration can reconcile their stock and lots.
        DB::table('products')->update([
            'inventory_unit' => DB::raw("CASE WHEN unit = 'kg' THEN 'kg' ELSE 'pza' END"),
        ]);

        DB::table('products')
            ->where('unit', 'cj')
            ->update([
                'has_box_presentation' => true,
                'inventory_quantity_mode' => 'legacy_presentation',
                'cost_per_box' => DB::raw('cost'),
                'sale_price_per_box' => DB::raw('sale_price'),
            ]);

        DB::table('products')
            ->where('unit', '!=', 'cj')
            ->update([
                'cost_per_piece' => DB::raw('cost'),
                'sale_price_per_piece' => DB::raw('sale_price'),
            ]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'inventory_unit',
                'has_box_presentation',
                'cost_per_piece',
                'sale_price_per_piece',
                'cost_per_box',
                'sale_price_per_box',
                'inventory_quantity_mode',
            ]);
        });
    }
};
