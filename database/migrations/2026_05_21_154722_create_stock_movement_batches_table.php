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
        Schema::create('stock_movement_batches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stock_movement_id')
                ->constrained('stock_movements')
                ->cascadeOnDelete();

            $table->foreignId('product_batch_id')
                ->constrained('product_batches')
                ->cascadeOnDelete();

            $table->integer('quantity');

            $table->integer('previous_batch_quantity');

            $table->integer('new_batch_quantity');

            $table->enum('allocation_method', [
                'FEFO_AUTO',
                'MANUAL',
                'UNKNOWN',
            ])->default('UNKNOWN');

            $table->timestamps();
        });
    }
};
