<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('purchase_orders', 'ticket_number')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropColumn('ticket_number');
            });
        }

        if (! Schema::hasColumn('purchase_order_items', 'discount_amount')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->decimal('discount_amount', 12, 2)
                    ->default(0)
                    ->after('actual_price');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('purchase_orders', 'ticket_number')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->string('ticket_number')->nullable()->after('actual_total');
            });
        }

        if (Schema::hasColumn('purchase_order_items', 'discount_amount')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->dropColumn('discount_amount');
            });
        }
    }
};
