<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\CompanyOperationalTime;
use App\Models\CompanySetting;
use App\Models\User;
use App\Support\AttendanceBreakdown;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_admin_can_fetch_daily_attendance_data(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $staff = User::factory()->create(['status' => 'active']);

        Attendance::create([
            'user_id' => $staff->id,
            'work_date' => today(),
            'clock_in_at' => now()->subHours(3),
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.attendance.data', ['date' => today()->toDateString()]))
            ->assertOk()
            ->assertJsonStructure(['mode', 'rows', 'stats'])
            ->assertJsonPath('stats.present', 1);
    }

    public function test_admin_can_fetch_monthly_attendance_data(): void
    {
        $admin = User::factory()->create(['name' => 'ZZZ Admin']);
        $admin->assignRole('admin');

        $staff = User::factory()->create(['name' => 'AAA Staff', 'status' => 'active']);

        Attendance::create([
            'user_id' => $staff->id,
            'work_date' => today(),
            'clock_in_at' => now()->subHours(6),
            'clock_out_at' => now(),
            'worked_minutes' => 360,
            'status' => 'closed',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.attendance.data', ['mode' => 'month', 'month' => today()->format('Y-m')]))
            ->assertOk()
            ->assertJsonStructure(['mode', 'rows', 'stats'])
            ->assertJsonPath('mode', 'month')
            ->assertJsonPath('rows.0.name', 'AAA Staff')
            ->assertJsonPath('stats.records', 1)
            ->assertJsonPath('stats.completed', 1);
    }

    public function test_admin_can_fetch_weekly_attendance_data(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $staff = User::factory()->create(['name' => 'AAA Staff', 'status' => 'active']);

        Attendance::create([
            'user_id' => $staff->id,
            'work_date' => today(),
            'clock_in_at' => now()->subHours(6),
            'clock_out_at' => now(),
            'worked_minutes' => 360,
            'status' => 'closed',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.attendance.data', ['mode' => 'week', 'week' => now()->format('o-\WW')]))
            ->assertOk()
            ->assertJsonPath('mode', 'week')
            ->assertJsonPath('rows.0.name', 'AAA Staff')
            ->assertJsonPath('stats.records', 1)
            ->assertJsonPath('stats.open', 0);
    }

    public function test_staff_filter_limits_daily_roster_to_selected_staff(): void
    {
        $admin = User::factory()->create(['name' => 'ZZZ Admin']);
        $admin->assignRole('admin');

        $target = User::factory()->create(['name' => 'AAA Staff', 'status' => 'active']);
        User::factory()->create(['name' => 'BBB Staff', 'status' => 'active']);

        $this->setShift();

        Attendance::create([
            'user_id' => $target->id,
            'work_date' => today(),
            'clock_in_at' => today()->copy()->setTime(16, 58),
            'status' => 'open',
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.attendance.data', [
                'date' => today()->toDateString(),
                'staff' => $target->id,
            ]))
            ->assertOk()
            ->assertJsonPath('stats.total', 1)
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.name', 'AAA Staff');

        $response->assertJsonPath('rows.0.status', 'on_time');
    }

    public function test_staff_filter_limits_weekly_records_to_selected_staff(): void
    {
        $admin = User::factory()->create(['name' => 'ZZZ Admin']);
        $admin->assignRole('admin');

        $target = User::factory()->create(['name' => 'AAA Staff', 'status' => 'active']);
        $other = User::factory()->create(['name' => 'BBB Staff', 'status' => 'active']);

        $this->setShift();

        Attendance::create([
            'user_id' => $target->id,
            'work_date' => today(),
            'clock_in_at' => today()->copy()->setTime(16, 58),
            'status' => 'open',
        ]);

        Attendance::create([
            'user_id' => $other->id,
            'work_date' => today(),
            'clock_in_at' => today()->copy()->setTime(16, 59),
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.attendance.data', [
                'mode' => 'week',
                'week' => now()->format('o-\WW'),
                'staff' => $target->id,
            ]))
            ->assertOk()
            ->assertJsonPath('stats.records', 1)
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.name', 'AAA Staff');
    }

    public function test_week_filter_returns_records_for_requested_week(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $staff = User::factory()->create(['status' => 'active']);

        $lastWeek = now()->subWeek();

        Attendance::create([
            'user_id' => $staff->id,
            'work_date' => $lastWeek->toDateString(),
            'clock_in_at' => $lastWeek->copy()->setTime(9, 0),
            'status' => 'open',
        ]);

        Attendance::create([
            'user_id' => $staff->id,
            'work_date' => now()->toDateString(),
            'clock_in_at' => now()->copy()->setTime(9, 0),
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.attendance.data', [
                'mode' => 'week',
                'week' => $lastWeek->format('o-\WW'),
            ]))
            ->assertOk()
            ->assertJsonPath('stats.records', 1)
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.date', $lastWeek->format('d M Y'));
    }

    protected function setShift(string $start = '17:00', string $end = '03:00'): void
    {
        CompanyOperationalTime::create([
            'company_settings_id' => CompanySetting::current()->id,
            'label' => 'Shift',
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    public function test_clock_in_after_grace_period_is_late(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $staff = User::factory()->create(['name' => 'AAA Staff', 'status' => 'active']);

        $this->setShift();

        Attendance::create([
            'user_id' => $staff->id,
            'work_date' => today(),
            'clock_in_at' => today()->copy()->setTime(17, 10),
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.attendance.data', ['date' => today()->toDateString()]))
            ->assertOk()
            ->assertJsonPath('rows.0.status', 'late');
    }

    public function test_clock_in_within_grace_period_is_on_time(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $staff = User::factory()->create(['name' => 'AAA Staff', 'status' => 'active']);

        $this->setShift();

        Attendance::create([
            'user_id' => $staff->id,
            'work_date' => today(),
            'clock_in_at' => today()->copy()->setTime(17, 5),
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.attendance.data', ['date' => today()->toDateString()]))
            ->assertOk()
            ->assertJsonPath('rows.0.status', 'on_time');
    }

    public function test_clock_in_before_shift_start_is_on_time(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $staff = User::factory()->create(['name' => 'AAA Staff', 'status' => 'active']);

        $this->setShift();

        Attendance::create([
            'user_id' => $staff->id,
            'work_date' => today(),
            'clock_in_at' => today()->copy()->setTime(16, 55),
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.attendance.data', ['date' => today()->toDateString()]))
            ->assertOk()
            ->assertJsonPath('rows.0.status', 'on_time');
    }

    public function test_staff_with_no_clock_in_is_absent(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        User::factory()->create(['name' => 'AAA Staff', 'status' => 'active']);
        $present = User::factory()->create(['name' => 'BBB Staff', 'status' => 'active']);

        $this->setShift();

        Attendance::create([
            'user_id' => $present->id,
            'work_date' => today(),
            'clock_in_at' => today()->copy()->setTime(16, 58),
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.attendance.data', ['date' => today()->toDateString()]))
            ->assertOk()
            ->assertJsonPath('rows.0.status', 'absent');
    }

    public function test_day_with_no_records_shows_no_record_status(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        User::factory()->create(['name' => 'AAA Staff', 'status' => 'active']);

        $this->setShift();

        $this->actingAs($admin)
            ->getJson(route('admin.attendance.data', ['date' => today()->toDateString()]))
            ->assertOk()
            ->assertJsonPath('rows.0.status', 'no_record');
    }

    public function test_future_day_shows_no_record_status(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        User::factory()->create(['name' => 'AAA Staff', 'status' => 'active']);

        $this->setShift();

        $this->actingAs($admin)
            ->getJson(route('admin.attendance.data', ['date' => today()->addDay()->toDateString()]))
            ->assertOk()
            ->assertJsonPath('rows.0.status', 'no_record');
    }

    public function test_dashboard_breakdown_shows_no_record_when_no_one_has_clocked_in(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        User::factory()->create(['status' => 'active']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Not absent')
            ->assertSee('bg-amber-500')
            ->assertSee('No record');
    }

    public function test_admin_dashboard_shows_todays_attendance_breakdown(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $onTime = User::factory()->create(['status' => 'active']);
        $late = User::factory()->create(['status' => 'active']);

        $this->setShift();

        Attendance::create([
            'user_id' => $onTime->id,
            'work_date' => today(),
            'clock_in_at' => today()->copy()->setTime(16, 58),
            'status' => 'open',
        ]);

        Attendance::create([
            'user_id' => $late->id,
            'work_date' => today(),
            'clock_in_at' => today()->copy()->setTime(17, 30),
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee("Today's Attendance")
            ->assertDontSee('Not absent')
            ->assertSee('bg-green-500')
            ->assertSee('attendanceChart');
    }

    public function test_admin_dashboard_passes_staff_names_to_the_attendance_chart(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $onTime = User::factory()->create(['name' => 'On Time Person', 'status' => 'active']);
        $late = User::factory()->create(['name' => 'Late Person', 'status' => 'active']);
        $absent = User::factory()->create(['name' => 'Absent Person', 'status' => 'active']);

        $this->setShift();

        Attendance::create([
            'user_id' => $onTime->id,
            'work_date' => today(),
            'clock_in_at' => today()->copy()->setTime(16, 58),
            'status' => 'open',
        ]);

        Attendance::create([
            'user_id' => $late->id,
            'work_date' => today(),
            'clock_in_at' => today()->copy()->setTime(17, 30),
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('names:', false)
            ->assertSee('On Time Person')
            ->assertSee('Late Person')
            ->assertSee('Absent Person');
    }

    public function test_clock_in_after_the_default_late_time_is_late_without_a_shift(): void
    {
        config()->set('hr.late_clock_in', '09:00');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $staff = User::factory()->create(['name' => 'AAA Staff', 'status' => 'active']);

        Attendance::create([
            'user_id' => $staff->id,
            'work_date' => today(),
            'clock_in_at' => today()->copy()->setTime(23, 16),
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.attendance.data', ['date' => today()->toDateString()]))
            ->assertOk()
            ->assertJsonPath('rows.0.status', 'late');
    }

    public function test_clock_in_before_the_default_late_time_is_on_time_without_a_shift(): void
    {
        config()->set('hr.late_clock_in', '09:00');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $staff = User::factory()->create(['name' => 'AAA Staff', 'status' => 'active']);

        Attendance::create([
            'user_id' => $staff->id,
            'work_date' => today(),
            'clock_in_at' => today()->copy()->setTime(8, 50),
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.attendance.data', ['date' => today()->toDateString()]))
            ->assertOk()
            ->assertJsonPath('rows.0.status', 'on_time');
    }

    public function test_admin_and_hr_are_excluded_from_the_attendance_breakdown(): void
    {
        $admin = User::factory()->create(['name' => 'AAA Admin', 'status' => 'active']);
        $admin->assignRole('admin');

        $hr = User::factory()->create(['name' => 'BBB HR', 'status' => 'active']);
        $hr->assignRole('hr');

        $staff = User::factory()->create(['name' => 'CCC Staff', 'status' => 'active']);
        $staff->assignRole('staff');

        $this->setShift();

        Attendance::create([
            'user_id' => $staff->id,
            'work_date' => today(),
            'clock_in_at' => today()->copy()->setTime(16, 58),
            'status' => 'open',
        ]);

        Attendance::create([
            'user_id' => $admin->id,
            'work_date' => today(),
            'clock_in_at' => today()->copy()->setTime(10, 0),
            'status' => 'open',
        ]);

        $breakdown = AttendanceBreakdown::forDate();

        $this->assertSame(1, $breakdown['on_time']);
        $this->assertSame(0, $breakdown['absent']);
        $this->assertSame(0, $breakdown['no_record']);

        $grouped = AttendanceBreakdown::grouped();

        $this->assertTrue($grouped['on_time']->contains('id', $staff->id));
        $this->assertFalse($grouped['on_time']->contains('id', $admin->id));
        $this->assertFalse($grouped['on_time']->contains('id', $hr->id));
    }

    public function test_admin_and_hr_are_excluded_from_the_daily_roster(): void
    {
        $admin = User::factory()->create(['name' => 'AAA Admin']);
        $admin->assignRole('admin');

        $hr = User::factory()->create(['name' => 'BBB HR']);
        $hr->assignRole('hr');

        $staff = User::factory()->create(['name' => 'CCC Staff', 'status' => 'active']);
        $staff->assignRole('staff');

        $this->setShift();

        $this->actingAs($admin)
            ->getJson(route('admin.attendance.data', ['date' => today()->toDateString()]))
            ->assertOk()
            ->assertJsonPath('stats.total', 1)
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.name', 'CCC Staff');
    }

    public function test_admin_and_hr_are_excluded_from_attendance_records(): void
    {
        $admin = User::factory()->create(['name' => 'AAA Admin', 'status' => 'active']);
        $admin->assignRole('admin');

        $staff = User::factory()->create(['name' => 'CCC Staff', 'status' => 'active']);
        $staff->assignRole('staff');

        Attendance::create([
            'user_id' => $admin->id,
            'work_date' => today(),
            'clock_in_at' => today()->copy()->setTime(10, 0),
            'status' => 'open',
        ]);

        Attendance::create([
            'user_id' => $staff->id,
            'work_date' => today(),
            'clock_in_at' => today()->copy()->setTime(17, 0),
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.attendance.data', ['mode' => 'month', 'month' => today()->format('Y-m')]))
            ->assertOk()
            ->assertJsonPath('stats.records', 1)
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.name', 'CCC Staff');
    }

    public function test_admin_and_hr_are_excluded_from_the_attendance_export(): void
    {
        $admin = User::factory()->create(['name' => 'AAA Admin', 'status' => 'active']);
        $admin->assignRole('admin');

        $hr = User::factory()->create(['name' => 'BBB Hr', 'status' => 'active']);
        $hr->assignRole('hr');

        $staff = User::factory()->create(['name' => 'CCC Staff', 'status' => 'active']);
        $staff->assignRole('staff');

        foreach ([$admin, $hr, $staff] as $user) {
            Attendance::create([
                'user_id' => $user->id,
                'work_date' => today(),
                'clock_in_at' => today()->copy()->setTime(9, 0),
                'status' => 'open',
            ]);
        }

        $response = $this->actingAs($admin)->get(route('admin.attendance.export', [
            'month' => today()->month,
            'year' => today()->year,
        ]));

        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('CCC Staff', $csv);
        $this->assertStringNotContainsString('AAA Admin', $csv);
        $this->assertStringNotContainsString('BBB Hr', $csv);
    }

    public function test_attendance_page_renders_the_search_toolbar_and_responsive_records(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        User::factory()->create(['name' => 'AAA Staff', 'employee_id' => 'STAFF001', 'status' => 'active']);

        $this->actingAs($admin)
            ->get(route('admin.attendance.index'))
            ->assertOk()
            ->assertSee('id="attendance_search"', false)
            ->assertSee('attendanceBoard', false)
            ->assertSee('md:hidden', false)
            ->assertSee('Export CSV')
            ->assertSee('Reset');
    }
}
