<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SalesRoleSessionKeepAliveTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_sales_role_user_can_keep_their_database_session_active(): void
    {
        Event::fake();
        $salesRole = Role::firstOrCreate(['name' => 'Ventas']);
        $salesUser = User::factory()->create(['role_id' => $salesRole->id]);

        $this->actingAs($salesUser)
            ->post(route('session.keep-alive'))
            ->assertNoContent()
            ->assertSessionHas('sales_session_keep_alive_at');
    }

    public function test_other_roles_do_not_receive_the_sales_session_exception(): void
    {
        Event::fake();
        $otherRole = Role::firstOrCreate(['name' => 'Inventario']);
        $otherUser = User::factory()->create(['role_id' => $otherRole->id]);

        $this->withoutMiddleware(\App\Http\Middleware\AuditSystemActions::class)
            ->actingAs($otherUser)
            ->post(route('session.keep-alive'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionMissing('sales_session_keep_alive_at');
    }
}
