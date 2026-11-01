<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserEmployeeLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_independent_user_can_be_linked_to_an_employee(): void
    {
        $role = Role::create(['name' => 'Administrador de prueba']);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'users.update']));
        $actor = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $target = User::factory()->create(['role_id' => $role->id, 'employee_id' => null, 'is_active' => true]);
        $employee = Employee::create([
            'first_name' => 'Maira',
            'last_name' => 'Pendiente',
            'email' => 'maira.pendiente@example.test',
            'employment_status' => 'Activo',
        ]);

        $response = $this->actingAs($actor)->put(route('systems.users.link-employee'), [
            'employee_id' => $employee->id,
            'user_id' => $target->id,
            'record_version' => $target->updated_at->toJSON(),
        ]);

        $response->assertRedirect(route('systems.users.index'));
        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'employee_id' => $employee->id,
        ]);
    }

    public function test_an_employee_cannot_be_linked_to_two_users(): void
    {
        $role = Role::create(['name' => 'Administrador de prueba']);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'users.update']));
        $actor = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $employee = Employee::create([
            'first_name' => 'Maira',
            'last_name' => 'Pendiente',
            'email' => 'maira.pendiente@example.test',
            'employment_status' => 'Activo',
        ]);
        User::factory()->create(['role_id' => $role->id, 'employee_id' => $employee->id]);
        $target = User::factory()->create(['role_id' => $role->id, 'employee_id' => null]);

        $response = $this->actingAs($actor)->from(route('systems.users.index'))->put(route('systems.users.link-employee'), [
            'employee_id' => $employee->id,
            'user_id' => $target->id,
            'record_version' => $target->updated_at->toJSON(),
        ]);

        $response->assertSessionHasErrors('employee_id');
        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'employee_id' => null,
        ]);
    }
}
