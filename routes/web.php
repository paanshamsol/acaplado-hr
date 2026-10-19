<?php

use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CompanySettingController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\LeaveController;
use App\Http\Controllers\Admin\LeaveRequestController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Attendance\PunchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Staff\DashboardController as StaffDashboardController;
use App\Http\Controllers\Staff\LeaveController as StaffLeaveController;
use App\Http\Controllers\Staff\PayslipController as StaffPayslipController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::delete('/notifications/clear', [NotificationController::class, 'destroyAll'])->name('notifications.destroy-all');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
});

// Public clock-in / clock-out kiosk.
Route::get('/attendance', [PunchController::class, 'kiosk'])->name('attendance.kiosk');
Route::post('/attendance/punch', [PunchController::class, 'punch'])
    ->middleware('throttle:15,1')
    ->name('attendance.punch');

Route::middleware(['auth', 'verified', 'role:admin|hr'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('/attendance', [AdminAttendanceController::class, 'index'])->name('attendance.index');
        Route::get('/attendance/data', [AdminAttendanceController::class, 'data'])->name('attendance.data');
        Route::get('/attendance/export', [AdminAttendanceController::class, 'export'])->name('attendance.export');

        Route::get('/leaves', [LeaveController::class, 'index'])
            ->middleware('permission:view-leaves')
            ->name('leaves.index');
        Route::get('/leaves/{staff}/edit', [LeaveController::class, 'edit'])
            ->middleware('permission:manage-leaves')
            ->name('leaves.edit');
        Route::put('/leaves/{staff}', [LeaveController::class, 'update'])
            ->middleware('permission:manage-leaves')
            ->name('leaves.update');
        Route::delete('/leaves/{staff}', [LeaveController::class, 'destroy'])
            ->middleware('permission:manage-leaves')
            ->name('leaves.destroy');
        Route::delete('/leaves/{staff}/types/{leaveType}', [LeaveController::class, 'destroyType'])
            ->middleware('permission:manage-leaves')
            ->name('leaves.types.destroy');
        Route::post('/leaves/generate', [LeaveController::class, 'generate'])
            ->middleware('permission:manage-leaves')
            ->name('leaves.generate');

        Route::get('/leave-requests', [LeaveRequestController::class, 'index'])
            ->middleware('permission:approve-leaves')
            ->name('leave-requests.index');
        Route::get('/leave-requests/export', [LeaveRequestController::class, 'export'])
            ->middleware('permission:approve-leaves')
            ->name('leave-requests.export');
        Route::patch('/leave-requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])
            ->middleware('permission:approve-leaves')
            ->name('leave-requests.approve');
        Route::patch('/leave-requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])
            ->middleware('permission:approve-leaves')
            ->name('leave-requests.reject');
        Route::patch('/leave-requests/{leaveRequest}/remarks', [LeaveRequestController::class, 'updateRemarks'])
            ->middleware('permission:approve-leaves')
            ->name('leave-requests.remarks');

        Route::resource('staff', StaffController::class)
            ->except('show')
            ->middleware('permission:manage-staff');

        Route::post('/staff/{staff}/photo', [StaffController::class, 'updatePhoto'])
            ->middleware('permission:manage-staff')
            ->name('staff.photo');

        Route::patch('/staff/{staff}/salary', [StaffController::class, 'saveSalaryAdjustment'])
            ->middleware('permission:manage-staff')
            ->name('staff.salary');

        Route::get('/staff/{staff}/payslip/pdf', [StaffController::class, 'payslipPdf'])
            ->middleware('permission:manage-staff')
            ->name('staff.payslip.pdf');

        Route::get('/company', [CompanySettingController::class, 'edit'])
            ->middleware('permission:manage-company')
            ->name('company.edit');
        Route::put('/company', [CompanySettingController::class, 'update'])
            ->middleware('permission:manage-company')
            ->name('company.update');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])
            ->middleware('permission:manage-company')
            ->name('audit-logs.index');
    });

Route::middleware(['auth', 'verified', 'role:staff'])
    ->prefix('staff')
    ->name('staff.')
    ->group(function () {
        Route::get('/', [StaffDashboardController::class, 'index'])->name('dashboard');

        Route::get('/leaves', [StaffLeaveController::class, 'index'])->name('leaves.index');
        Route::post('/leaves', [StaffLeaveController::class, 'store'])->name('leaves.store');
        Route::delete('/leaves/{leaveRequest}', [StaffLeaveController::class, 'destroy'])->name('leaves.destroy');

        Route::get('/payslips', [StaffPayslipController::class, 'index'])->name('payslips.index');
        Route::get('/payslips/{payslip}/pdf', [StaffPayslipController::class, 'pdf'])->name('payslips.pdf');
        Route::delete('/payslips/{payslip}', [StaffPayslipController::class, 'destroy'])->name('payslips.destroy');
    });

require __DIR__.'/auth.php';
