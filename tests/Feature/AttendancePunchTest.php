<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\CompanySetting;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        ])->assertOk()->assertJsonPath('photo', Storage::disk('public')->url('profiles/avatar.jpg'));
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

    public function test_overnight_clock_out_after_midnight_closes_the_previous_day(): void
    {
        $staff = $this->staff();

        CompanySetting::current()->operationalTimes()->create([
            'label' => 'Night shift',
            'start_time' => '17:00',
            'end_time' => '03:30',
        ]);

        $this->travelTo(Carbon::parse('2026-10-20 17:40:00'));

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertOk()->assertJson(['ok' => true, 'type' => 'in']);

        $this->travelTo(Carbon::parse('2026-10-21 00:30:00'));

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertOk()->assertJson(['ok' => true, 'type' => 'out']);

        $this->assertDatabaseCount('attendances', 1);

        $attendance = Attendance::query()->sole();

        $this->assertSame('2026-10-20', $attendance->work_date->toDateString());
        $this->assertSame('closed', $attendance->status);
        $this->assertSame('2026-10-21 00:30', $attendance->clock_out_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-20 17:40', $attendance->clock_in_at->format('Y-m-d H:i'));
        $this->assertSame(410, $attendance->worked_minutes);
    }

    public function test_open_previous_day_is_closed_even_past_the_grace_period(): void
    {
        $staff = $this->staff();

        CompanySetting::current()->operationalTimes()->create([
            'label' => 'Night shift',
            'start_time' => '17:00',
            'end_time' => '03:30',
        ]);

        $this->travelTo(Carbon::parse('2026-10-20 17:40:00'));

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertOk()->assertJson(['type' => 'in']);

        $this->travelTo(Carbon::parse('2026-10-21 04:45:00'));

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertOk()->assertJson(['type' => 'out']);

        $this->assertDatabaseCount('attendances', 1);

        $attendance = Attendance::query()->sole();

        $this->assertSame('2026-10-20', $attendance->work_date->toDateString());
        $this->assertSame('closed', $attendance->status);
        $this->assertSame('2026-10-21 04:45', $attendance->clock_out_at->format('Y-m-d H:i'));
        $this->assertSame($staff->id, $attendance->user_id);
    }

    public function test_punch_within_the_cooldown_closes_the_previous_shift(): void
    {
        $this->staff();

        CompanySetting::current()->operationalTimes()->create([
            'label' => 'Night shift',
            'start_time' => '17:00',
            'end_time' => '03:30',
        ]);

        $this->travelTo(Carbon::parse('2026-10-20 17:40:00'));

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertOk()->assertJson(['type' => 'in']);

        $this->travelTo(Carbon::parse('2026-10-21 04:10:00'));

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertOk()->assertJson(['type' => 'out']);

        $this->assertDatabaseCount('attendances', 1);

        $attendance = Attendance::query()->sole();

        $this->assertSame('2026-10-20', $attendance->work_date->toDateString());
        $this->assertSame('closed', $attendance->status);
    }

    public function test_clock_in_within_six_hours_of_a_clock_out_is_rejected(): void
    {
        $this->staff();

        CompanySetting::current()->operationalTimes()->create([
            'label' => 'Day shift',
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $this->travelTo(Carbon::parse('2026-10-20 09:00:00'));

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertOk()->assertJson(['type' => 'in']);

        $this->travelTo(Carbon::parse('2026-10-20 17:00:00'));

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertOk()->assertJson(['type' => 'out']);

        $this->travelTo(Carbon::parse('2026-10-20 18:00:00'));

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertStatus(422)->assertJson(['ok' => false, 'code' => 'cooldown']);

        $this->assertDatabaseCount('attendances', 1);
        $this->assertSame('closed', Attendance::query()->sole()->status);
    }

    public function test_clock_in_after_the_six_hour_cooldown_is_a_new_shift(): void
    {
        $staff = $this->staff();

        CompanySetting::current()->operationalTimes()->create([
            'label' => 'Day shift',
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $this->travelTo(Carbon::parse('2026-10-20 09:00:00'));

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertOk()->assertJson(['type' => 'in']);

        $this->travelTo(Carbon::parse('2026-10-20 17:00:00'));

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertOk()->assertJson(['type' => 'out']);

        $this->travelTo(Carbon::parse('2026-10-21 09:00:00'));

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertOk()->assertJson(['type' => 'in']);

        $this->assertDatabaseCount('attendances', 2);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $staff->id,
            'work_date' => '2026-10-21',
            'status' => 'open',
        ]);
    }

    public function test_overnight_clock_out_within_the_grace_period_closes_the_previous_day(): void
    {
        $staff = $this->staff();

        CompanySetting::current()->operationalTimes()->create([
            'label' => 'Night shift',
            'start_time' => '17:00',
            'end_time' => '03:30',
        ]);

        $this->travelTo(Carbon::parse('2026-10-20 17:40:00'));

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertOk()->assertJson(['type' => 'in']);

        $this->travelTo(Carbon::parse('2026-10-21 03:45:00'));

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertOk()->assertJson(['type' => 'out']);

        $this->assertDatabaseCount('attendances', 1);

        $attendance = Attendance::query()->sole();

        $this->assertSame('2026-10-20', $attendance->work_date->toDateString());
        $this->assertSame('closed', $attendance->status);
        $this->assertSame('2026-10-21 03:45', $attendance->clock_out_at->format('Y-m-d H:i'));
        $this->assertSame($staff->id, $attendance->user_id);
    }

    public function test_after_midnight_punch_without_a_shift_configured_still_closes_the_open_previous_day(): void
    {
        $staff = $this->staff();

        $this->travelTo(Carbon::parse('2026-10-20 17:40:00'));

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertOk()->assertJson(['type' => 'in']);

        $this->travelTo(Carbon::parse('2026-10-21 00:30:00'));

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertOk()->assertJson(['type' => 'out']);

        $this->assertDatabaseCount('attendances', 1);

        $attendance = Attendance::query()->sole();

        $this->assertSame('2026-10-20', $attendance->work_date->toDateString());
        $this->assertSame('closed', $attendance->status);
        $this->assertSame($staff->id, $attendance->user_id);
    }

    public function test_after_midnight_clock_in_with_no_open_record_starts_a_new_day(): void
    {
        $this->staff();

        $this->travelTo(Carbon::parse('2026-10-21 00:30:00'));

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertOk()->assertJson(['type' => 'in']);

        $this->assertDatabaseCount('attendances', 1);

        $this->assertDatabaseHas('attendances', [
            'work_date' => '2026-10-21',
            'status' => 'open',
        ]);
    }

    public function test_after_midnight_clock_in_without_an_open_record_uses_the_overnight_day(): void
    {
        $this->staff();

        CompanySetting::current()->operationalTimes()->create([
            'label' => 'Night shift',
            'start_time' => '17:00',
            'end_time' => '06:00',
        ]);

        $this->travelTo(Carbon::parse('2026-10-23 00:30:00'));

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertOk()->assertJson(['ok' => true, 'type' => 'in']);

        $this->assertDatabaseCount('attendances', 1);

        $attendance = Attendance::query()->sole();

        $this->assertSame('2026-10-22', $attendance->work_date->toDateString());
        $this->assertSame('2026-10-23 00:30', $attendance->clock_in_at->format('Y-m-d H:i'));
        $this->assertSame('open', $attendance->status);
    }

    public function test_clock_in_at_the_kiosk_open_time_uses_that_day(): void
    {
        $this->staff();

        CompanySetting::current()->operationalTimes()->create([
            'label' => 'Night shift',
            'start_time' => '17:00',
            'end_time' => '06:00',
        ]);

        $this->travelTo(Carbon::parse('2026-10-22 15:30:00'));

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertOk()->assertJson(['ok' => true, 'type' => 'in']);

        $this->assertSame('2026-10-22', Attendance::query()->sole()->work_date->toDateString());
    }

    public function test_clock_in_before_the_kiosk_opens_is_rejected(): void
    {
        $this->staff();

        CompanySetting::current()->operationalTimes()->create([
            'label' => 'Night shift',
            'start_time' => '17:00',
            'end_time' => '06:00',
        ]);

        $this->travelTo(Carbon::parse('2026-10-22 15:29:00'));

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertStatus(422)->assertJson(['ok' => false, 'code' => 'outside_hours']);

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_clock_in_after_the_kiosk_closes_is_rejected(): void
    {
        $this->staff();

        CompanySetting::current()->operationalTimes()->create([
            'label' => 'Night shift',
            'start_time' => '17:00',
            'end_time' => '06:00',
        ]);

        $this->travelTo(Carbon::parse('2026-10-23 07:31:00'));

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertStatus(422)->assertJson(['ok' => false, 'code' => 'outside_hours']);

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_clock_in_at_seven_still_uses_the_previous_day(): void
    {
        $this->staff();

        CompanySetting::current()->operationalTimes()->create([
            'label' => 'Night shift',
            'start_time' => '17:00',
            'end_time' => '06:00',
        ]);

        $this->travelTo(Carbon::parse('2026-10-23 07:00:00'));

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertOk()->assertJson(['ok' => true, 'type' => 'in']);

        $this->assertSame('2026-10-22', Attendance::query()->sole()->work_date->toDateString());
    }

    public function test_clock_out_at_the_kiosk_close_closes_the_previous_day(): void
    {
        $this->staff();

        CompanySetting::current()->operationalTimes()->create([
            'label' => 'Night shift',
            'start_time' => '17:00',
            'end_time' => '06:00',
        ]);

        $this->travelTo(Carbon::parse('2026-10-22 17:40:00'));

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertOk()->assertJson(['type' => 'in']);

        $this->travelTo(Carbon::parse('2026-10-23 07:30:00'));

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertOk()->assertJson(['type' => 'out']);

        $attendance = Attendance::query()->sole();

        $this->assertSame('2026-10-22', $attendance->work_date->toDateString());
        $this->assertSame('closed', $attendance->status);
    }

    public function test_clock_out_after_the_kiosk_closes_is_rejected(): void
    {
        $this->staff();

        CompanySetting::current()->operationalTimes()->create([
            'label' => 'Night shift',
            'start_time' => '17:00',
            'end_time' => '06:00',
        ]);

        $this->travelTo(Carbon::parse('2026-10-22 17:40:00'));

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertOk()->assertJson(['type' => 'in']);

        $this->travelTo(Carbon::parse('2026-10-23 07:31:00'));

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertStatus(422)->assertJson(['ok' => false, 'code' => 'outside_hours']);

        $this->assertSame('open', Attendance::query()->sole()->status);
    }

    public function test_same_day_second_punch_is_still_a_clock_out(): void
    {
        $this->staff();

        CompanySetting::current()->operationalTimes()->create([
            'label' => 'Night shift',
            'start_time' => '17:00',
            'end_time' => '03:30',
        ]);

        $this->travelTo(Carbon::parse('2026-10-20 23:40:00'));

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertOk()->assertJson(['type' => 'in']);

        $this->travelTo(Carbon::parse('2026-10-20 23:59:00'));

        $this->postJson(route('attendance.punch'), [
            'staff_id' => 'STAFF001',
            'password' => 'password',
            'descriptor' => $this->descriptor(),
        ])->assertOk()->assertJson(['type' => 'out']);

        $this->assertDatabaseCount('attendances', 1);
        $this->assertSame('closed', Attendance::query()->sole()->status);
    }
}
