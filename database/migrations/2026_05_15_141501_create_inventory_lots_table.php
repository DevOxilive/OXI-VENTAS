<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_lots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->string('lot_code');

            $table->date('entry_date');

            $table->date('expiration_date')->nullable();

            $table->integer('current_stock')->default(0);

            $table->decimal('unit_cost', 10, 2)->default(0);

            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index(['branch_id', 'product_id', 'lot_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_lots');
    }
};