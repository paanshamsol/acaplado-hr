<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\AttendanceBreakdown;
use App\Support\LeaveOverview;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $stats = [
            'staff' => User::role(['staff', 'contract', 'part-time'])->count(),
            'active' => User::where('status', 'active')->count(),
            'face_enrolled' => User::whereNotNull('face_descriptor')->count(),
            'present_today' => Attendance::whereDate('work_date', today())
                ->whereHas('user', fn ($query) => $query->attendanceStaff())
                ->count(),
        ];

        $year = (int) ($request->integer('year') ?: now()->year);

        $leave = auth()->user()->can('view-leaves') ? LeaveOverview::matrix($year) : null;
        $staffWithBalances = collect();

        if ($leave) {
            $staffWithBalances = $leave['staff']->map(function ($member) use ($leave) {
                $memberBalances = $leave['leaveTypes']->map(function ($type) use ($member, $leave) {
                    $balance = $leave['balances']->get($member->id.'-'.$type->id);
                    $entitlement = $balance ? (float) $balance->entitlement : (float) $type->default_days;
                    $carried = $balance ? (float) $balance->carried_over : 0.0;
                    $used = $balance ? (float) $balance->used : 0.0;
                    $remaining = $entitlement + $carried - $used;
                    $percent = $entitlement > 0 ? min(100, max(0, ($used / $entitlement) * 100)) : 0;

                    if ($remaining <= 0) {
                        $color = 'text-red-600';
                        $bar = 'bg-red-500';
                        $status = 'negative';
                    } elseif ($remaining <= 2) {
                        $color = 'text-amber-600';
                        $bar = 'bg-amber-500';
                        $status = 'low';
                    } else {
                        $color = 'text-green-600';
                        $bar = 'bg-green-500';
                        $status = 'good';
                    }

                    return compact('type', 'entitlement', 'carried', 'used', 'remaining', 'percent', 'color', 'bar', 'status');
                });

                $totalUsed = $memberBalances->sum('used');
                $totalLeft = $memberBalances->sum('remaining');

                return compact('member', 'memberBalances', 'totalUsed', 'totalLeft');
            });

            $leave['staffWithBalances'] = $staffWithBalances;
        }

        $attendanceChart = AttendanceBreakdown::forDate();
        $attendanceStaff = AttendanceBreakdown::grouped();

        $leaveRequests = null;

        if (auth()->user()->can('approve-leaves')) {
            $lrYear = $request->filled('lr_year') ? (int) $request->integer('lr_year') : (int) now()->year;
            $lrMonth = $request->filled('lr_month') && (int) $request->integer('lr_month') >= 1 && (int) $request->integer('lr_month') <= 12
                ? (int) $request->integer('lr_month')
                : null;
            $lrStaffId = $request->filled('lr_staff') && (int) $request->integer('lr_staff') > 0
                ? (int) $request->integer('lr_staff')
                : null;
            $lrStatus = $request->string('lr_status')->toString();
            $lrStatus = in_array($lrStatus, ['all', 'pending', 'approved', 'rejected'], true) ? $lrStatus : 'all';

            $historyQuery = LeaveRequest::query()
                ->forStaff()
                ->with(['user', 'leaveType', 'approver'])
                ->when($lrYear > 0, fn ($q) => $q->whereYear('start_date', $lrYear))
                ->when($lrMonth !== null, fn ($q) => $q->whereMonth('start_date', $lrMonth))
                ->when($lrStaffId !== null, fn ($q) => $q->where('user_id', $lrStaffId));

            $allStaffForLeave = User::query()->leaveStaff()->orderBy('name')->get(['id', 'name', 'employee_id']);
            $groupedByStaff = (clone $historyQuery)->get()->groupBy('user_id');

            $staffSummary = $allStaffForLeave
                ->when($lrStaffId !== null, fn ($c) => $c->where('id', $lrStaffId))
                ->map(function (User $member) use ($groupedByStaff) {
                    $items = $groupedByStaff->get($member->id, collect());

                    return [
                        'member' => $member,
                        'pending' => $items->where('status', 'pending')->count(),
                        'approved' => $items->where('status', 'approved')->count(),
                        'rejected' => $items->where('status', 'rejected')->count(),
                        'total' => $items->count(),
                        'approved_days' => (float) $items->where('status', 'approved')->sum('days'),
                    ];
                })
                ->values();

            $leaveRequests = [
                'counts' => [
                    'pending' => LeaveRequest::query()->forStaff()->where('status', 'pending')->count(),
                    'approved' => LeaveRequest::query()->forStaff()->where('status', 'approved')->count(),
                    'rejected' => LeaveRequest::query()->forStaff()->where('status', 'rejected')->count(),
                ],
                'latest' => LeaveRequest::query()
                    ->forStaff()
                    ->with(['user', 'leaveType', 'approver'])
                    ->where('status', 'pending')
                    ->orderBy('start_date')
                    ->orderBy('id')
                    ->limit(5)
                    ->get(),
                'filter' => [
                    'year' => $lrYear,
                    'month' => $lrMonth,
                    'staff' => $lrStaffId,
                    'status' => $lrStatus,
                ],
                'staffList' => $allStaffForLeave,
                'staffSummary' => $staffSummary,
                'history' => (clone $historyQuery)
                    ->when($lrStatus !== 'all', fn ($q) => $q->where('status', $lrStatus))
                    ->orderByDesc('start_date')
                    ->orderByDesc('id')
                    ->limit(25)
                    ->get(),
            ];
        }

        $salaryYear = (int) ($request->integer('salary_year') ?: now()->year);
        $salaryMonth = (int) ($request->integer('salary_month') ?: now()->month);
        $salaryMonth = ($salaryMonth >= 1 && $salaryMonth <= 12) ? $salaryMonth : (int) now()->month;

        $salaryStaff = collect();
        $salaryTotal = 0.0;

        if (auth()->user()->can('manage-staff')) {
            $salaryStaff = User::query()
                ->attendanceStaff()
                ->with([
                    'salaryAdjustments' => fn ($query) => $query
                        ->where('year', $salaryYear)
                        ->where('month', $salaryMonth),
                    'payslips' => fn ($query) => $query
                        ->where('year', $salaryYear)
                        ->where('month', $salaryMonth),
                ])
                ->orderBy('name')
                ->get();

            $salaryTotal = $salaryStaff->sum(function (User $member) {
                $adjustment = $member->salaryAdjustments->first();

                return $adjustment
                    ? $adjustment->total()
                    : (float) $member->hourly_rate * (float) $member->hours_of_work * (int) $member->days_of_work;
            });
        }

        return view('admin.dashboard', compact(
            'stats',
            'leave',
            'attendanceChart',
            'attendanceStaff',
            'leaveRequests',
            'salaryStaff',
            'salaryYear',
            'salaryMonth',
            'salaryTotal',
        ));
    }
}
