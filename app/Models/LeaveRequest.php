<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'leave_type_id',
    'start_date',
    'end_date',
    'days',
    'reason',
    'attachment_path',
    'status',
    'approved_by',
    'decided_at',
    'rejection_reason',
    'remarks',
])]
class LeaveRequest extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days' => 'decimal:1',
            'decided_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function hasAttachment(): bool
    {
        return filled($this->attachment_path);
    }

    /**
     * @param  Builder<LeaveRequest>  $query
     * @return Builder<LeaveRequest>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * @param  Builder<LeaveRequest>  $query
     * @return Builder<LeaveRequest>
     */
    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->whereYear('start_date', $year);
    }
}
