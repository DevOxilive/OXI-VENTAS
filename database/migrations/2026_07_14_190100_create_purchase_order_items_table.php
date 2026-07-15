<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('branch_product_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->decimal('current_stock', 10, 2)->default(0);
            $table->decimal('min_stock', 10, 2)->default(0);
            $table->decimal('requested_quantity', 10, 2)->default(0);
            $table->decimal('purchased_quantity', 10, 2)->nullable();
            $table->decimal('estimated_price', 10, 2)->nullable();
            $table->decimal('estimated_total', 12, 2)->nullable();
            $table->decimal('actual_price', 10, 2)->nullable();
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('actual_total', 12, 2)->nullable();
            $table->enum('status', [
                'REQUESTED',
                'PURCHASED',
                'ADJUSTED',
                'UNAVAILABLE',
            ])->default('REQUESTED');
            $table->timestamps();

            $table->unique(
                ['purchase_order_id', 'branch_product_id'],
                'purchase_order_product_unique'
            );
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
