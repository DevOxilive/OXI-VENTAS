<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TRANSFER_PERMISSION = 'inventory.purchase-orders.source.transfer';

    public function up(): void
    {
        DB::transaction(function (): void {
            $permissionIds = DB::table('permissions')
                ->where('name', self::TRANSFER_PERMISSION)
                ->pluck('id');

            if ($permissionIds->isNotEmpty()) {
                DB::table('permission_user')->whereIn('permission_id', $permissionIds)->delete();
                DB::table('role_permission')->whereIn('permission_id', $permissionIds)->delete();
                DB::table('permissions')->whereIn('id', $permissionIds)->delete();
            }
        });

        Schema::dropIfExists('purchase_order_transfers');
        Schema::dropIfExists('purchase_report_items');
        Schema::dropIfExists('purchase_reports');

        if (! Schema::hasTable('purchase_orders') || ! Schema::hasColumn('purchase_orders', 'assigned_to_user_id')) {
            return;
        }

        $hasLegacyListingIndex = Schema::hasIndex('purchase_orders', 'purchase_orders_generation_listing_index');
        $hasReplacementListingIndex = Schema::hasIndex('purchase_orders', 'purchase_orders_generation_listing_without_assignee_index');

        Schema::table('purchase_orders', function (Blueprint $table) use ($hasLegacyListingIndex, $hasReplacementListingIndex): void {
            if ($hasLegacyListingIndex) {
                $table->dropIndex('purchase_orders_generation_listing_index');
            }

            $table->dropConstrainedForeignId('assigned_to_user_id');

            if (! $hasReplacementListingIndex) {
                $table->index(
                    ['branch_id', 'status', 'general_purchase_order_id'],
                    'purchase_orders_generation_listing_without_assignee_index',
                );
            }
        });
    }

    public function down(): void
    {
        // The old reports and the individual-assignment workflow were deliberately retired.
        // Their historical data cannot be reconstructed during a rollback.
    }
};
