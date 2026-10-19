<?php

namespace App\Support;

use App\Models\Payslip;
use App\Models\SalaryAdjustment;

class PayslipSync
{
    /**
     * Create or refresh the payroll record for an adjustment's staff member and
     * period, so the payslip always mirrors what was saved on the admin's
     * Salary & Payslips section.
     *
     * Rate, hours and days are taken from the adjustment; existing allowances
     * and other deductions are preserved and basic / net are recalculated.
     */
    public static function fromAdjustment(SalaryAdjustment $adjustment, ?int $generatedBy = null): Payslip
    {
        $payslip = Payslip::query()
            ->where('user_id', $adjustment->user_id)
            ->where('year', $adjustment->year)
            ->where('month', $adjustment->month)
            ->first();

        $isNew = $payslip === null;

        $payslip ??= new Payslip([
            'user_id' => $adjustment->user_id,
            'year' => $adjustment->year,
            'month' => $adjustment->month,
        ]);

        $payslip->fill([
            'hourly_rate' => $adjustment->hourly_rate,
            'hours_of_work' => $adjustment->hours_of_work,
            'days_of_work' => $adjustment->days_of_work,
            'generated_by' => $payslip->generated_by ?? $generatedBy,
        ]);

        if ($isNew) {
            $payslip->allowances = 0;
            $payslip->other_deductions = 0;
        }

        $payslip->recalculate();
        $payslip->save();

        return $payslip;
    }
}
