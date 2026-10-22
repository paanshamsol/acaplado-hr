<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\CompanySetting;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FixOvernightWorkDates extends Command
{
    protected $signature = 'hr:fix-overnight-work-dates
                            {--apply : Save the corrections instead of only listing them}
                            {--employee= : Only correct records for this employee_id}';

    protected $description = 'Move after-midnight clock-ins saved on the next calendar day back to their overnight shift day';

    public function handle(): int
    {
        $settings = CompanySetting::current();
        $windows = $settings->operationalTimes()->get();

        if (! $windows->contains(fn ($window) => $window->isOvernight())) {
            $this->components->warn('No overnight operational window is configured, so no work dates need correcting.');

            return self::SUCCESS;
        }

        $query = Attendance::query()
            ->with('user:id,employee_id,name')
            ->whereNotNull('clock_in_at');

        if (filled($employee = $this->option('employee'))) {
            $query->whereHas('user', fn ($query) => $query->where('employee_id', $employee));
        }

        $attendances = $query->orderBy('work_date')->orderBy('clock_in_at')->get();
        $moves = [];
        $conflicts = [];

        foreach ($attendances->groupBy('user_id') as $rows) {
            $byDate = $rows->keyBy(fn (Attendance $attendance) => $attendance->work_date->toDateString());

            foreach ($rows as $attendance) {
                $current = $attendance->work_date->toDateString();
                $expected = $this->serviceDay($settings, $attendance);

                if ($expected === $current) {
                    continue;
                }

                $occupant = $byDate->get($expected);

                if ($occupant !== null && $this->serviceDay($settings, $occupant) === $expected) {
                    $conflicts[] = [$attendance, $current, $expected];

                    continue;
                }

                $moves[] = ['attendance' => $attendance, 'from' => $current, 'to' => $expected];
            }
        }

        if ($moves === [] && $conflicts === []) {
            $this->components->info('All attendance work dates already match the overnight shift day.');

            return self::SUCCESS;
        }

        if ($moves !== []) {
            $this->table(
                ['ID', 'Employee', 'Staff', 'Clock-in', 'Work date', 'Corrected'],
                array_map(fn (array $move) => [
                    $move['attendance']->id,
                    $move['attendance']->user?->employee_id,
                    $move['attendance']->user?->name,
                    $move['attendance']->clock_in_at->format('Y-m-d H:i'),
                    $move['from'],
                    $move['to'],
                ], $moves),
            );
        }

        if ($conflicts !== []) {
            $this->table(
                ['ID', 'Employee', 'Clock-in', 'Work date', 'Wanted date'],
                array_map(fn (array $conflict) => [
                    $conflict[0]->id,
                    $conflict[0]->user?->employee_id,
                    $conflict[0]->clock_in_at->format('Y-m-d H:i'),
                    $conflict[1],
                    $conflict[2],
                ], $conflicts),
            );

            $this->components->warn(sprintf(
                '%d record(s) were skipped because the corrected date already has an attendance row.',
                count($conflicts),
            ));
        }

        if (! $this->option('apply')) {
            $this->components->warn('Dry run: nothing was changed. Re-run with --apply to save these corrections.');

            return self::SUCCESS;
        }

        usort($moves, fn (array $a, array $b) => strcmp($a['from'], $b['from']));

        DB::transaction(function () use ($moves): void {
            foreach ($moves as $move) {
                Attendance::query()
                    ->whereKey($move['attendance']->id)
                    ->update(['work_date' => $move['to']]);
            }
        });

        $this->components->info(sprintf('Corrected %d attendance record(s).', count($moves)));

        return self::SUCCESS;
    }

    private function serviceDay(CompanySetting $settings, Attendance $attendance): string
    {
        return $settings->attendanceWorkDateFor(Carbon::parse($attendance->clock_in_at))->toDateString();
    }
}
