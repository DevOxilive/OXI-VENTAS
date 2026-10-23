<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $permission = 'sales.returns.void';

    public function up(): void
    {
        $now = now();

        DB::table('permissions')->insertOrIgnore([
            'name' => $this->permission,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $permissionId = DB::table('permissions')->where('name', $this->permission)->value('id');
        $roleIds = DB::table('roles')
            ->whereIn('name', ['Vendedor', 'Administrador', 'Super Administrador'])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_permission')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', $this->permission)->value('id');

        if (! $permissionId) {
            return;
        }

        DB::table('permission_user')->where('permission_id', $permissionId)->delete();
        DB::table('role_permission')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
