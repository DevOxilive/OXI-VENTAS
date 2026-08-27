<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('branch_products', 'entry_date')) {
            Schema::table('branch_products', function (Blueprint $table) {
                $table->date('entry_date')->nullable()->after('tracks_expiration');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('branch_products', 'entry_date')) {
            Schema::table('branch_products', function (Blueprint $table) {
                $table->dropColumn('entry_date');
            });
        }
    }
};
