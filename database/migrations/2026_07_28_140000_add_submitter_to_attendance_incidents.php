<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_incidents', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_incidents', 'submitted_by')) {
                $table->foreignId('submitted_by')
                    ->nullable()
                    ->after('attendance_record_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        $permissionIds = DB::table('permissions')
            ->whereIn('name', [
                'attendance.incidents.view',
                'attendance.incidents.create',
                'attendance.incidents.update',
            ])
            ->pluck('id');

        $humanResourcesRoleId = DB::table('roles')
            ->where('name', 'Recursos Humanos')
            ->value('id');

        if ($humanResourcesRoleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $humanResourcesRoleId,
                    'permission_id' => $permissionId,
                ]);
            }

            DB::table('role_permission')
                ->where('role_id', $humanResourcesRoleId)
                ->whereIn('permission_id', DB::table('permissions')
                    ->whereIn('name', [
                        'attendance.incidents.approve',
                        'attendance.incidents.reject',
                    ])
                    ->pluck('id'))
                ->delete();
        }
    }

    public function down(): void
    {
        Schema::table('attendance_incidents', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_incidents', 'submitted_by')) {
                $table->dropConstrainedForeignId('submitted_by');
            }
        });
    }
};
