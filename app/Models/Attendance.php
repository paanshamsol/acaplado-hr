<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'user_id',
    'work_date',
    'clock_in_at',
    'clock_in_photo_path',
    'clock_in_face_score',
    'clock_in_ip',
    'clock_out_at',
    'clock_out_photo_path',
    'clock_out_face_score',
    'clock_out_ip',
    'worked_minutes',
    'status',
])]
class Attendance extends Model
{
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'clock_in_at' => 'datetime',
            'clock_out_at' => 'datetime',
            'clock_in_face_score' => 'decimal:4',
            'clock_out_face_score' => 'decimal:4',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Store the attendance day as a plain Y-m-d date (SQLite would otherwise
     * keep the model's default Y-m-d H:i:s format, which breaks date-range
     * comparisons against a date column).
     */
    protected function workDate(): Attribute
    {
        return Attribute::set(function ($value) {
            if ($value === null || $value === '') {
                return $value;
            }

            return $value instanceof \DateTimeInterface
                ? Carbon::instance($value)->toDateString()
                : Carbon::parse($value)->toDateString();
        });
    }

    public function isOpen(): bool
    {
        return $this->clock_in_at !== null && $this->clock_out_at === null;
    }
}
