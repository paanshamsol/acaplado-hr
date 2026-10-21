<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'address', 'phone', 'email', 'logo_path', 'brand_color', 'latitude', 'longitude'])]
class CompanySetting extends Model
{
    /**
     * Minimum rest between a staff member's clock-out and the next clock-in
     * being accepted as a new work day.
     */
    public const SHIFT_COOLDOWN_HOURS = 6;

    /**
     * Default brand colour (indigo) used when none is configured.
     */
    public const DEFAULT_BRAND_COLOR = '#4f46e5';

    /**
     * Get the single company settings row, creating it if necessary.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    /**
     * Brand colour used for navigation and button accents, falling back to the
     * default when none is configured.
     */
    public function brandColor(): string
    {
        return filled($this->brand_color) ? $this->brand_color : self::DEFAULT_BRAND_COLOR;
    }

    /**
     * Resolve the company logo URL from the uploaded logo, then a bundled
     * fallback image in `public/images`.
     */
    public function logoUrl(): ?string
    {
        if (filled($this->logo_path)) {
            return Storage::disk('public')->url($this->logo_path);
        }

        foreach (['images/company-logo.png', 'images/company-logo.svg'] as $candidate) {
            if (file_exists(public_path($candidate))) {
                return asset($candidate);
            }
        }

        return null;
    }

    /**
     * Company logo as a base64 data URI, so it embeds in generated PDFs where
     * dompdf does not fetch remote URLs. Reads the uploaded logo through the
     * configured disk, falling back to a bundled image in `public/images`.
     */
    public function logoDataUri(): ?string
    {
        if (filled($this->logo_path)) {
            $bytes = Storage::disk('public')->get($this->logo_path);

            if (filled($bytes)) {
                return $this->toDataUri($bytes, $this->logo_path);
            }
        }

        foreach (['images/company-logo.png', 'images/company-logo.svg'] as $candidate) {
            if (is_file(public_path($candidate))) {
                return $this->toDataUri((string) file_get_contents(public_path($candidate)), $candidate);
            }
        }

        return null;
    }

    private function toDataUri(string $bytes, string $path): string
    {
        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'svg' => 'image/svg+xml',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return sprintf('data:%s;base64,%s', $mime, base64_encode($bytes));
    }

    public function operationalTimes(): HasMany
    {
        return $this->hasMany(CompanyOperationalTime::class, 'company_settings_id')->orderBy('start_time');
    }

    /**
     * The attendance service day the given moment belongs to.
     *
     * Overnight shifts (e.g. 17:00 - 06:00) run past midnight, so a punch made
     * between midnight and the configured shift end (plus grace) still belongs
     * to the day the shift started. Without this, an after-midnight clock-out
     * would start a brand-new attendance day and leave the previous row open.
     */
    public function attendanceWorkDateFor(Carbon $moment): Carbon
    {
        if ($this->overnightCutoffFor($moment) !== null) {
            return $moment->copy()->subDay()->startOfDay();
        }

        return $moment->copy()->startOfDay();
    }

    /**
     * The overnight early clock-out boundary that applies to the given moment:
     * the latest grace-adjusted shift end still ahead of `$moment`, or null when
     * the moment is not an overnight tail. Among overlapping windows the window
     * ending furthest from midnight wins, so the widest tail is used.
     */
    public function overnightCutoffFor(Carbon $moment): ?Carbon
    {
        $cutoff = null;
        $timeOfDay = $moment->copy()->startOfDay();
        $secondsIntoDay = $timeOfDay->diffInSeconds($moment, false);

        foreach ($this->operationalTimes()->get() as $window) {
            $candidate = $window->overnightCutoff();

            if ($candidate === null) {
                continue;
            }

            $candidateSeconds = $timeOfDay->diffInSeconds($candidate, false);

            if ($secondsIntoDay >= $candidateSeconds) {
                continue;
            }

            if ($cutoff === null || $candidate->greaterThan($cutoff)) {
                $cutoff = $candidate->copy();
            }
        }

        return $cutoff;
    }

    /**
     * The first moment a staff member clocked out on the service day `$workDate`
     * may start the next work day. Returns null when the row is still open, so
     * the overnight clock-out in progress is never treated as finished.
     *
     * Used to keep a punch made shortly after an overnight clock-out (e.g. the
     * same morning) from being treated as a fresh clock-in.
     */
    public function nextShiftStartAt(Carbon $workDate): ?Carbon
    {
        return Attendance::query()
            ->whereDate('work_date', $workDate->toDateString())
            ->whereNotNull('clock_out_at')
            ->orderBy('clock_out_at')
            ->value('clock_out_at')
            ?->copy()->addHours(self::SHIFT_COOLDOWN_HOURS);
    }
}
