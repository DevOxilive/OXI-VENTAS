<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemAudit;
use App\Models\User;
use App\Support\SystemPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_exclusive_permission_cannot_access_system_administration(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->getJson(route('system-administration.index'))
            ->assertForbidden();
    }

    public function test_super_administrator_can_access_the_center_and_audits(): void
    {
        $role = Role::create(['name' => 'Super Administrador']);
        $permissions = collect([SystemPermission::ACCESS_CENTER, SystemPermission::AUDIT_VIEW])
            ->map(fn (string $name) => Permission::create(['name' => $name]));
        $role->permissions()->sync($permissions->pluck('id'));
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $this->actingAs($user)
            ->get(route('system-administration.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('system-administration.audits.index'))
            ->assertOk();
    }

    public function test_deleted_user_can_be_restored_from_global_trash_by_authorized_user(): void
    {
        $role = Role::create(['name' => 'Super Administrador']);
        $permissions = collect([SystemPermission::TRASH_VIEW, SystemPermission::TRASH_RESTORE])
            ->map(fn (string $name) => Permission::create(['name' => $name]));
        $role->permissions()->sync($permissions->pluck('id'));
        $actor = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $deleted = User::factory()->create(['is_active' => true]);
        $deleted->delete();

        $this->actingAs($actor)
            ->post(route('system-administration.trash.restore', ['resource' => 'users', 'record' => $deleted->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $deleted->id, 'deleted_at' => null]);
    }

    public function test_mutating_request_is_recorded_in_global_audit_log(): void
    {
        $role = Role::create(['name' => 'Super Administrador']);
        $permission = Permission::create(['name' => SystemPermission::TRASH_RESTORE]);
        $role->permissions()->attach($permission);
        $actor = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $deleted = User::factory()->create(['is_active' => true]);
        $deleted->delete();

        $this->actingAs($actor)
            ->post(route('system-administration.trash.restore', ['resource' => 'users', 'record' => $deleted->id]));

        $this->assertTrue(SystemAudit::query()->where('action', 'restore')->exists());
    }
}
