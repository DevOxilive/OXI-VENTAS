<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesRoleConsolidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_merges_vendedor_permissions_users_and_attendance_permissions_into_ventas(): void
    {
        $ventas = Role::where('name', 'Ventas')->firstOrFail();
        $vendedor = Role::create(['name' => 'Vendedor']);

        $salePermission = Permission::firstOrCreate(['name' => 'sales.create']);
        $voidReturnPermission = Permission::firstOrCreate(['name' => 'sales.returns.void']);
        $attendancePermission = Permission::firstOrCreate(['name' => 'attendance.register']);
        $correctionPermission = Permission::firstOrCreate(['name' => 'attendance.corrections.request']);
        $individualPermission = Permission::create(['name' => 'sales.customers.create']);

        $ventas->permissions()->attach($salePermission);
        $vendedor->permissions()->attach([$salePermission->id, $voidReturnPermission->id]);

        $activeUser = User::factory()->create();
        $deletedUser = User::factory()->create();
        $this->setRole($activeUser, $vendedor);
        $this->setRole($deletedUser, $vendedor);
        $deletedUser->permissions()->attach($individualPermission, ['mode' => 'deny']);
        $deletedUser->delete();

        $this->runConsolidationMigration();

        $this->assertDatabaseMissing('roles', ['id' => $vendedor->id]);
        $this->assertDatabaseHas('users', ['id' => $activeUser->id, 'role_id' => $ventas->id]);
        $this->assertDatabaseHas('users', ['id' => $deletedUser->id, 'role_id' => $ventas->id]);
        $this->assertDatabaseHas('permission_user', [
            'user_id' => $deletedUser->id,
            'permission_id' => $individualPermission->id,
            'mode' => 'deny',
        ]);

        $permissionIds = $ventas->fresh()->permissions()->pluck('permissions.id');
        $this->assertTrue($permissionIds->contains($salePermission->id));
        $this->assertTrue($permissionIds->contains($voidReturnPermission->id));
        $this->assertTrue($permissionIds->contains($attendancePermission->id));
        $this->assertTrue($permissionIds->contains($correctionPermission->id));
    }

    public function test_it_still_grants_attendance_permissions_when_vendedor_was_already_removed(): void
    {
        $ventas = Role::where('name', 'Ventas')->firstOrFail();
        $attendancePermission = Permission::firstOrCreate(['name' => 'attendance.register']);
        $correctionPermission = Permission::firstOrCreate(['name' => 'attendance.corrections.request']);

        $this->runConsolidationMigration();

        $permissionIds = $ventas->fresh()->permissions()->pluck('permissions.id');
        $this->assertTrue($permissionIds->contains($attendancePermission->id));
        $this->assertTrue($permissionIds->contains($correctionPermission->id));
    }

    private function runConsolidationMigration(): void
    {
        $migration = require database_path('migrations/2026_10_30_120000_merge_vendedor_role_into_ventas.php');
        $migration->up();
    }

    private function setRole(User $user, Role $role): void
    {
        \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $user->id)
            ->update(['role_id' => $role->id]);
    }
}
