<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index('created_at', 'stock_movements_created_at_index');
        });

        Schema::table('branch_products', function (Blueprint $table) {
            $table->index(['branch_id', 'status'], 'branch_products_branch_status_index');
        });

        Schema::table('product_batches', function (Blueprint $table) {
            $table->index(
                ['status', 'expiration_date', 'branch_product_id'],
                'product_batches_status_expiration_branch_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropIndex('stock_movements_created_at_index'));
        Schema::table('branch_products', fn (Blueprint $table) => $table->dropIndex('branch_products_branch_status_index'));
        Schema::table('product_batches', fn (Blueprint $table) => $table->dropIndex('product_batches_status_expiration_branch_index'));
    }
};
