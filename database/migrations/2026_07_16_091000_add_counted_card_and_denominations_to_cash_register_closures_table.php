<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_register_closures', function (Blueprint $table) {
            if (!Schema::hasColumn('cash_register_closures', 'counted_card')) {
                $table->decimal('counted_card', 12, 2)->default(0)->after('counted_cash');
            }

            if (!Schema::hasColumn('cash_register_closures', 'card_difference')) {
                $table->decimal('card_difference', 12, 2)->default(0)->after('cash_difference');
            }

            if (!Schema::hasColumn('cash_register_closures', 'denomination_breakdown')) {
                $table->json('denomination_breakdown')->nullable()->after('payment_breakdown');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cash_register_closures', function (Blueprint $table) {
            foreach (['denomination_breakdown', 'card_difference', 'counted_card'] as $column) {
                if (Schema::hasColumn('cash_register_closures', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
