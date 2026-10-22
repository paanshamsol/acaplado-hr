<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FixOvernightWorkDatesTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $employeeId = 'LADO05'): User
    {
        return User::factory()->create([
            'employee_id' => $employeeId,
            'status' => 'active',
        ]);
    }

    private function nightShift(): void
    {
        CompanySetting::current()->operationalTimes()->create([
            'label' => 'Night shift',
            'start_time' => '17:00',
            'end_time' => '06:00',
        ]);
    }

    private function attendance(User $staff, string $workDate, string $clockIn, string $status = 'open'): Attendance
    {
        return Attendance::query()->create([
            'user_id' => $staff->id,
            'work_date' => $workDate,
            'clock_in_at' => Carbon::parse($clockIn),
            'status' => $status,
        ]);
    }

    public function test_dry_run_lists_corrections_without_changing_work_dates(): void
    {
        $staff = $this->staff();
        $this->nightShift();

        $attendance = $this->attendance($staff, '2026-10-23', '2026-10-23 00:26');

        $this->artisan('hr:fix-overnight-work-dates', ['--employee' => 'LADO05'])
            ->assertSuccessful();

        $this->assertSame('2026-10-23', $attendance->fresh()->work_date->toDateString());
    }

    public function test_apply_moves_an_after_midnight_clock_in_to_the_previous_day(): void
    {
        $staff = $this->staff();
        $this->nightShift();

        $attendance = $this->attendance($staff, '2026-10-23', '2026-10-23 00:26');

        $this->artisan('hr:fix-overnight-work-dates', ['--apply' => true])
            ->assertSuccessful();

        $attendance->refresh();

        $this->assertSame('2026-10-22', $attendance->work_date->toDateString());
        $this->assertSame('2026-10-23 00:26', $attendance->clock_in_at->format('Y-m-d H:i'));
    }

    public function test_apply_leaves_correct_records_alone(): void
    {
        $staff = $this->staff();
        $this->nightShift();

        $correct = $this->attendance($staff, '2026-10-22', '2026-10-22 17:40', 'closed');

        $this->artisan('hr:fix-overnight-work-dates', ['--apply' => true])
            ->assertSuccessful();

        $this->assertSame('2026-10-22', $correct->fresh()->work_date->toDateString());
    }

    public function test_apply_skips_records_whose_corrected_date_is_already_taken(): void
    {
        $staff = $this->staff();
        $this->nightShift();

        $existing = $this->attendance($staff, '2026-10-22', '2026-10-22 17:40', 'closed');
        $wrong = $this->attendance($staff, '2026-10-23', '2026-10-23 00:26');

        $this->artisan('hr:fix-overnight-work-dates', ['--apply' => true])
            ->expectsOutputToContain('skipped')
            ->assertSuccessful();

        $this->assertSame('2026-10-22', $existing->fresh()->work_date->toDateString());
        $this->assertSame('2026-10-23', $wrong->fresh()->work_date->toDateString());
    }

    public function test_employee_option_limits_the_correction_to_one_staff_member(): void
    {
        $this->nightShift();

        $lado = $this->staff('LADO05');
        $other = $this->staff('OTHER01');

        $ladoAttendance = $this->attendance($lado, '2026-10-23', '2026-10-23 00:26');
        $otherAttendance = $this->attendance($other, '2026-10-23', '2026-10-23 00:30');

        $this->artisan('hr:fix-overnight-work-dates', ['--apply' => true, '--employee' => 'LADO05'])
            ->assertSuccessful();

        $this->assertSame('2026-10-22', $ladoAttendance->fresh()->work_date->toDateString());
        $this->assertSame('2026-10-23', $otherAttendance->fresh()->work_date->toDateString());
    }

    public function test_it_does_nothing_when_no_overnight_window_is_configured(): void
    {
        $staff = $this->staff();

        CompanySetting::current()->operationalTimes()->create([
            'label' => 'Day shift',
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $attendance = $this->attendance($staff, '2026-10-23', '2026-10-23 00:26');

        $this->artisan('hr:fix-overnight-work-dates', ['--apply' => true])
            ->assertSuccessful();

        $this->assertSame('2026-10-23', $attendance->fresh()->work_date->toDateString());
    }
}
