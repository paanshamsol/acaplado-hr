<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class AttendanceStatus
{
    public const ON_TIME = 'on_time';

    public const LATE = 'late';

    public const ABSENT = 'absent';

    public const NO_RECORD = 'no_record';

    /**
     * Derive the daily attendance status from the clock-in time compared to the
     * company's operational shift start, allowing a short grace period.
     *
     * A clock-in at or before (shift start + grace) is on time; anything later
     * is late. No clock-in means absent. When no operational shift start is
     * configured, the default late clock-in time (`hr.late_clock_in`) is used.
     */
    public static function derive(?Carbon $clockInAt, ?string $shiftStart, int $graceMinutes = 5): string
    {
        if ($clockInAt === null) {
            return self::ABSENT;
        }

        $shiftStart = blank($shiftStart) ? config('hr.late_clock_in') : $shiftStart;

        if (blank($shiftStart)) {
            return self::ON_TIME;
        }

        $start = $clockInAt->copy()->setTimeFromTimeString($shiftStart);

        // Overnight shifts: a clock-in in the small hours belongs to the
        // previous day's shift and is therefore late, not "early".
        if ($clockInAt->lessThan($start->copy()->subHours(6))) {
            return self::LATE;
        }

        return $clockInAt->lessThanOrEqualTo($start->copy()->addMinutes($graceMinutes))
            ? self::ON_TIME
            : self::LATE;
    }
}
