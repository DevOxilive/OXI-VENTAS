<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex(
            'general_purchase_orders',
            'general_purchase_orders_purchase_cycle_id_unique'
        )) {
            Schema::table('general_purchase_orders', function (Blueprint $table) {
                $table->dropUnique('general_purchase_orders_purchase_cycle_id_unique');
            });
        }

    }

    public function down(): void
    {
        if (! Schema::hasIndex(
            'general_purchase_orders',
            'general_purchase_orders_purchase_cycle_id_unique'
        )) {
            Schema::table('general_purchase_orders', function (Blueprint $table) {
                $table->unique('purchase_cycle_id');
            });
        }
    }
};
