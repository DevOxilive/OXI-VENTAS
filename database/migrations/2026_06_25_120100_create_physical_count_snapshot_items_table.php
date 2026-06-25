<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physical_count_snapshot_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('physical_count_snapshot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_batch_id')->nullable()->constrained('product_batches')->nullOnDelete();
            $table->string('barcode')->nullable();
            $table->string('product_name');
            $table->string('category_name')->nullable();
            $table->string('subcategory_name')->nullable();
            $table->string('lot_number')->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('branch_product_status')->nullable();
            $table->string('batch_status')->nullable();
            $table->decimal('system_stock', 10, 2)->default(0);
            $table->decimal('batch_stock', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['physical_count_snapshot_id', 'branch_product_id'], 'pcs_snapshot_branch_product_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('physical_count_snapshot_items');
    }
};
