<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use App\Support\LeaveOverview;
use App\Support\LeaveRollover;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveController extends Controller
{
    public function index(Request $request): View
    {
        $year = (int) ($request->integer('year') ?: now()->year);

        $overview = LeaveOverview::matrix($year);

        return view('admin.leaves.index', $overview);
    }

    public function edit(Request $request, User $staff): View
    {
        $year = (int) ($request->integer('year') ?: now()->year);

        $leaveTypes = LeaveType::query()->orderBy('id')->get();

        $balances = $staff->leaveBalances()
            ->where('year', $year)
            ->get()
            ->keyBy('leave_type_id');

        return view('admin.leaves.edit', compact('staff', 'leaveTypes', 'balances', 'year'));
    }

    public function update(Request $request, User $staff): RedirectResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'entitlements' => ['array'],
            'entitlements.*' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'carried_over' => ['array'],
            'carried_over.*' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'used' => ['array'],
            'used.*' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'delete' => ['array'],
            'delete.*' => ['nullable', 'boolean'],
        ]);

        $year = (int) $data['year'];

        foreach (LeaveType::query()->pluck('id') as $typeId) {
            if ($request->boolean("delete.{$typeId}")) {
                $staff->leaveBalances()
                    ->where('leave_type_id', $typeId)
                    ->where('year', $year)
                    ->delete();

                continue;
            }

            LeaveBalance::updateOrCreate(
                [
                    'user_id' => $staff->id,
                    'leave_type_id' => $typeId,
                    'year' => $year,
                ],
                [
                    'entitlement' => $data['entitlements'][$typeId] ?? 0,
                    'carried_over' => $data['carried_over'][$typeId] ?? 0,
                    'used' => $data['used'][$typeId] ?? 0,
                ],
            );
        }

        return redirect()
            ->route('admin.leaves.index', ['year' => $year])
            ->with('status', 'leaves-updated');
    }

    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2099'],
        ]);

        $sourceYear = (int) $data['year'];
        $targetYear = $sourceYear + 1;

        $count = LeaveRollover::generate($targetYear, $sourceYear);

        return redirect()
            ->route('admin.leaves.index', ['year' => $targetYear])
            ->with('status', 'leaves-generated')
            ->with('leaves-generated-year', $targetYear)
            ->with('leaves-generated-count', $count);
    }

    public function destroy(Request $request, User $staff): RedirectResponse
    {
        $year = (int) ($request->integer('year') ?: now()->year);

        $staff->leaveBalances()->where('year', $year)->delete();

        return redirect()
            ->route('admin.leaves.index', ['year' => $year])
            ->with('status', 'leaves-deleted');
    }

    public function destroyType(Request $request, User $staff, LeaveType $leaveType): RedirectResponse
    {
        $year = (int) ($request->integer('year') ?: now()->year);

        $staff->leaveBalances()
            ->where('leave_type_id', $leaveType->id)
            ->where('year', $year)
            ->delete();

        return redirect()
            ->route('admin.leaves.edit', ['staff' => $staff, 'year' => $year])
            ->with('status', 'leaves-deleted');
    }
}
