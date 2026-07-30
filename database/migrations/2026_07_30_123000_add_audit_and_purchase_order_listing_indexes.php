<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('physical_counts', function (Blueprint $table) {
            $table->index(
                ['branch_id', 'status', 'started_at'],
                'physical_counts_branch_status_started_index'
            );
        });

        Schema::table('physical_count_entries', function (Blueprint $table) {
            $table->index(
                ['physical_count_id', 'user_id', 'created_at'],
                'physical_count_entries_audit_user_date_index'
            );
            $table->index(
                ['physical_count_id', 'branch_product_id', 'deleted_at'],
                'physical_count_entries_audit_product_deleted_index'
            );
        });

        Schema::table('physical_count_snapshot_items', function (Blueprint $table) {
            $table->index(
                ['physical_count_snapshot_id', 'category_id'],
                'physical_count_snapshot_items_snapshot_category_index'
            );
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->index(
                ['branch_id', 'status', 'assigned_to_user_id', 'general_purchase_order_id'],
                'purchase_orders_generation_listing_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex('purchase_orders_generation_listing_index');
        });

        Schema::table('physical_count_snapshot_items', function (Blueprint $table) {
            $table->dropIndex('physical_count_snapshot_items_snapshot_category_index');
        });

        Schema::table('physical_count_entries', function (Blueprint $table) {
            $table->dropIndex('physical_count_entries_audit_user_date_index');
            $table->dropIndex('physical_count_entries_audit_product_deleted_index');
        });

        Schema::table('physical_counts', function (Blueprint $table) {
            $table->dropIndex('physical_counts_branch_status_started_index');
        });
    }
};
