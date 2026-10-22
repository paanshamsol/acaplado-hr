<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendanceCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_admin_can_record_attendance_without_face_enrollment(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create(['status' => 'active']);

        $this->actingAs($admin)
            ->postJson(route('admin.attendance.store'), [
                'user_id' => $staff->id,
                'work_date' => '2026-10-20',
                'clock_in_time' => '17:00',
                'clock_out_time' => null,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $attendance = Attendance::query()->where('user_id', $staff->id)->firstOrFail();

        $this->assertSame('2026-10-20', $attendance->work_date->toDateString());
        $this->assertSame('2026-10-20 17:00:00', $attendance->clock_in_at->format('Y-m-d H:i:s'));
        $this->assertNull($attendance->clock_out_at);
        $this->assertSame('open', $attendance->status);
    }

    public function test_admin_can_record_an_overnight_shift_with_clock_out_on_the_next_day(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create(['status' => 'active']);

        $this->actingAs($admin)
            ->postJson(route('admin.attendance.store'), [
                'user_id' => $staff->id,
                'work_date' => '2026-10-20',
                'clock_in_time' => '17:00',
                'clock_out_time' => '03:30',
            ])
            ->assertOk();

        $attendance = Attendance::query()->where('user_id', $staff->id)->firstOrFail();

        $this->assertSame('2026-10-21 03:30:00', $attendance->clock_out_at->format('Y-m-d H:i:s'));
        $this->assertSame(630, $attendance->worked_minutes);
        $this->assertSame('closed', $attendance->status);
    }

    public function test_admin_can_update_an_attendance_record(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create(['status' => 'active']);

        $attendance = Attendance::create([
            'user_id' => $staff->id,
            'work_date' => '2026-10-20',
            'clock_in_at' => '2026-10-20 17:00:00',
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->putJson(route('admin.attendance.update', $attendance), [
                'user_id' => $staff->id,
                'work_date' => '2026-10-20',
                'clock_in_time' => '17:15',
                'clock_out_time' => '02:00',
            ])
            ->assertOk();

        $attendance->refresh();

        $this->assertSame('2026-10-20 17:15:00', $attendance->clock_in_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-21 02:00:00', $attendance->clock_out_at->format('Y-m-d H:i:s'));
        $this->assertSame(525, $attendance->worked_minutes);
        $this->assertSame('closed', $attendance->status);
    }

    public function test_clearing_the_clock_out_reopens_the_record_and_clears_capture_details(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create(['status' => 'active']);

        $attendance = Attendance::create([
            'user_id' => $staff->id,
            'work_date' => '2026-10-20',
            'clock_in_at' => '2026-10-20 17:00:00',
            'clock_out_at' => '2026-10-21 02:00:00',
            'clock_out_photo_path' => 'attendance/out.jpg',
            'clock_out_face_score' => 0.98,
            'clock_out_ip' => '127.0.0.1',
            'worked_minutes' => 540,
            'status' => 'closed',
        ]);

        $this->actingAs($admin)
            ->putJson(route('admin.attendance.update', $attendance), [
                'user_id' => $staff->id,
                'work_date' => '2026-10-20',
                'clock_in_time' => '17:00',
                'clock_out_time' => null,
            ])
            ->assertOk();

        $attendance->refresh();

        $this->assertNull($attendance->clock_out_at);
        $this->assertNull($attendance->clock_out_photo_path);
        $this->assertNull($attendance->clock_out_face_score);
        $this->assertNull($attendance->clock_out_ip);
        $this->assertNull($attendance->worked_minutes);
        $this->assertSame('open', $attendance->status);
    }

    public function test_admin_can_delete_an_attendance_record_and_its_photos(): void
    {
        Storage::fake('public');

        $admin = $this->admin();
        $staff = User::factory()->create(['status' => 'active']);

        Storage::disk('public')->put('attendance/in.jpg', 'in');
        Storage::disk('public')->put('attendance/out.jpg', 'out');

        $attendance = Attendance::create([
            'user_id' => $staff->id,
            'work_date' => '2026-10-20',
            'clock_in_at' => '2026-10-20 17:00:00',
            'clock_in_photo_path' => 'attendance/in.jpg',
            'clock_out_at' => '2026-10-21 02:00:00',
            'clock_out_photo_path' => 'attendance/out.jpg',
            'status' => 'closed',
        ]);

        $this->actingAs($admin)
            ->deleteJson(route('admin.attendance.destroy', $attendance))
            ->assertOk();

        $this->assertDatabaseMissing('attendances', ['id' => $attendance->id]);
        Storage::disk('public')->assertMissing('attendance/in.jpg');
        Storage::disk('public')->assertMissing('attendance/out.jpg');
    }

    public function test_a_staff_member_cannot_have_two_records_on_the_same_day(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create(['status' => 'active']);

        Attendance::create([
            'user_id' => $staff->id,
            'work_date' => '2026-10-20',
            'clock_in_at' => '2026-10-20 17:00:00',
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.attendance.store'), [
                'user_id' => $staff->id,
                'work_date' => '2026-10-20',
                'clock_in_time' => '18:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('work_date');

        $this->assertSame(1, Attendance::query()->count());
    }

    public function test_attendance_cannot_be_recorded_for_admin_or_hr_accounts(): void
    {
        $admin = $this->admin();

        $hr = User::factory()->create(['status' => 'active']);
        $hr->assignRole('hr');

        $this->actingAs($admin)
            ->postJson(route('admin.attendance.store'), [
                'user_id' => $hr->id,
                'work_date' => '2026-10-20',
                'clock_in_time' => '09:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');

        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_staff_users_cannot_manage_attendance_records(): void
    {
        $staff = User::factory()->create(['status' => 'active']);
        $staff->assignRole('staff');

        $this->actingAs($staff)
            ->postJson(route('admin.attendance.store'), [
                'user_id' => $staff->id,
                'work_date' => '2026-10-20',
                'clock_in_time' => '09:00',
            ])
            ->assertForbidden();
    }

    public function test_attendance_times_must_use_a_valid_time_format(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create(['status' => 'active']);

        $this->actingAs($admin)
            ->postJson(route('admin.attendance.store'), [
                'user_id' => $staff->id,
                'work_date' => '2026-10-20',
                'clock_in_time' => '5pm',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('clock_in_time');
    }

    public function test_attendance_page_exposes_manual_entry_controls_to_managers(): void
    {
        $admin = $this->admin();
        User::factory()->create(['status' => 'active']);

        $this->actingAs($admin)
            ->get(route('admin.attendance.index'))
            ->assertOk()
            ->assertSee('Add record')
            ->assertSee('canManage', false)
            ->assertSee(route('admin.attendance.store'), false);
    }

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }
}
