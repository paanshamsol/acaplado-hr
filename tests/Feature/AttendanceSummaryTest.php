<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\CompanyOperationalTime;
use App\Models\CompanySetting;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_summary_groups_days_worked_and_total_hours_for_each_staff(): void
    {
        $admin = $this->admin();

        $alice = User::factory()->create(['name' => 'AAA Alice', 'status' => 'active']);
        $bob = User::factory()->create(['name' => 'BBB Bob', 'status' => 'active']);

        Attendance::create([
            'user_id' => $alice->id,
            'work_date' => today()->subDays(2)->toDateString(),
            'clock_in_at' => today()->subDays(2)->copy()->setTime(9, 0),
            'clock_out_at' => today()->subDays(2)->copy()->setTime(17, 0),
            'worked_minutes' => 480,
            'status' => 'closed',
        ]);

        Attendance::create([
            'user_id' => $alice->id,
            'work_date' => today()->subDay()->toDateString(),
            'clock_in_at' => today()->subDay()->copy()->setTime(9, 0),
            'clock_out_at' => today()->subDay()->copy()->setTime(16, 0),
            'worked_minutes' => 420,
            'status' => 'closed',
        ]);

        Attendance::create([
            'user_id' => $bob->id,
            'work_date' => today()->toDateString(),
            'clock_in_at' => today()->copy()->setTime(9, 0),
            'clock_out_at' => today()->copy()->setTime(18, 0),
            'worked_minutes' => 540,
            'status' => 'closed',
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.attendance.data', ['mode' => 'summary', 'staff_range' => 'month', 'month' => today()->format('Y-m')]))
            ->assertOk()
            ->assertJsonPath('mode', 'summary')
            ->assertJsonCount(2, 'summary')
            ->assertJsonPath('stats.staff', 2)
            ->assertJsonPath('stats.days', 3);

        $aliceSummary = collect($response->json('summary'))->firstWhere('user_id', $alice->id);

        $this->assertSame(2, $aliceSummary['days_worked']);
        $this->assertSame(900, $aliceSummary['total_minutes']);
        $this->assertSame('15h 00m', $aliceSummary['total_hours']);
        $this->assertCount(2, $aliceSummary['dates']);
        $this->assertSame(today()->subDay()->toDateString(), $aliceSummary['last_worked']);
    }

    public function test_open_shift_uses_the_shift_end_for_hours_worked(): void
    {
        $admin = $this->admin();

        $staff = User::factory()->create(['name' => 'AAA Staff', 'status' => 'active']);

        $this->setShift('17:00', '03:00');

        Attendance::create([
            'user_id' => $staff->id,
            'work_date' => today()->toDateString(),
            'clock_in_at' => today()->copy()->setTime(17, 0),
            'status' => 'open',
        ]);

        $summary = $this->actingAs($admin)
            ->getJson(route('admin.attendance.data', ['mode' => 'summary', 'staff_range' => 'month', 'month' => today()->format('Y-m')]))
            ->assertOk()
            ->json('summary.0');

        $this->assertSame(1, $summary['days_worked']);
        $this->assertSame(600, $summary['total_minutes']);
        $this->assertSame('10h 00m', $summary['total_hours']);
        $this->assertSame('03:00 AM', $summary['dates'][0]['clock_out']);
        $this->assertTrue($summary['dates'][0]['assumed_clock_out']);
        $this->assertTrue($summary['dates'][0]['open']);
        $this->assertSame(1, $summary['open_days']);
    }

    public function test_summary_includes_active_staff_who_have_not_worked_yet(): void
    {
        $admin = $this->admin();

        User::factory()->create(['name' => 'AAA Idle', 'status' => 'active']);

        $summary = $this->actingAs($admin)
            ->getJson(route('admin.attendance.data', ['mode' => 'summary', 'staff_range' => 'month', 'month' => today()->format('Y-m')]))
            ->assertOk()
            ->json('summary.0');

        $this->assertSame('AAA Idle', $summary['name']);
        $this->assertSame(0, $summary['days_worked']);
        $this->assertSame(0, $summary['total_minutes']);
        $this->assertSame('0h 00m', $summary['total_hours']);
        $this->assertCount(0, $summary['dates']);
    }

    public function test_summary_excludes_admin_and_hr_accounts(): void
    {
        $admin = $this->admin();

        $hr = User::factory()->create(['name' => 'BBB HR', 'status' => 'active']);
        $hr->assignRole('hr');

        User::factory()->create(['name' => 'CCC Staff', 'status' => 'active']);

        $this->actingAs($admin)
            ->getJson(route('admin.attendance.data', ['mode' => 'summary', 'staff_range' => 'month', 'month' => today()->format('Y-m')]))
            ->assertOk()
            ->assertJsonCount(1, 'summary')
            ->assertJsonPath('summary.0.name', 'CCC Staff');
    }

    public function test_summary_staff_filter_limits_the_group_to_one_staff_member(): void
    {
        $admin = $this->admin();

        $target = User::factory()->create(['name' => 'AAA Target', 'status' => 'active']);
        User::factory()->create(['name' => 'BBB Other', 'status' => 'active']);

        Attendance::create([
            'user_id' => $target->id,
            'work_date' => today()->toDateString(),
            'clock_in_at' => today()->copy()->setTime(9, 0),
            'clock_out_at' => today()->copy()->setTime(17, 0),
            'worked_minutes' => 480,
            'status' => 'closed',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.attendance.data', [
                'mode' => 'summary',
                'staff_range' => 'month',
                'month' => today()->format('Y-m'),
                'staff' => $target->id,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'summary')
            ->assertJsonPath('summary.0.name', 'AAA Target')
            ->assertJsonPath('label', 'AAA Target • '.now()->format('F Y'));
    }

    public function test_summary_respects_a_custom_date_range(): void
    {
        $admin = $this->admin();

        $staff = User::factory()->create(['name' => 'AAA Staff', 'status' => 'active']);

        Attendance::create([
            'user_id' => $staff->id,
            'work_date' => today()->subDays(20)->toDateString(),
            'clock_in_at' => today()->subDays(20)->copy()->setTime(9, 0),
            'clock_out_at' => today()->subDays(20)->copy()->setTime(17, 0),
            'worked_minutes' => 480,
            'status' => 'closed',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.attendance.data', [
                'mode' => 'summary',
                'staff_range' => 'custom',
                'from_date' => today()->subDays(5)->toDateString(),
                'to_date' => today()->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonPath('summary.0.days_worked', 0)
            ->assertJsonPath('stats.days', 0);
    }

    public function test_attendance_page_renders_the_summary_view_controls(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.attendance.index'))
            ->assertOk()
            ->assertSee('Staff summary', false)
            ->assertSee('Staff detail', false)
            ->assertSee('Day roster', false)
            ->assertSee('All records', false)
            ->assertSee('All staff');
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

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }
}
