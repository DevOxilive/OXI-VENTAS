<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('branch_products', function (Blueprint $table) {
            $table->date('season_start_date')
                ->nullable()
                ->after('status');

            $table->date('season_end_date')
                ->nullable()
                ->after('season_start_date');
        });
    }

    public function down(): void
    {
        Schema::table('branch_products', function (Blueprint $table) {
            $table->dropColumn([
                'season_start_date',
                'season_end_date',
            ]);
        });
    }
};