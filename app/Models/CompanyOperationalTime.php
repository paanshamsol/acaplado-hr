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
     * The early clock-out boundary for this window: when the window runs past
     * midnight this is the end time plus the kiosk close padding, otherwise
     * null. A punch at or before this time still counts as the previous day's
     * shift (e.g. 17:00 - 06:00 with a 90 minute close belongs to the previous
     * day until 07:30).
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
            ->addMinutes($end->hour * 60 + $end->minute + self::closeAfterEndMinutes());
    }

    /**
     * The window during which the kiosk accepts punches for the shift anchored
     * to `$date`, padded before the start and after the end. Overnight shifts
     * end on the following day.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function kioskWindow(Carbon $date): array
    {
        $start = $date->copy()->startOfDay()->addMinutes($this->minutesFrom($this->start_time));
        $end = $date->copy()->startOfDay()->addMinutes($this->minutesFrom($this->end_time));

        if ($this->isOvernight()) {
            $end->addDay();
        }

        return [
            $start->subMinutes(self::openBeforeStartMinutes()),
            $end->addMinutes(self::closeAfterEndMinutes()),
        ];
    }

    private function minutesFrom(?string $time): int
    {
        if (blank($time)) {
            return 0;
        }

        $parsed = Carbon::createFromTimeString($time);

        return $parsed->hour * 60 + $parsed->minute;
    }

    private static function openBeforeStartMinutes(): int
    {
        return (int) config('hr.attendance_open_before_start_minutes', 90);
    }

    private static function closeAfterEndMinutes(): int
    {
        return (int) config('hr.attendance_close_after_end_minutes', 90);
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
