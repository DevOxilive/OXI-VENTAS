<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_product_id')
                ->constrained('branch_products')
                ->cascadeOnDelete();

            $table->enum('type', [
                'IN',
                'OUT',
                'ADJUSTMENT',
            ]);

            $table->enum('reason', [
                'PURCHASE',
                'SALE',
                'DAMAGED',
                'EXPIRED',
                'OTHER',
                'INVENTORY_DIFFERENCE',
            ]);

            $table->decimal('quantity', 12, 3);
            $table->decimal('previous_stock', 12, 3);
            $table->decimal('new_stock', 12, 3);

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
