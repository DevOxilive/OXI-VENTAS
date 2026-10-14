<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_cancellations', function (Blueprint $table) {
            $table->string('application_type', 40)->nullable()->after('amount');
            $table->decimal('account_balance_before', 12, 2)->nullable()->after('application_type');
            $table->decimal('account_balance_after', 12, 2)->nullable()->after('account_balance_before');
        });
    }

    public function down(): void
    {
        Schema::table('sale_cancellations', function (Blueprint $table) {
            $table->dropColumn([
                'application_type',
                'account_balance_before',
                'account_balance_after',
            ]);
        });
    }
};
