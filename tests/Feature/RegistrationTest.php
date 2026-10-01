<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_registration_screen_cannot_be_rendered_if_support_is_disabled(): void
    {
        if (Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is enabled.');
        }

        $response = $this->get('/register');

        $response->assertStatus(404);
    }

    public function test_new_users_can_register(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $branch = Branch::create([
            'name' => 'Sucursal de prueba',
            'slug' => 'sucursal-de-prueba',
            'active' => true,
        ]);
        $role = Role::create(['name' => 'Vendedor']);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
            'branch_id' => $branch->id,
        ]);

        $this->assertGuest();
        $this->assertDatabaseHas(User::class, [
            'email' => 'test@example.com',
            'branch_id' => $branch->id,
            'role_id' => $role->id,
        ]);
        $response->assertRedirect(route('register', absolute: false));
        $response->assertSessionHas('success', 'Usuario registrado correctamente');
    }

    public function test_public_registration_rejects_role_id_tampering(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $branch = Branch::create([
            'name' => 'Sucursal de prueba',
            'slug' => 'sucursal-de-prueba',
            'active' => true,
        ]);
        Role::create(['name' => 'Vendedor']);
        $superAdministrator = Role::create(['name' => 'Super Administrador']);

        $response = $this->from('/register')->post('/register', [
            'name' => 'Malicious User',
            'email' => 'malicious@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
            'branch_id' => $branch->id,
            'role_id' => $superAdministrator->id,
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('role_id');
        $this->assertDatabaseMissing(User::class, [
            'email' => 'malicious@example.com',
        ]);
    }
}
