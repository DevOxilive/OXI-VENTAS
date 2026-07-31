<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->string('operation_key', 190)->nullable()->unique()->after('metadata');
        });

        Schema::table('attendance_correction_requests', function (Blueprint $table) {
            $table->string('pending_key', 190)->nullable()->unique()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_correction_requests', function (Blueprint $table) {
            $table->dropUnique(['pending_key']);
            $table->dropColumn('pending_key');
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropUnique(['operation_key']);
            $table->dropColumn('operation_key');
        });
    }
};
