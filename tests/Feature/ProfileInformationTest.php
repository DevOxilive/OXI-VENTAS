<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileInformationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_only_shows_the_assigned_information(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->get(route('profile.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Show')
                ->has('auth.user.employee')
                ->has('auth.user.branches'));
    }

    public function test_profile_security_and_account_management_endpoints_are_not_available(): void
    {
        $this->actingAs(User::factory()->create());

        $this->put('/user/profile-information')->assertNotFound();
        $this->put('/user/password')->assertNotFound();
        $this->post('/user/two-factor-authentication')->assertNotFound();
        $this->delete('/user/other-browser-sessions')->assertNotFound();
        $this->delete('/user')->assertNotFound();
    }
}
