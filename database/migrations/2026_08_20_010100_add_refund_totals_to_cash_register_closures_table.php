<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cash_register_closures', function (Blueprint $table) {
            if (! Schema::hasColumn('cash_register_closures', 'refunds_count')) {
                $table->unsignedInteger('refunds_count')->default(0)->after('sales_total');
            }

            if (! Schema::hasColumn('cash_register_closures', 'refunds_total')) {
                $table->decimal('refunds_total', 12, 2)->default(0)->after('refunds_count');
            }

            if (! Schema::hasColumn('cash_register_closures', 'refund_cash_total')) {
                $table->decimal('refund_cash_total', 12, 2)->default(0)->after('refunds_total');
            }

            if (! Schema::hasColumn('cash_register_closures', 'refund_card_total')) {
                $table->decimal('refund_card_total', 12, 2)->default(0)->after('refund_cash_total');
            }

            if (! Schema::hasColumn('cash_register_closures', 'refund_breakdown')) {
                $table->json('refund_breakdown')->nullable()->after('payment_breakdown');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cash_register_closures', function (Blueprint $table) {
            foreach ([
                'refund_breakdown',
                'refund_card_total',
                'refund_cash_total',
                'refunds_total',
                'refunds_count',
            ] as $column) {
                if (Schema::hasColumn('cash_register_closures', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
