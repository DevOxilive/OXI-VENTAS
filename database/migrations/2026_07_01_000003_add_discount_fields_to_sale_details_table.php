<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_details', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_details', 'original_unit_price')) {
                $table->decimal('original_unit_price', 10, 2)->nullable()->after('quantity');
            }

            if (!Schema::hasColumn('sale_details', 'discount_percentage')) {
                $table->decimal('discount_percentage', 5, 2)->default(0)->after('original_unit_price');
            }

            if (!Schema::hasColumn('sale_details', 'discount_amount')) {
                $table->decimal('discount_amount', 10, 2)->default(0)->after('discount_percentage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_details', function (Blueprint $table) {
            if (Schema::hasColumn('sale_details', 'discount_amount')) {
                $table->dropColumn('discount_amount');
            }

            if (Schema::hasColumn('sale_details', 'discount_percentage')) {
                $table->dropColumn('discount_percentage');
            }

            if (Schema::hasColumn('sale_details', 'original_unit_price')) {
                $table->dropColumn('original_unit_price');
            }
        });
    }
};
