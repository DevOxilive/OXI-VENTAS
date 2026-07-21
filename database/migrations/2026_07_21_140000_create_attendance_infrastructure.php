<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->date('attendance_date')->index();
            $table->timestamp('recorded_at')->index();
            $table->string('type', 60)->index();
            $table->string('status', 40)->default('pending')->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('location_accuracy')->nullable();
            $table->string('approximate_address')->nullable();
            $table->boolean('within_geofence')->nullable();
            $table->json('geofence_snapshot')->nullable();
            $table->string('authentication_method', 60);
            $table->string('authentication_result', 30)->default('verified');
            $table->string('operating_system')->nullable();
            $table->string('browser')->nullable();
            $table->string('device_type')->nullable();
            $table->text('user_agent')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'attendance_date']);
        });

        Schema::create('attendance_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->json('requested_changes')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        $now = now();
        $permissions = [
            'attendance.view', 'attendance.register', 'attendance.corrections.request',
            'attendance.manage', 'attendance.corrections.review', 'attendance.reports',
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(['name' => $permission], ['created_at' => $now, 'updated_at' => $now]);
        }

        $employeePermissionIds = DB::table('permissions')->whereIn('name', ['attendance.view', 'attendance.register', 'attendance.corrections.request'])->pluck('id');
        foreach (DB::table('roles')->pluck('id') as $roleId) {
            foreach ($employeePermissionIds as $permissionId) {
                DB::table('role_permission')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }

        $managementPermissionIds = DB::table('permissions')->whereIn('name', ['attendance.manage', 'attendance.corrections.review', 'attendance.reports'])->pluck('id');
        foreach (DB::table('roles')->whereIn('name', ['Administrador', 'Super Administrador'])->pluck('id') as $roleId) {
            foreach ($managementPermissionIds as $permissionId) {
                DB::table('role_permission')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_correction_requests');
        Schema::dropIfExists('attendance_records');
    }
};
