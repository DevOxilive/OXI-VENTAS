<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('type', 30)->default('fixed');
            $table->string('color', 20)->nullable();
            $table->boolean('active')->default(true)->index();
            $table->time('check_in_at')->nullable();
            $table->time('check_out_at')->nullable();
            $table->time('meal_start_at')->nullable();
            $table->time('meal_end_at')->nullable();
            $table->unsignedSmallInteger('maximum_meal_minutes')->nullable();
            $table->unsignedSmallInteger('expected_work_minutes')->nullable();
            $table->unsignedSmallInteger('minimum_work_minutes')->nullable();
            $table->json('working_days');
            $table->json('tolerances')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_schedule_id')->constrained()->cascadeOnDelete();
            $table->string('assignable_type', 100);
            $table->unsignedBigInteger('assignable_id');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->index(['assignable_type', 'assignable_id', 'active'], 'attendance_assignment_target_index');
        });

        Schema::create('attendance_status_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('color', 20)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true)->index();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_record_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 60)->index();
            $table->date('incident_date')->index();
            $table->time('incident_time')->nullable();
            $table->time('estimated_arrival_at')->nullable();
            $table->text('reason');
            $table->string('evidence_path')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('authorized_at')->nullable();
            $table->text('authorization_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->timestamps();
        });

        $now = now();
        foreach ([
            ['code' => 'on_time', 'name' => 'Puntual', 'color' => 'success', 'sort_order' => 10],
            ['code' => 'late', 'name' => 'Retardo', 'color' => 'warning', 'sort_order' => 20],
            ['code' => 'late_justified', 'name' => 'Retardo justificado', 'color' => 'info', 'sort_order' => 30],
            ['code' => 'absence', 'name' => 'Falta', 'color' => 'danger', 'sort_order' => 40],
            ['code' => 'permission', 'name' => 'Permiso', 'color' => 'info', 'sort_order' => 50],
            ['code' => 'remote_work', 'name' => 'Trabajo remoto', 'color' => 'neutral', 'sort_order' => 60],
        ] as $status) DB::table('attendance_status_definitions')->insert($status + ['active' => true, 'created_at' => $now, 'updated_at' => $now]);

        foreach ([
            'attendance.schedules.view', 'attendance.schedules.manage',
            'attendance.incidents.view', 'attendance.incidents.create', 'attendance.incidents.review',
        ] as $permission) DB::table('permissions')->updateOrInsert(['name' => $permission], ['created_at' => $now, 'updated_at' => $now]);

        $permissionIds = DB::table('permissions')->whereIn('name', ['attendance.schedules.view', 'attendance.schedules.manage', 'attendance.incidents.view', 'attendance.incidents.create', 'attendance.incidents.review'])->pluck('id');
        foreach (DB::table('roles')->whereIn('name', ['Administrador', 'Super Administrador'])->pluck('id') as $roleId) {
            foreach ($permissionIds as $permissionId) DB::table('role_permission')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_settings');
        Schema::dropIfExists('attendance_incidents');
        Schema::dropIfExists('attendance_status_definitions');
        Schema::dropIfExists('attendance_schedule_assignments');
        Schema::dropIfExists('attendance_schedules');
    }
};
