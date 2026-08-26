<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_branch_slug_is_reported_as_name_validation_error(): void
    {
        Branch::create([
            'name' => 'Tienda Oxilive',
            'slug' => 'tienda-oxilive',
            'active' => true,
        ]);

        $user = $this->authorizedUser(['branches.create']);

        $this->actingAs($user)
            ->from(route('branches.index'))
            ->post(route('branches.store'), [
                'name' => 'Tienda Oxilive',
                'color' => '#facc15',
                'street' => 'Villa Guerrero',
                'external_number' => '227',
                'postal_code' => '57600',
                'neighborhood' => 'Romero Seccion Las Fuentes',
                'municipality' => 'Nezahualcoyotl',
                'address_state' => 'Estado De Mexico',
            ])
            ->assertRedirect(route('branches.index'))
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Branch::withTrashed()->where('slug', 'tienda-oxilive')->count());
    }

    private function authorizedUser(array $permissionNames): User
    {
        $role = Role::create(['name' => 'Administrador de sucursales']);
        $permissions = collect($permissionNames)
            ->map(fn (string $name) => Permission::create(['name' => $name]));
        $role->permissions()->sync($permissions->pluck('id'));

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
