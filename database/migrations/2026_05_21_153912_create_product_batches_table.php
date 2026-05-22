<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductBatchesTable extends Migration
{
    public function up(): void
    {
        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_product_id')
                ->constrained('branch_products')
                ->cascadeOnDelete();

            $table->string('lot_number')->nullable();
            $table->date('expiration_date')->nullable();

            $table->integer('initial_quantity')->default(0);
            $table->integer('quantity')->default(0);

            $table->string('supplier')->nullable();
            $table->date('received_at')->nullable();

            $table->enum('status', [
                'ACTIVE',
                'EXPIRED',
                'DEPLETED',
                'RETURNED',
            ])->default('ACTIVE');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_batches');
    }
}
