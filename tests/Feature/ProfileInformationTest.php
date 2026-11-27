<?php

namespace Tests\Feature;

use App\Models\User;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileInformationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_shared_props_does_not_read_unused_navigation_data(): void
    {
        $user = User::factory()->create()->fresh();
        $request = Request::create('/user/profile');
        $request->setUserResolver(fn () => $user);
        DB::enableQueryLog();
        try {
            $props = app(HandleInertiaRequests::class)->share($request);
            $this->assertCount(0, DB::getQueryLog());
            $branches = $props['branches']();
            DB::flushQueryLog();
            $this->assertSame($branches, $props['sidebarBranches']());
            $this->assertCount(0, DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

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
