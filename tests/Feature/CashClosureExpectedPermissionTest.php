<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CashClosureExpectedPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_expected_amounts_are_only_sent_to_users_with_the_specific_permission(): void
    {
        $branch = Branch::create(['name' => 'Caja', 'slug' => 'caja', 'active' => true]);
        $role = Role::firstOrCreate(['name' => 'Vendedor']);
        $view = Permission::firstOrCreate(['name' => 'sales.cash-closures.view']);
        $expected = Permission::firstOrCreate(['name' => 'sales.cash-closures.view-expected']);
        $role->permissions()->attach($view);

        $withoutExpected = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $withoutExpected->branches()->attach($branch);
        $this->actingAs($withoutExpected)
            ->get(route('ventas.cash-closures.index', ['branch' => $branch->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ventas/CashRegisterClosures')
                ->missing('current.expected_cash')
                ->missing('current.card_total'));

        $withExpected = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $withExpected->branches()->attach($branch);
        $withExpected->permissions()->attach($expected, ['mode' => 'allow']);
        $this->actingAs($withExpected)
            ->get(route('ventas.cash-closures.index', ['branch' => $branch->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ventas/CashRegisterClosures')
                ->has('current.expected_cash')
                ->has('current.card_total'));
    }
}
