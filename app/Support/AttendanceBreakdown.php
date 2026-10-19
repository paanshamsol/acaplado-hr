<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AttendanceBreakdown
{
    /**
     * Count active staff by derived attendance status for a given date.
     *
     * When the date has no clock-in/out records at all (e.g. a future day),
     * everyone is counted as "no record" instead of absent.
     *
     * @return array{on_time: int, late: int, absent: int, no_record: int}
     */
    public static function forDate(?Carbon $date = null): array
    {
        $grouped = self::grouped($date);

        return [
            AttendanceStatus::ON_TIME => $grouped[AttendanceStatus::ON_TIME]->count(),
            AttendanceStatus::LATE => $grouped[AttendanceStatus::LATE]->count(),
            AttendanceStatus::ABSENT => $grouped[AttendanceStatus::ABSENT]->count(),
            AttendanceStatus::NO_RECORD => $grouped[AttendanceStatus::NO_RECORD]->count(),
        ];
    }

    /**
     * Active staff grouped by their derived attendance status for a given date.
     *
     * When the date has no clock-in/out records at all (e.g. a future day),
     * everyone is grouped under "no record" instead of absent.
     *
     * @return array{on_time: Collection<int, User>, late: Collection<int, User>, absent: Collection<int, User>, no_record: Collection<int, User>}
     */
    public static function grouped(?Carbon $date = null): array
    {
        $date = $date ?: Carbon::today();

        $shiftStart = CompanySetting::current()->operationalTimes->first()?->start_time;
        $graceMinutes = (int) config('hr.late_grace_minutes', 5);

        $staff = User::query()->attendanceStaff()->where('status', 'active')->orderBy('name')->get();
        $attendances = Attendance::whereDate('work_date', $date)
            ->whereHas('user', fn ($query) => $query->attendanceStaff())
            ->get()
            ->keyBy('user_id');

        $grouped = [
            AttendanceStatus::ON_TIME => collect(),
            AttendanceStatus::LATE => collect(),
            AttendanceStatus::ABSENT => collect(),
            AttendanceStatus::NO_RECORD => collect(),
        ];

        if ($attendances->isEmpty()) {
            $grouped[AttendanceStatus::NO_RECORD] = $staff->values();

            return $grouped;
        }

        foreach ($staff as $member) {
            $status = AttendanceStatus::derive(
                $attendances->get($member->id)?->clock_in_at,
                $shiftStart,
                $graceMinutes,
            );

            $grouped[$status]->push($member);
        }

        return $grouped;
    }
}
