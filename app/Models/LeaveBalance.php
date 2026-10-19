<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'leave_type_id', 'year', 'entitlement', 'used', 'carried_over'])]
class LeaveBalance extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'entitlement' => 'decimal:1',
            'used' => 'decimal:1',
            'carried_over' => 'decimal:1',
        ];
    }

    /**
     * Days still available (entitlement + carried over - used).
     */
    public function remaining(): float
    {
        return (float) $this->entitlement + (float) $this->carried_over - (float) $this->used;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
