<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('physical_counts', function (Blueprint $table) {
            $table->string('recapture_scope')->default('all')->after('status');
            $table->timestamp('recapture_started_at')->nullable()->after('closed_at');
            $table->timestamp('last_applied_at')->nullable()->after('recapture_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('physical_counts', function (Blueprint $table) {
            $table->dropColumn(['recapture_scope', 'recapture_started_at', 'last_applied_at']);
        });
    }
};
