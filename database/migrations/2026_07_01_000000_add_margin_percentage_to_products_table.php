<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'margin_percentage')) {
                $table->decimal('margin_percentage', 8, 2)
                    ->nullable()
                    ->after('sale_price');
            }
        });

        DB::table('products')
            ->select('id', 'cost', 'sale_price')
            ->orderBy('id')
            ->chunkById(200, function ($products) {
                foreach ($products as $product) {
                    $cost = (float) ($product->cost ?? 0);
                    $salePrice = (float) ($product->sale_price ?? 0);
                    $margin = $cost > 0
                        ? round((($salePrice - $cost) / $cost) * 100, 2)
                        : null;

                    DB::table('products')
                        ->where('id', $product->id)
                        ->update([
                            'margin_percentage' => $margin,
                        ]);
                }
            }, 'id');
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'margin_percentage')) {
                $table->dropColumn('margin_percentage');
            }
        });
    }
};
