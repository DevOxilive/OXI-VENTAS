<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_cancellations', function (Blueprint $table) {
            $table->decimal('credit_charge_reduction', 12, 2)->default(0)->after('account_balance_after');
            $table->decimal('credit_balance_increase', 12, 2)->default(0)->after('credit_charge_reduction');
            $table->foreignId('voided_by_user_id')->nullable()->after('reason')->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable()->after('voided_by_user_id');
            $table->timestamp('voided_at')->nullable()->after('void_reason');
            $table->index(['branch_id', 'voided_at'], 'sale_cancellations_active_branch_index');
        });

        Schema::table('sale_cancellation_details', function (Blueprint $table) {
            $table->foreignId('void_stock_movement_id')
                ->nullable()
                ->after('return_stock_movement_id')
                ->constrained('stock_movements')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sale_cancellation_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('void_stock_movement_id');
        });

        Schema::table('sale_cancellations', function (Blueprint $table) {
            $table->dropIndex('sale_cancellations_active_branch_index');
            $table->dropConstrainedForeignId('voided_by_user_id');
            $table->dropColumn([
                'credit_charge_reduction',
                'credit_balance_increase',
                'void_reason',
                'voided_at',
            ]);
        });
    }
};
