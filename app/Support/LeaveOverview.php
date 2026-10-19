<?php

namespace App\Support;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Support\Collection;

class LeaveOverview
{
    /**
     * Build the staff x leave-type entitlement matrix for a given year.
     *
     * @return array{year: int, leaveTypes: Collection<int, LeaveType>, staff: Collection<int, User>, balances: Collection<string, LeaveBalance>}
     */
    public static function matrix(?int $year = null): array
    {
        $year = $year ?: (int) now()->year;

        $leaveTypes = LeaveType::query()->orderBy('id')->get();

        $staff = User::query()->orderBy('name')->get();

        $balances = LeaveBalance::query()
            ->where('year', $year)
            ->whereIn('user_id', $staff->pluck('id'))
            ->get()
            ->keyBy(fn (LeaveBalance $balance) => $balance->user_id.'-'.$balance->leave_type_id);

        return [
            'year' => $year,
            'leaveTypes' => $leaveTypes,
            'staff' => $staff,
            'balances' => $balances,
        ];
    }
}
