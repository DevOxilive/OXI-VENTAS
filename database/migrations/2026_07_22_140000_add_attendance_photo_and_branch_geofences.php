<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Limpieza de instalaciones previas que usaron reconocimiento facial.
        Schema::dropIfExists('employee_biometric_profiles');

        Schema::table('attendance_records', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_records', 'selfie_path')) {
                $table->string('selfie_path')->nullable()->after('authentication_result');
            }
            if (Schema::hasColumn('attendance_records', 'face_match_score')) {
                $table->dropColumn('face_match_score');
            }
            if (Schema::hasColumn('attendance_records', 'face_verification_result')) {
                $table->dropColumn('face_verification_result');
            }
        });

        Schema::table('branches', function (Blueprint $table) {
            if (! Schema::hasColumn('branches', 'attendance_latitude')) {
                $table->decimal('attendance_latitude', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('branches', 'attendance_longitude')) {
                $table->decimal('attendance_longitude', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('branches', 'attendance_geofence_radius_meters')) {
                $table->unsignedInteger('attendance_geofence_radius_meters')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['attendance_latitude', 'attendance_longitude', 'attendance_geofence_radius_meters']);
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn('selfie_path');
        });
    }
};
