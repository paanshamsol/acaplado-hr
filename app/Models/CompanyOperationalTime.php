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
     * The early clock-out boundary for this window: when the window runs past
     * midnight this is the end time plus the overnight grace period, otherwise
     * null. A punch at or before this time still counts as the previous day's
     * clock-out (e.g. 17:00 - 06:00 closes up to 06:30).
     *
     * When `$date` is given the boundary is anchored to that day, so historical
     * punches can be evaluated against the shift they actually belong to.
     */
    public function overnightCutoff(?Carbon $date = null): ?Carbon
    {
        if (! $this->isOvernight() || blank($this->end_time)) {
            return null;
        }

        $end = Carbon::createFromTimeString($this->end_time);

        return ($date ?? Carbon::now())->copy()->startOfDay()
            ->addMinutes($end->hour * 60 + $end->minute + self::OVERNIGHT_GRACE_MINUTES);
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
