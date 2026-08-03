<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            if (! Schema::hasColumn('branches', 'maps_url')) {
                $table->text('maps_url')->nullable()->after('color');
            }
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            if (Schema::hasColumn('branches', 'maps_url')) {
                $table->dropColumn('maps_url');
            }
        });
    }
};
