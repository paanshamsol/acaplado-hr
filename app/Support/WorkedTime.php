<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\CompanyOperationalTime;
use Illuminate\Support\Carbon;

class WorkedTime
{
    /**
     * The clock-out used to calculate worked time.
     *
     * A real clock-out always wins. When a staff member clocked in but never
     * clocked out, the shift end is used instead so an open shift still counts
     * towards their days and hours worked.
     */
    public static function effectiveClockOut(Attendance $attendance, ?CompanyOperationalTime $shift): ?Carbon
    {
        if ($attendance->clock_out_at !== null) {
            return $attendance->clock_out_at->copy();
        }

        if ($attendance->clock_in_at === null || $shift === null || blank($shift->end_time)) {
            return null;
        }

        $clockOut = $attendance->work_date->copy()->startOfDay()->setTimeFromTimeString($shift->end_time);

        if ($shift->isOvernight()) {
            $clockOut->addDay();
        }

        // A clock-in after the shift already ended cannot be assumed to have
        // worked until that same shift end.
        if ($clockOut->lessThanOrEqualTo($attendance->clock_in_at)) {
            return null;
        }

        return $clockOut;
    }

    /**
     * Worked minutes for a record, using the stored total when available and
     * the shift end for a still-open shift.
     */
    public static function minutes(Attendance $attendance, ?CompanyOperationalTime $shift): int
    {
        if ($attendance->clock_in_at === null) {
            return 0;
        }

        if ($attendance->clock_out_at !== null) {
            if ($attendance->worked_minutes !== null) {
                return max(0, (int) $attendance->worked_minutes);
            }

            return max(0, (int) $attendance->clock_in_at->diffInMinutes($attendance->clock_out_at, false));
        }

        $clockOut = self::effectiveClockOut($attendance, $shift);

        return $clockOut === null
            ? 0
            : max(0, (int) $attendance->clock_in_at->diffInMinutes($clockOut, false));
    }
}
