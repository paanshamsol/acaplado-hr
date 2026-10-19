<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendancePunchTest extends TestCase
{
    use RefreshDatabase;

    private const DESCRIPTOR = 0.5;

    private function staff(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'employee_id' => 'STAFF001',
            'status' => 'active',
            'face_descriptor' => array_fill(0, 128, self::DESCRIPTOR),
        ], $attributes));
    }

    private function descriptor(float $value = self::DESCRIPTOR): array
    {
        return array_fill(0, 128, $value);
    }

    public function test_staff_can_clock_in_and_then_clock_out(): void
    {
        $staff = $this->staff();

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertOk()->assertJson(['ok' => true, 'type' => 'in']);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $staff->id,
            'status' => 'open',
        ]);

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertOk()->assertJson(['ok' => true, 'type' => 'out']);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $staff->id,
            'status' => 'closed',
        ]);
    }

    public function test_punch_response_includes_the_profile_photo(): void
    {
        Storage::fake('public');

        $this->staff(['profile_photo_path' => 'profiles/avatar.jpg']);

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertOk()->assertJsonPath('photo', Storage::url('profiles/avatar.jpg'));
    }

    public function test_face_mismatch_is_rejected(): void
    {
        $this->staff();

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(5.0),
        ])->assertStatus(422)->assertJson(['ok' => false, 'code' => 'mismatch']);

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_invalid_password_is_rejected(): void
    {
        $this->staff();

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'wrong-password',
            'descriptor' => $this->descriptor(),
        ])->assertStatus(422)->assertJson(['code' => 'invalid_credentials']);

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_staff_without_an_enrolled_face_cannot_punch(): void
    {
        $this->staff(['face_descriptor' => null]);

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertStatus(422)->assertJson(['code' => 'no_face']);
    }

    public function test_kiosk_page_is_accessible(): void
    {
        $this->get(route('attendance.kiosk'))->assertOk();
    }

    public function test_admin_and_hr_cannot_clock_in(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $admin = $this->staff(['employee_id' => 'ADMIN001']);
        $admin->assignRole('admin');

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'ADMIN001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertStatus(422)->assertJson(['ok' => false, 'code' => 'not_applicable']);

        $this->assertDatabaseCount('attendances', 0);
    }
}
