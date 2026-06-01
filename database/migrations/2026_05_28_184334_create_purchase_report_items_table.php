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
        Schema::create('purchase_report_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_report_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('branch_product_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->decimal('current_stock', 10, 2)->default(0);
            $table->decimal('min_stock', 10, 2)->default(0);

            $table->decimal('requested_quantity', 10, 2)->default(0);

            $table->decimal('estimated_price', 10, 2)->nullable();
            $table->decimal('estimated_total', 10, 2)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['purchase_report_id', 'branch_product_id'],
                'purchase_report_product_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_report_items');
    }
};
