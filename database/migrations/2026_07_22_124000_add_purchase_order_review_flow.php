<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('purchase_orders', 'assigned_to_user_id')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->foreignId('assigned_to_user_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('purchase_order_items', 'received_quantity')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->decimal('received_quantity', 10, 2)
                    ->nullable()
                    ->after('purchased_quantity');
            });
        }

        if (! Schema::hasColumn('purchase_order_items', 'receipt_notes')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->text('receipt_notes')
                    ->nullable()
                    ->after('status');
            });
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE purchase_orders
                MODIFY status ENUM('DRAFT', 'GENERATED', 'REVIEW', 'COMPLETED', 'CANCELLED')
                NOT NULL DEFAULT 'DRAFT'
            SQL);
        }
    }

    public function down(): void
    {
        DB::table('purchase_orders')
            ->where('status', 'REVIEW')
            ->update(['status' => 'GENERATED']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE purchase_orders
                MODIFY status ENUM('DRAFT', 'GENERATED', 'COMPLETED', 'CANCELLED')
                NOT NULL DEFAULT 'DRAFT'
            SQL);
        }

        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_order_items', 'received_quantity')) {
                $table->dropColumn('received_quantity');
            }

            if (Schema::hasColumn('purchase_order_items', 'receipt_notes')) {
                $table->dropColumn('receipt_notes');
            }
        });

        if (Schema::hasColumn('purchase_orders', 'assigned_to_user_id')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropConstrainedForeignId('assigned_to_user_id');
            });
        }
    }
};
