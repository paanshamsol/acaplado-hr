<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\CompanySetting;
use App\Models\User;
use App\Support\AttendanceStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function index(): View
    {
        $staff = User::query()->attendanceStaff()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'employee_id']);

        return view('admin.attendance.index', compact('staff'));
    }

    /**
     * CSV export of attendance records for a period.
     *
     * Admin and HR accounts do not record attendance, so they are excluded.
     */
    public function export(Request $request): StreamedResponse
    {
        $month = (int) ($request->integer('month') ?: now()->month);
        $month = ($month >= 1 && $month <= 12) ? $month : (int) now()->month;
        $year = (int) ($request->integer('year') ?: now()->year);

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $attendances = Attendance::query()
            ->with('user')
            ->whereHas('user', fn ($query) => $query->attendanceStaff())
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('work_date')
            ->orderBy('user_id')
            ->get();

        $fileName = sprintf('attendance-%d-%02d.csv', $year, $month);

        return response()->streamDownload(function () use ($attendances) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Date', 'Employee ID', 'Name', 'Clock In', 'Clock Out', 'Worked (min)', 'Status',
            ]);

            foreach ($attendances as $attendance) {
                fputcsv($handle, [
                    $attendance->work_date?->format('Y-m-d'),
                    $attendance->user?->employee_id,
                    $attendance->user?->name,
                    $attendance->clock_in_at?->format('H:i'),
                    $attendance->clock_out_at?->format('H:i'),
                    (int) ($attendance->worked_minutes ?? 0),
                    $attendance->status,
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        return match ($request->input('mode')) {
            'week' => $this->weekData($request),
            'month' => $this->monthData($request),
            default => $this->dayData($request),
        };
    }

    protected function dayData(Request $request): JsonResponse
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->string('date'))
            : Carbon::today();

        $attendances = Attendance::with('user')
            ->whereDate('work_date', $date)
            ->whereHas('user', fn ($query) => $query->attendanceStaff())
            ->get()
            ->keyBy('user_id');

        $staffQuery = User::query()->attendanceStaff()->where('status', 'active')->orderBy('name');

        if ($request->filled('staff')) {
            $staffQuery->whereKey($request->integer('staff'));
        }

        $staff = $staffQuery->get();

        $shiftStart = CompanySetting::current()->operationalTimes->first()?->start_time;
        $graceMinutes = (int) config('hr.late_grace_minutes', 5);

        // A day with no clock-in/out at all (e.g. a future day) has no records
        // to judge, so every row is reported as "no record" rather than absent.
        $hasRecords = $attendances->isNotEmpty();

        $rows = $staff->map(function (User $user) use ($attendances, $date, $shiftStart, $graceMinutes, $hasRecords) {
            $attendance = $attendances->get($user->id);

            return [
                'id' => $attendance?->id,
                'user_id' => $user->id,
                'work_date' => $date->toDateString(),
                'clock_in_time' => $attendance?->clock_in_at?->format('H:i'),
                'clock_out_time' => $attendance?->clock_out_at?->format('H:i'),
                'name' => $user->name,
                'employee_id' => $user->employee_id,
                'photo' => $user->profile_photo_path ? Storage::disk('public')->url($user->profile_photo_path) : null,
                'clock_in' => $attendance?->clock_in_at?->format('h:i A'),
                'clock_out' => $attendance?->clock_out_at?->format('h:i A'),
                'worked' => $this->formatMinutes($attendance?->worked_minutes),
                'status' => $hasRecords
                    ? AttendanceStatus::derive($attendance?->clock_in_at, $shiftStart, $graceMinutes)
                    : AttendanceStatus::NO_RECORD,
                'clock_in_photo' => $attendance?->clock_in_photo_path ? Storage::disk('public')->url($attendance->clock_in_photo_path) : null,
                'clock_out_photo' => $attendance?->clock_out_photo_path ? Storage::disk('public')->url($attendance->clock_out_photo_path) : null,
            ];
        });

        return response()->json([
            'mode' => 'day',
            'label' => $date->format('l, d M Y'),
            'generated_at' => now()->format('h:i:s A'),
            'rows' => $rows->values(),
            'stats' => [
                'total' => $staff->count(),
                'present' => $attendances->count(),
                'completed' => $attendances->where('status', 'closed')->count(),
                'open' => $attendances->where('status', 'open')->count(),
            ],
        ]);
    }

    /**
     * Create an attendance record from the admin dashboard, without requiring
     * the staff member to clock in through the face kiosk.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateAttendance($request);

        [$clockIn, $clockOut] = $this->resolveTimes($data);

        Attendance::query()->create([
            'user_id' => $data['user_id'],
            'work_date' => $data['work_date'],
            'clock_in_at' => $clockIn,
            'clock_out_at' => $clockOut,
            'worked_minutes' => $clockOut !== null ? (int) $clockIn->diffInMinutes($clockOut, false) : null,
            'status' => $clockOut !== null ? 'closed' : 'open',
        ]);

        return response()->json(['ok' => true, 'message' => 'Attendance record created.']);
    }

    /**
     * Update an existing attendance record from the admin dashboard.
     */
    public function update(Request $request, Attendance $attendance): JsonResponse
    {
        $data = $this->validateAttendance($request, $attendance);

        [$clockIn, $clockOut] = $this->resolveTimes($data);

        $attendance->fill([
            'user_id' => $data['user_id'],
            'work_date' => $data['work_date'],
            'clock_in_at' => $clockIn,
            'clock_out_at' => $clockOut,
            'worked_minutes' => $clockOut !== null ? (int) $clockIn->diffInMinutes($clockOut, false) : null,
            'status' => $clockOut !== null ? 'closed' : 'open',
        ]);

        if ($clockOut === null) {
            $attendance->fill([
                'clock_out_photo_path' => null,
                'clock_out_face_score' => null,
                'clock_out_ip' => null,
            ]);
        }

        $attendance->save();

        return response()->json(['ok' => true, 'message' => 'Attendance record updated.']);
    }

    /**
     * Delete an attendance record and any captured punch photos.
     */
    public function destroy(Attendance $attendance): JsonResponse
    {
        $photos = array_filter([
            $attendance->clock_in_photo_path,
            $attendance->clock_out_photo_path,
        ]);

        if ($photos !== []) {
            Storage::disk('public')->delete($photos);
        }

        $attendance->delete();

        return response()->json(['ok' => true, 'message' => 'Attendance record deleted.']);
    }

    /**
     * @return array{user_id: int, work_date: string, clock_in_time: string, clock_out_time: ?string}
     */
    protected function validateAttendance(Request $request, ?Attendance $attendance = null): array
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'work_date' => ['required', 'date_format:Y-m-d'],
            'clock_in_time' => ['required', 'date_format:H:i'],
            'clock_out_time' => ['nullable', 'date_format:H:i'],
        ]);

        $staff = User::query()->findOrFail($data['user_id']);

        if (! $staff->recordsAttendance() || $staff->status !== 'active') {
            throw ValidationException::withMessages([
                'user_id' => 'Attendance can only be recorded for active staff accounts.',
            ]);
        }

        $duplicate = Attendance::query()
            ->where('user_id', $staff->id)
            ->whereDate('work_date', $data['work_date'])
            ->when($attendance !== null, fn ($query) => $query->whereKeyNot($attendance->getKey()))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'work_date' => 'This staff member already has an attendance record for that date.',
            ]);
        }

        return $data;
    }

    /**
     * @param  array{work_date: string, clock_in_time: string, clock_out_time: ?string}  $data
     * @return array{0: Carbon, 1: ?Carbon}
     */
    protected function resolveTimes(array $data): array
    {
        $date = Carbon::createFromFormat('Y-m-d', $data['work_date'])->startOfDay();

        $clockIn = $date->copy()->setTimeFromTimeString($data['clock_in_time']);

        if (blank($data['clock_out_time'] ?? null)) {
            return [$clockIn, null];
        }

        $clockOut = $date->copy()->setTimeFromTimeString($data['clock_out_time']);

        // A clock-out earlier than the clock-in belongs to the next day, which
        // is how overnight shifts are entered manually.
        if ($clockOut->lessThan($clockIn)) {
            $clockOut->addDay();
        }

        return [$clockIn, $clockOut];
    }

    protected function weekData(Request $request): JsonResponse
    {
        $reference = $request->filled('week')
            ? $this->parseWeek($request->string('week')->toString())
            : Carbon::now();

        $start = $reference->copy()->startOfWeek(Carbon::MONDAY);
        $end = $reference->copy()->endOfWeek(Carbon::SUNDAY);

        return $this->periodData(
            $request,
            $start,
            $end,
            'week',
            $start->format('d M Y').' – '.$end->format('d M Y'),
        );
    }

    protected function monthData(Request $request): JsonResponse
    {
        $month = $request->filled('month')
            ? Carbon::parse($request->string('month').'-01')
            : Carbon::now();

        return $this->periodData(
            $request,
            $month->copy()->startOfMonth(),
            $month->copy()->endOfMonth(),
            'month',
            $month->format('F Y'),
        );
    }

    /**
     * List every individual clock-in/out record between two dates.
     */
    protected function periodData(Request $request, Carbon $start, Carbon $end, string $mode, string $label): JsonResponse
    {
        $query = Attendance::with('user')
            ->whereHas('user', fn ($query) => $query->attendanceStaff())
            ->whereDate('work_date', '>=', $start)
            ->whereDate('work_date', '<=', $end);

        if ($request->filled('staff')) {
            $query->where('user_id', $request->integer('staff'));
        }

        $attendances = $query->orderBy('work_date')->orderBy('clock_in_at')->get();

        $shiftStart = CompanySetting::current()->operationalTimes->first()?->start_time;
        $graceMinutes = (int) config('hr.late_grace_minutes', 5);

        $rows = $attendances->map(function (Attendance $attendance) use ($shiftStart, $graceMinutes) {
            return [
                'id' => $attendance->id,
                'user_id' => $attendance->user_id,
                'work_date' => $attendance->work_date->toDateString(),
                'clock_in_time' => $attendance->clock_in_at?->format('H:i'),
                'clock_out_time' => $attendance->clock_out_at?->format('H:i'),
                'date' => $attendance->work_date->format('d M Y'),
                'weekday' => $attendance->work_date->format('D'),
                'name' => $attendance->user?->name,
                'employee_id' => $attendance->user?->employee_id,
                'photo' => $attendance->user?->profile_photo_path ? Storage::disk('public')->url($attendance->user->profile_photo_path) : null,
                'clock_in' => $attendance->clock_in_at?->format('h:i A'),
                'clock_out' => $attendance->clock_out_at?->format('h:i A'),
                'worked' => $this->formatMinutes($attendance->worked_minutes),
                'status' => AttendanceStatus::derive($attendance->clock_in_at, $shiftStart, $graceMinutes),
            ];
        });

        return response()->json([
            'mode' => $mode,
            'label' => $label,
            'generated_at' => now()->format('h:i:s A'),
            'rows' => $rows->values(),
            'stats' => [
                'records' => $attendances->count(),
                'completed' => $attendances->where('status', 'closed')->count(),
                'open' => $attendances->where('status', 'open')->count(),
            ],
        ]);
    }

    protected function parseWeek(string $value): Carbon
    {
        if (! preg_match('/^(\d{4})-W(\d{1,2})$/', $value, $matches)) {
            return Carbon::now();
        }

        return Carbon::now()->setISODate((int) $matches[1], (int) $matches[2])->startOfDay();
    }

    protected function formatMinutes(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        return sprintf('%dh %02dm', intdiv($minutes, 60), $minutes % 60);
    }
}
