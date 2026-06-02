<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_movement_batches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stock_movement_id')
                ->constrained('stock_movements')
                ->cascadeOnDelete();

            $table->foreignId('product_batch_id')
                ->constrained('product_batches')
                ->cascadeOnDelete();

            $table->decimal('quantity', 12, 3);
            $table->decimal('previous_batch_quantity', 12, 3);
            $table->decimal('new_batch_quantity', 12, 3);

            $table->enum('allocation_method', [
                'MANUAL',
                'FEFO_AUTO',
            ])->default('MANUAL');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movement_batches');
    }
};