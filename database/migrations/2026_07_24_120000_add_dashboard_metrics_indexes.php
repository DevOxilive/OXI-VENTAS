<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->index(['branch_id', 'status', 'date'], 'sales_dashboard_branch_status_date_index');
        });
        Schema::table('cash_register_closures', function (Blueprint $table) {
            $table->index(['branch_id', 'cash_box_number', 'period_start', 'period_end'], 'cash_closures_dashboard_confirmation_index');
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['reason', 'type', 'created_at'], 'stock_movements_dashboard_shrinkage_index');
        });
        Schema::table('general_purchase_orders', function (Blueprint $table) {
            $table->index(['status', 'purchased_at'], 'general_orders_dashboard_status_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('sales', fn (Blueprint $table) => $table->dropIndex('sales_dashboard_branch_status_date_index'));
        Schema::table('cash_register_closures', fn (Blueprint $table) => $table->dropIndex('cash_closures_dashboard_confirmation_index'));
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropIndex('stock_movements_dashboard_shrinkage_index'));
        Schema::table('general_purchase_orders', fn (Blueprint $table) => $table->dropIndex('general_orders_dashboard_status_date_index'));
    }
};
