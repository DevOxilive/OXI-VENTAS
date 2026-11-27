<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('attendance_pin_hash')->nullable()->after('password');
            $table->string('attendance_pin_fingerprint', 64)->nullable()->unique()->after('attendance_pin_hash');
            $table->timestamp('attendance_pin_set_at')->nullable()->after('attendance_pin_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['attendance_pin_fingerprint']);
            $table->dropColumn(['attendance_pin_hash', 'attendance_pin_fingerprint', 'attendance_pin_set_at']);
        });
    }
};
