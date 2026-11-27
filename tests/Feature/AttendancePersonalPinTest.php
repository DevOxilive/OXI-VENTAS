<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AttendancePersonalPinTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_user_can_configure_a_personal_four_digit_attendance_pin(): void
    {
        $user = $this->salesUser();

        $response = $this->actingAs($user)->putJson(route('attendance.pin.update'), [
            'pin' => '4286',
            'pin_confirmation' => '4286',
        ]);

        $response->assertOk();
        $user->refresh();

        $this->assertTrue(Hash::check('4286', $user->attendance_pin_hash));
        $this->assertNotSame('4286', $user->attendance_pin_hash);
        $this->assertNotNull($user->attendance_pin_fingerprint);
        $this->assertNotNull($user->attendance_pin_set_at);
    }

    public function test_personal_attendance_pin_cannot_be_reused_by_another_sales_user(): void
    {
        $firstUser = $this->salesUser();
        $secondUser = $this->salesUser();

        $this->actingAs($firstUser)->putJson(route('attendance.pin.update'), [
            'pin' => '4286',
            'pin_confirmation' => '4286',
        ])->assertOk();

        $this->actingAs($secondUser)->putJson(route('attendance.pin.update'), [
            'pin' => '4286',
            'pin_confirmation' => '4286',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('pin');
    }

    private function salesUser(): User
    {
        $permission = Permission::firstOrCreate(['name' => 'attendance.register']);
        $role = Role::firstOrCreate(['name' => 'Ventas']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }
}
