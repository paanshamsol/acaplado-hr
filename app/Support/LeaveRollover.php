<?php

namespace App\Support;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;

class LeaveRollover
{
    /**
     * Maximum unused annual leave days that carry into the next year.
     */
    public const ANNUAL_CARRY_OVER_LIMIT = 3;

    /**
     * Generate (or refresh) the entitlements for a target year based on the
     * preceding source year.
     *
     * Rules:
     * - Annual leave renews to the company default; unused days carry over,
     *   capped at {@see self::ANNUAL_CARRY_OVER_LIMIT}.
     * - Medical, emergency and bereavement leave renew to the company default
     *   each year (no carry over).
     *
     * Any days already recorded as "used" in the target year are preserved.
     *
     * @return int the number of staff members generated
     */
    public static function generate(int $targetYear, ?int $sourceYear = null): int
    {
        $sourceYear = $sourceYear ?? $targetYear - 1;

        $leaveTypes = LeaveType::query()->orderBy('id')->get();

        $staff = User::query()->orderBy('name')->get();

        $sourceBalances = LeaveBalance::query()
            ->where('year', $sourceYear)
            ->get()
            ->keyBy(fn (LeaveBalance $balance) => $balance->user_id.'-'.$balance->leave_type_id);

        foreach ($staff as $member) {
            foreach ($leaveTypes as $type) {
                $carriedOver = 0.0;

                if ($type->slug === 'annual') {
                    $source = $sourceBalances->get($member->id.'-'.$type->id);

                    $entitlement = $source ? (float) $source->entitlement : (float) $type->default_days;
                    $carried = $source ? (float) $source->carried_over : 0.0;
                    $used = $source ? (float) $source->used : 0.0;

                    $unused = $entitlement + $carried - $used;

                    $carriedOver = max(0.0, min($unused, self::ANNUAL_CARRY_OVER_LIMIT));
                }

                LeaveBalance::updateOrCreate(
                    [
                        'user_id' => $member->id,
                        'leave_type_id' => $type->id,
                        'year' => $targetYear,
                    ],
                    [
                        'entitlement' => (float) $type->default_days,
                        'carried_over' => $carriedOver,
                    ],
                );
            }
        }

        return $staff->count();
    }
}
