<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_register_closures', function (Blueprint $table) {
            if (!Schema::hasColumn('cash_register_closures', 'cash_box_number')) {
                $table->string('cash_box_number')->nullable()->after('user_id');
            }

            if (!Schema::hasColumn('cash_register_closures', 'card_total')) {
                $table->decimal('card_total', 12, 2)->default(0)->after('expected_cash');
            }

            if (!Schema::hasColumn('cash_register_closures', 'other_total')) {
                $table->decimal('other_total', 12, 2)->default(0)->after('card_total');
            }

            if (!Schema::hasColumn('cash_register_closures', 'recharge_total')) {
                $table->decimal('recharge_total', 12, 2)->default(0)->after('other_total');
            }

            if (!Schema::hasColumn('cash_register_closures', 'expected_drawer_cash')) {
                $table->decimal('expected_drawer_cash', 12, 2)->default(0)->after('recharge_total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cash_register_closures', function (Blueprint $table) {
            foreach ([
                'expected_drawer_cash',
                'recharge_total',
                'other_total',
                'card_total',
                'cash_box_number',
            ] as $column) {
                if (Schema::hasColumn('cash_register_closures', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
