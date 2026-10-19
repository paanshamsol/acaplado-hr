<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
