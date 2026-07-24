<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_details', fn (Blueprint $table) => $table->decimal('unit_cost', 12, 4)->nullable()->after('unit_price'));
        Schema::table('stock_movements', fn (Blueprint $table) => $table->decimal('unit_cost', 12, 4)->nullable()->after('quantity'));
    }

    public function down(): void
    {
        Schema::table('sale_details', fn (Blueprint $table) => $table->dropColumn('unit_cost'));
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropColumn('unit_cost'));
    }
};
