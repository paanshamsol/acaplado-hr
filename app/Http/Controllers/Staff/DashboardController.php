<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\CompanySetting;
use App\Models\LeaveType;
use App\Support\AttendanceStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $year = (int) now()->year;

        $leaveTypes = LeaveType::query()->orderBy('id')->get();

        $balances = $user->leaveBalances()
            ->where('year', $year)
            ->get()
            ->keyBy('leave_type_id');

        $pendingRequests = $user->leaveRequests()->where('status', 'pending')->count();

        $payslipsCount = $user->payslips()->withSavedAdjustment()->count();

        $today = now()->toDateString();
        $todayAttendance = Attendance::query()
            ->where('user_id', $user->id)
            ->where('work_date', $today)
            ->first();

        $shiftStart = CompanySetting::current()->operationalTimes->first()?->start_time;
        $graceMinutes = (int) config('hr.late_grace_minutes', 5);
        $todayStatus = $todayAttendance
            ? AttendanceStatus::derive($todayAttendance->clock_in_at, $shiftStart, $graceMinutes)
            : AttendanceStatus::NO_RECORD;

        return view('staff.dashboard', compact(
            'user',
            'leaveTypes',
            'balances',
            'year',
            'pendingRequests',
            'payslipsCount',
            'todayAttendance',
            'todayStatus',
            'shiftStart',
            'graceMinutes',
        ));
    }
}
