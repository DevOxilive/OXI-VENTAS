<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_products', function (Blueprint $table) {
            $table->index('barcode', 'branch_products_barcode_search_index');
        });
    }

    public function down(): void
    {
        Schema::table('branch_products', function (Blueprint $table) {
            $table->dropIndex('branch_products_barcode_search_index');
        });
    }
};
