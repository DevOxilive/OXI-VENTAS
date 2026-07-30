<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('physical_counts', 'finalized_at')) {
            Schema::table('physical_counts', function (Blueprint $table) {
                $table->timestamp('finalized_at')->nullable()->after('recapture_started_at');
                $table->foreignId('finalized_by')->nullable()->after('finalized_at')
                    ->constrained('users')->nullOnDelete();
            });
        }

        DB::table('permissions')->updateOrInsert([
            'name' => 'audits.physical-counts.finalize',
        ], [
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $permissionId = DB::table('permissions')
            ->where('name', 'audits.physical-counts.finalize')
            ->value('id');
        $applyPermissionId = DB::table('permissions')
            ->where('name', 'audits.physical-counts.apply')
            ->value('id');

        if ($applyPermissionId) {
            DB::table('role_permission')
                ->where('permission_id', $applyPermissionId)
                ->pluck('role_id')
                ->each(fn ($roleId) => DB::table('role_permission')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]));

            DB::table('permission_user')
                ->where('permission_id', $applyPermissionId)
                ->get()
                ->each(fn ($assignment) => DB::table('permission_user')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'user_id' => $assignment->user_id,
                    'mode' => $assignment->mode,
                ]));
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'audits.physical-counts.finalize')
            ->value('id');

        if ($permissionId) {
            DB::table('role_permission')->where('permission_id', $permissionId)->delete();
            DB::table('permission_user')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        Schema::table('physical_counts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('finalized_by');
            $table->dropColumn('finalized_at');
        });
    }
};
