<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_order_items', 'purchase_presentation')) {
                $table->string('purchase_presentation', 20)->default('Pieza')->after('requested_quantity');
            }

            if (! Schema::hasColumn('purchase_order_items', 'package_quantity')) {
                $table->decimal('package_quantity', 12, 3)->nullable()->after('purchase_presentation');
            }

            if (! Schema::hasColumn('purchase_order_items', 'units_per_package')) {
                $table->decimal('units_per_package', 12, 3)->nullable()->after('package_quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            foreach (['units_per_package', 'package_quantity', 'purchase_presentation'] as $column) {
                if (Schema::hasColumn('purchase_order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
