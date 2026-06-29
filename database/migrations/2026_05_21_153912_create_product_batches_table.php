<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_product_id')
                ->constrained('branch_products')
                ->cascadeOnDelete();

            $table->string('lot_number')->nullable()->default(255);
            $table->date('expiration_date')->nullable();

            $table->decimal('initial_quantity', 12, 3)->default(0);
            $table->decimal('quantity', 12, 3)->default(0);

            $table->string('supplier')->nullable();
            $table->date('received_at')->nullable();
            $table->date('season_start_date')->nullable();
            $table->date('season_end_date')->nullable();

            $table->boolean('has_real_lot')->default(false);

            $table->enum('entry_type', [
                'PURCHASE_BATCH',
                'PURCHASE_NO_BATCH',
                'BULK_PRODUCT',
            ])->default('PURCHASE_NO_BATCH');

            $table->enum('status', [
                'ACTIVE',
                'INACTIVE',
                'SEASONAL',
            ])->default('ACTIVE');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_batches');
    }
};