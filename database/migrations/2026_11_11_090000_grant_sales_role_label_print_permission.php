<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $salesRoleId = DB::table('roles')->where('name', 'Ventas')->value('id');
        $labelPrintPermissionId = DB::table('permissions')
            ->where('name', 'systems.labels.print')
            ->value('id');

        if (! $salesRoleId || ! $labelPrintPermissionId) {
            return;
        }

        DB::table('role_permission')->updateOrInsert([
            'role_id' => $salesRoleId,
            'permission_id' => $labelPrintPermissionId,
        ]);
    }

    public function down(): void
    {
        $salesRoleId = DB::table('roles')->where('name', 'Ventas')->value('id');
        $labelPrintPermissionId = DB::table('permissions')
            ->where('name', 'systems.labels.print')
            ->value('id');

        if ($salesRoleId && $labelPrintPermissionId) {
            DB::table('role_permission')
                ->where('role_id', $salesRoleId)
                ->where('permission_id', $labelPrintPermissionId)
                ->delete();
        }
    }
};
