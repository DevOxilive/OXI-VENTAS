<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\RoleSessionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RoleSessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_configures_the_requested_timeout_for_each_role(): void
    {
        Event::fake();

        foreach ([
            'Sistemas' => 10,
            'Super Administrador' => 10,
            'Administrador' => 10,
            'Recursos Humanos' => 30,
            'Inventario' => 30,
        ] as $roleName => $minutes) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $user = User::factory()->create(['role_id' => $role->id]);

            $policy = RoleSessionPolicy::forUser($user->load('role'));

            $this->assertFalse($policy['keep_alive']);
            $this->assertSame($minutes, $policy['timeout_minutes']);
            $this->assertSame(5, $policy['warning_minutes']);
        }
    }

    public function test_confirming_before_expiration_starts_a_new_role_session_window(): void
    {
        Event::fake();
        $role = Role::firstOrCreate(['name' => 'Inventario']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)
            ->postJson(route('session.continue'))
            ->assertOk()
            ->assertJsonPath('expires_at', now()->addMinutes(30)->timestamp)
            ->assertSessionHas('role_session_expires_at');
    }

    public function test_an_expired_role_session_is_rejected_by_the_server(): void
    {
        Event::fake();
        $role = Role::firstOrCreate(['name' => 'Administrador']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)
            ->withSession([
                'role_session_policy_role' => 'Administrador',
                'role_session_expires_at' => now()->subSecond()->timestamp,
            ])
            ->postJson(route('session.continue'))
            ->assertUnauthorized();
    }

    public function test_the_policy_also_protects_authenticated_routes_outside_the_main_application_group(): void
    {
        Event::fake();
        $role = Role::firstOrCreate(['name' => 'Recursos Humanos']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)
            ->withSession([
                'role_session_policy_role' => 'Recursos Humanos',
                'role_session_expires_at' => now()->subSecond()->timestamp,
            ])
            ->get(route('profile.show'))
            ->assertRedirect(route('login'));
    }
}
