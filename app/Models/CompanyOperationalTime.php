<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['company_settings_id', 'label', 'start_time', 'end_time'])]
class CompanyOperationalTime extends Model
{
    /**
     * Whether this window runs past midnight (e.g. 17:00 to 03:30).
     */
    public function isOvernight(): bool
    {
        return strcmp((string) $this->end_time, (string) $this->start_time) <= 0;
    }

    /**
     * Minutes of grace after an overnight shift ends during which a punch is
     * still treated as the previous day's clock-out (e.g. staff leaving at
     * 03:45 for a shift ending 03:30).
     */
    public const OVERNIGHT_GRACE_MINUTES = 30;

    /**
     * Whether the given moment belongs to this window's *previous* day, i.e. it
     * falls between midnight and the end of an overnight shift (e.g. 00:30 for a
     * 17:00 - 03:30 shift). Such a punch is attributed to the day the shift
     * started so the open record closes correctly instead of starting a new day.
     */
    public function containsOvernightTail(Carbon $moment): bool
    {
        if (! $this->isOvernight() || blank($this->end_time)) {
            return false;
        }

        $end = Carbon::createFromTimeString($this->end_time);

        return $moment->copy()->startOfDay()
            ->addMinutes($end->hour * 60 + $end->minute + self::OVERNIGHT_GRACE_MINUTES)
            ->greaterThanOrEqualTo($moment);
    }

    /**
     * Human friendly window, e.g. "17:00 - 03:30".
     */
    public function range(): string
    {
        return "{$this->start_time} - {$this->end_time}";
    }

    public function companySetting(): BelongsTo
    {
        return $this->belongsTo(CompanySetting::class, 'company_settings_id');
    }
}
