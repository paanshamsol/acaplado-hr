<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'year',
    'month',
    'hourly_rate',
    'hours_of_work',
    'days_of_work',
])]
class SalaryAdjustment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'hourly_rate' => 'decimal:2',
            'hours_of_work' => 'decimal:2',
            'days_of_work' => 'integer',
        ];
    }

    public function total(): float
    {
        return (float) $this->hourly_rate * (float) $this->hours_of_work * (int) $this->days_of_work;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
