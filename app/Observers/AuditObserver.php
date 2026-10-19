<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Payslip;
use App\Models\SalaryAdjustment;
use App\Models\User;

class AuditObserver
{
    /**
     * Models whose create/update/delete events are audited.
     *
     * @var array<int, string>
     */
    protected static array $models = [
        User::class,
        LeaveRequest::class,
        LeaveBalance::class,
        CompanySetting::class,
        Payslip::class,
        SalaryAdjustment::class,
    ];

    public function created(mixed $model): void
    {
        AuditLog::record('created', $model, null, $model->getAttributes());
    }

    public function updated(mixed $model): void
    {
        $changes = $model->getChanges();
        $original = collect($changes)->mapWithKeys(
            fn ($value, $key) => [$key => $model->getOriginal($key)],
        )->all();

        AuditLog::record('updated', $model, $original, $changes);
    }

    public function deleted(mixed $model): void
    {
        AuditLog::record('deleted', $model, $model->getAttributes(), null);
    }

    /**
     * @return array<int, string>
     */
    public static function models(): array
    {
        return static::$models;
    }
}
