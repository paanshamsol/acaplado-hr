<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\Payslip;
use App\Support\PayslipSync;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayslipController extends Controller
{
    /**
     * List the payslips HR has saved for the signed-in staff member.
     *
     * Only payroll records that still have a matching saved salary adjustment
     * are listed, so the figures always match the admin's Salary & Payslips
     * section. Removed payslips stay removed until HR saves that period again.
     */
    public function index(Request $request): View
    {
        $payslips = $request->user()->payslips()
            ->withSavedAdjustment()
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();

        return view('staff.payslips.index', compact('payslips'));
    }

    /**
     * Download one of the signed-in staff member's own payslips as a PDF,
     * refreshed from the saved adjustment so it matches HR's latest update.
     */
    public function pdf(Request $request, Payslip $payslip): mixed
    {
        abort_unless($payslip->user_id === $request->user()->id, 404);

        $adjustment = $request->user()->salaryAdjustments()
            ->where('year', $payslip->year)
            ->where('month', $payslip->month)
            ->first();

        abort_if($adjustment === null, 404);

        $payslip = PayslipSync::fromAdjustment($adjustment);
        $payslip->load('user', 'generator');

        $pdf = Pdf::loadView('pdf.payslip', [
            'payslip' => $payslip,
            'company' => CompanySetting::current(),
        ]);

        $identifier = $payslip->user->employee_id ?: 'staff';

        return $pdf->download(sprintf('payslip-%s-%d-%02d.pdf', $identifier, $payslip->year, $payslip->month));
    }

    /**
     * Remove one of the signed-in staff member's own payslips.
     *
     * The record is deleted; it only reappears once HR saves that period again.
     */
    public function destroy(Request $request, Payslip $payslip): RedirectResponse
    {
        abort_unless($payslip->user_id === $request->user()->id, 404);

        $payslip->delete();

        return redirect()
            ->route('staff.payslips.index')
            ->with('status', 'payslip-removed');
    }
}
