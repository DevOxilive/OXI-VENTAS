<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
    if (!Schema::hasColumn('branch_products', 'name')) {
        Schema::table('branch_products', function (Blueprint $table) {
            $table->string('name')->nullable()->after('product_id');
        });
    }

    if (!Schema::hasColumn('branch_products', 'barcode')) {
        Schema::table('branch_products', function (Blueprint $table) {
            $table->string('barcode')->nullable()->after('name');
        });
    }

    if (!Schema::hasColumn('branch_products', 'category_id')) {
        Schema::table('branch_products', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('barcode')
                ->constrained('categories')
                ->nullOnDelete();
        });
    }
}

public function down(): void
{
    if (Schema::hasColumn('branch_products', 'category_id')) {
        Schema::table('branch_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }

    if (Schema::hasColumn('branch_products', 'barcode')) {
        Schema::table('branch_products', function (Blueprint $table) {
            $table->dropColumn('barcode');
        });
    }

    if (Schema::hasColumn('branch_products', 'name')) {
        Schema::table('branch_products', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
}
};
