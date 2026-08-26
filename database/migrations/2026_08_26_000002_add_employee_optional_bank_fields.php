<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'bank_clabe')) {
                $table->string('bank_clabe', 18)->nullable()->after('account_number');
            }

            if (! Schema::hasColumn('employees', 'bank_card_number')) {
                $table->string('bank_card_number', 16)->nullable()->after('bank_clabe');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            foreach (['bank_card_number', 'bank_clabe'] as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
