<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('general_purchase_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('general_purchase_order_items', 'product_description')) {
                $table->text('product_description')->nullable()->after('product_name');
            }

            if (! Schema::hasColumn('general_purchase_order_items', 'purchase_price')) {
                $table->decimal('purchase_price', 12, 2)->nullable()->after('units_per_package');
            }

            if (! Schema::hasColumn('general_purchase_order_items', 'purchase_notes')) {
                $table->text('purchase_notes')->nullable()->after('unavailable');
            }
        });

        if (Schema::hasColumn('general_purchase_order_items', 'package_price')) {
            DB::table('general_purchase_order_items')->update([
                'purchase_price' => DB::raw('package_price'),
            ]);
        }

        if (Schema::hasColumn('general_purchase_order_items', 'promotion_notes')) {
            DB::table('general_purchase_order_items')->update([
                'purchase_notes' => DB::raw('promotion_notes'),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('general_purchase_order_items', 'package_price')) {
            DB::table('general_purchase_order_items')->update([
                'package_price' => DB::raw('purchase_price'),
            ]);
        }

        if (Schema::hasColumn('general_purchase_order_items', 'promotion_notes')) {
            DB::table('general_purchase_order_items')->update([
                'promotion_notes' => DB::raw('purchase_notes'),
            ]);
        }

        Schema::table('general_purchase_order_items', function (Blueprint $table) {
            $columns = collect(['product_description', 'purchase_price', 'purchase_notes'])
                ->filter(fn (string $column) => Schema::hasColumn('general_purchase_order_items', $column))
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
