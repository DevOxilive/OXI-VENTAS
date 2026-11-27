<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('general_purchase_order_item_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('general_purchase_order_item_id')
                ->constrained(indexName: 'gpo_item_batches_item_fk')
                ->cascadeOnDelete();
            $table->string('lot_number', 100)->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('purchase_presentation', 20);
            $table->decimal('presentation_quantity', 12, 3);
            $table->decimal('base_quantity', 12, 3);
            $table->timestamps();

            $table->index(['general_purchase_order_item_id', 'expiration_date'], 'general_purchase_item_batches_expiration_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('general_purchase_order_item_batches');
    }
};
