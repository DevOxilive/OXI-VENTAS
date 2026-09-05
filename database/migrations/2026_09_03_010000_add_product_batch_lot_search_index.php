<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_batches', function (Blueprint $table) {
            $table->index('lot_number', 'product_batches_lot_number_search_index');
        });
    }

    public function down(): void
    {
        Schema::table('product_batches', function (Blueprint $table) {
            $table->dropIndex('product_batches_lot_number_search_index');
        });
    }
};
