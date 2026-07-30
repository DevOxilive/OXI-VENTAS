<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_audits', function (Blueprint $table) {
            $table->index(['module', 'occurred_at'], 'system_audits_module_date_index');
            $table->index(['user_id', 'occurred_at'], 'system_audits_user_date_index');
            $table->index(['result', 'occurred_at'], 'system_audits_result_date_index');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(
                ['branch_product_id', 'created_at'],
                'stock_movements_branch_product_date_index'
            );
            $table->index(
                ['type', 'reason', 'created_at'],
                'stock_movements_type_reason_date_index'
            );
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->index(
                ['attendance_date', 'type', 'user_id'],
                'attendance_records_date_type_user_index'
            );
            $table->index(
                ['attendance_date', 'status', 'user_id'],
                'attendance_records_date_status_user_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('system_audits', function (Blueprint $table) {
            $table->dropIndex('system_audits_module_date_index');
            $table->dropIndex('system_audits_user_date_index');
            $table->dropIndex('system_audits_result_date_index');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('stock_movements_branch_product_date_index');
            $table->dropIndex('stock_movements_type_reason_date_index');
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropIndex('attendance_records_date_type_user_index');
            $table->dropIndex('attendance_records_date_status_user_index');
        });
    }
};
