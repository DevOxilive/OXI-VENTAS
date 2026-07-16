<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_register_closures', function (Blueprint $table) {
            if (!Schema::hasColumn('cash_register_closures', 'cash_left')) {
                $table->decimal('cash_left', 12, 2)->default(0)->after('counted_cash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cash_register_closures', function (Blueprint $table) {
            if (Schema::hasColumn('cash_register_closures', 'cash_left')) {
                $table->dropColumn('cash_left');
            }
        });
    }
};
