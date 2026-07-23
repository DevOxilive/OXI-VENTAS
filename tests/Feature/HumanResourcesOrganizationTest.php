<?php

namespace Tests\Feature;

use App\Events\OrganizationStructureChanged;
use App\Events\RealtimeActivityLogged;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HumanResourcesOrganizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([
            OrganizationStructureChanged::class,
            RealtimeActivityLogged::class,
        ]);
    }

    public function test_authorized_human_resources_user_can_create_departments_and_positions(): void
    {
        $user = $this->authorizedUser([
            'departments.create',
            'positions.create',
        ]);
        $this->actingAs($user)
            ->post(route('human-resources.departments.store'), [
                'name' => 'Capital Humano',
            ])
            ->assertRedirect();

        $department = Department::query()->where('name', 'Capital Humano')->firstOrFail();

        $this->actingAs($user)
            ->post(route('human-resources.positions.store'), [
                'name' => 'Auxiliar de Capital Humano',
                'description' => 'Apoya el registro del personal.',
                'departmentId' => $department->id,
                'active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('positions', [
            'name' => 'Auxiliar de Capital Humano',
            'department_id' => $department->id,
        ]);
    }

    public function test_employee_uses_the_position_relationship_and_reflects_renamed_catalog_values(): void
    {
        $user = $this->authorizedUser(['employees.view', 'employees.create']);
        $department = Department::create([
            'name' => 'Ventas',
            'active' => true,
        ]);
        $position = Position::create([
            'name' => 'Asistente de ventas',
            'department_id' => $department->id,
            'active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('human-resources.employees.store'), [
                'firstName' => 'María',
                'lastName' => 'López',
                'email' => 'maria.lopez@example.com',
                'departmentId' => $department->id,
                'positionId' => $position->id,
                'employmentStatus' => 'Activo',
            ])
            ->assertRedirect();

        $employee = Employee::query()->where('email', 'maria.lopez@example.com')->firstOrFail();
        $this->assertSame($position->id, $employee->position_id);

        $department->update(['name' => 'Comercial']);
        $position->update(['name' => 'Ejecutiva comercial']);

        $this->actingAs($user)
            ->get(route('human-resources.employees.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('HumanResources/Employees')
                ->where('employeesDB.data.0.position', 'Ejecutiva comercial')
                ->where('employeesDB.data.0.department', 'Comercial'));
    }

    public function test_position_with_assigned_employees_cannot_be_deleted(): void
    {
        $user = $this->authorizedUser(['positions.delete']);
        $department = Department::create([
            'name' => 'Almacén',
            'active' => true,
        ]);
        $position = Position::create([
            'name' => 'Supervisor',
            'department_id' => $department->id,
            'active' => true,
        ]);
        Employee::create([
            'first_name' => 'Carlos',
            'last_name' => 'Ramírez',
            'email' => 'carlos.ramirez@example.com',
            'position_id' => $position->id,
        ]);

        $this->actingAs($user)
            ->delete(route('human-resources.positions.destroy', $position))
            ->assertSessionHasErrors('position');

        $this->assertDatabaseHas('positions', ['id' => $position->id]);
    }

    private function authorizedUser(array $permissionNames): User
    {
        $role = Role::create(['name' => 'Recursos Humanos']);
        $permissions = collect($permissionNames)
            ->map(fn (string $name) => Permission::create(['name' => $name]));
        $role->permissions()->sync($permissions->pluck('id'));

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
