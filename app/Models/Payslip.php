<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'user_id',
    'year',
    'month',
    'hourly_rate',
    'hours_of_work',
    'days_of_work',
    'basic',
    'allowances',
    'other_deductions',
    'net',
    'status',
    'generated_by',
])]
class Payslip extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    /**
     * Get the attributes that should be cast.
     *
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
            'basic' => 'decimal:2',
            'allowances' => 'decimal:2',
            'other_deductions' => 'decimal:2',
            'net' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Human-readable period, e.g. "January 2026".
     */
    public function periodLabel(): string
    {
        return Carbon::create(2000, $this->month, 1)->format('F').' '.$this->year;
    }

    /**
     * Limit the query to payslips that have a matching saved salary adjustment.
     *
     * These are the payslips a staff member sees in the portal: without a saved
     * adjustment the payroll record is not part of their payslip history.
     */
    public function scopeWithSavedAdjustment(Builder $query): void
    {
        $query->whereExists(function ($sub) {
            $sub->selectRaw('1')
                ->from('salary_adjustments')
                ->whereColumn('salary_adjustments.user_id', 'payslips.user_id')
                ->whereColumn('salary_adjustments.year', 'payslips.year')
                ->whereColumn('salary_adjustments.month', 'payslips.month');
        });
    }

    /**
     * Employee-borne deductions (other deductions).
     */
    public function totalEmployeeDeductions(): float
    {
        return (float) $this->other_deductions;
    }

    /**
     * Recompute the basic and net amounts from the rate / hours / days and
     * the allowances and deduction figures held on the payslip.
     */
    public function recalculate(): void
    {
        $this->basic = round((float) $this->hourly_rate * (float) $this->hours_of_work * (int) $this->days_of_work, 2);
        $this->net = round(
            (float) $this->basic
            + (float) $this->allowances
            - $this->totalEmployeeDeductions(),
            2,
        );
    }
}
