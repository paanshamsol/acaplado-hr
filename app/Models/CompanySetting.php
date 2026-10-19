<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'address', 'phone', 'email', 'logo_path', 'brand_color', 'latitude', 'longitude'])]
class CompanySetting extends Model
{
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
     * Absolute local path to the company logo, from the uploaded logo then a
     * bundled fallback image in `public/images`.
     */
    public function logoLocalPath(): ?string
    {
        if (filled($this->logo_path)) {
            $path = Storage::disk('public')->path($this->logo_path);

            if (is_file($path)) {
                return $path;
            }
        }

        foreach (['images/company-logo.png', 'images/company-logo.svg'] as $candidate) {
            if (is_file(public_path($candidate))) {
                return public_path($candidate);
            }
        }

        return null;
    }

    /**
     * Company logo as a base64 data URI, so it embeds in generated PDFs where
     * dompdf does not fetch remote URLs.
     */
    public function logoDataUri(): ?string
    {
        $path = $this->logoLocalPath();

        if (! $path) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'svg' => 'image/svg+xml',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return sprintf('data:%s;base64,%s', $mime, base64_encode((string) file_get_contents($path)));
    }

    public function operationalTimes(): HasMany
    {
        return $this->hasMany(CompanyOperationalTime::class, 'company_settings_id')->orderBy('start_time');
    }
}
