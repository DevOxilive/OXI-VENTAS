<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'cash_received')) {
                $table->decimal('cash_received', 10, 2)->nullable()->after('total');
            }

            if (!Schema::hasColumn('sales', 'change_due')) {
                $table->decimal('change_due', 10, 2)->default(0)->after('cash_received');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'change_due')) {
                $table->dropColumn('change_due');
            }

            if (Schema::hasColumn('sales', 'cash_received')) {
                $table->dropColumn('cash_received');
            }
        });
    }
};
