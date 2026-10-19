<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Notifications\LeaveDecided;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveRequestController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $status = in_array($status, ['all', 'pending', 'approved', 'rejected'], true) ? $status : 'pending';

        $year = $request->filled('year') ? (int) $request->integer('year') : (int) now()->year;
        $month = $request->filled('month') && (int) $request->integer('month') >= 1 && (int) $request->integer('month') <= 12
            ? (int) $request->integer('month')
            : null;
        $staffId = $request->filled('staff') && (int) $request->integer('staff') > 0
            ? (int) $request->integer('staff')
            : null;

        $baseQuery = LeaveRequest::query()
            ->when($year > 0, fn ($q) => $q->whereYear('start_date', $year))
            ->when($month !== null, fn ($q) => $q->whereMonth('start_date', $month))
            ->when($staffId !== null, fn ($q) => $q->where('user_id', $staffId));

        $counts = [
            'all' => (clone $baseQuery)->count(),
            'pending' => (clone $baseQuery)->where('status', 'pending')->count(),
            'approved' => (clone $baseQuery)->where('status', 'approved')->count(),
            'rejected' => (clone $baseQuery)->where('status', 'rejected')->count(),
        ];

        $requests = (clone $baseQuery)
            ->with(['user', 'leaveType', 'approver'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $staffList = User::query()->orderBy('name')->get(['id', 'name', 'employee_id']);

        $periodRequests = LeaveRequest::query()
            ->when($year > 0, fn ($q) => $q->whereYear('start_date', $year))
            ->when($month !== null, fn ($q) => $q->whereMonth('start_date', $month))
            ->when($staffId !== null, fn ($q) => $q->where('user_id', $staffId))
            ->get()
            ->groupBy('user_id');

        $staffSummary = $staffList
            ->when($staffId !== null, fn ($c) => $c->where('id', $staffId))
            ->map(function (User $member) use ($periodRequests) {
                $items = $periodRequests->get($member->id, collect());

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

        return view('admin.leave-requests.index', compact(
            'requests',
            'status',
            'counts',
            'year',
            'month',
            'staffId',
            'staffList',
            'staffSummary',
        ));
    }

    public function approve(Request $request, LeaveRequest $leaveRequest): RedirectResponse|JsonResponse
    {
        if (! $leaveRequest->isPending()) {
            return $this->respond($request, 'leave-already-decided', __('This request has already been decided.'), $leaveRequest);
        }

        $leaveRequest->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'decided_at' => now(),
            'rejection_reason' => null,
        ]);

        $this->recordUsage($leaveRequest);

        $leaveRequest->user?->notify(new LeaveDecided($leaveRequest->load('leaveType')));

        return $this->respond(
            $request,
            'leave-approved',
            __('Approved :type leave for :name (:days day(s)).', [
                'type' => $leaveRequest->leaveType?->name ?? __('Leave'),
                'name' => $leaveRequest->user?->name ?? __('Former Staff'),
                'days' => (float) $leaveRequest->days,
            ]),
            $leaveRequest,
        );
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): RedirectResponse|JsonResponse
    {
        if (! $leaveRequest->isPending()) {
            return $this->respond($request, 'leave-already-decided', __('This request has already been decided.'), $leaveRequest);
        }

        $data = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $leaveRequest->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'decided_at' => now(),
            'rejection_reason' => $data['rejection_reason'] ?? null,
        ]);

        $leaveRequest->user?->notify(new LeaveDecided($leaveRequest->load('leaveType')));

        return $this->respond(
            $request,
            'leave-rejected',
            __('Rejected :type leave for :name.', [
                'type' => $leaveRequest->leaveType?->name ?? __('Leave'),
                'name' => $leaveRequest->user?->name ?? __('Former Staff'),
            ]),
            $leaveRequest,
        );
    }

    public function updateRemarks(Request $request, LeaveRequest $leaveRequest): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $remarks = $data['remarks'] ?? null;

        $leaveRequest->update([
            'remarks' => filled($remarks) ? $remarks : null,
        ]);

        return $this->respond($request, 'leave-remarks-saved', __('Remarks saved.'), $leaveRequest);
    }

    /**
     * CSV export of leave requests for a period.
     */
    public function export(Request $request): StreamedResponse
    {
        $year = (int) ($request->integer('year') ?: now()->year);
        $month = $request->filled('month') && (int) $request->integer('month') >= 1 && (int) $request->integer('month') <= 12
            ? (int) $request->integer('month')
            : null;

        $requests = LeaveRequest::query()
            ->with(['user', 'leaveType', 'approver'])
            ->when($year > 0, fn ($q) => $q->whereYear('start_date', $year))
            ->when($month !== null, fn ($q) => $q->whereMonth('start_date', $month))
            ->orderByDesc('start_date')
            ->get();

        $fileName = sprintf('leave-requests-%d%s.csv', $year, $month ? '-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT) : '');

        return response()->streamDownload(function () use ($requests) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Employee ID', 'Name', 'Leave Type', 'Start Date', 'End Date', 'Days', 'Status',
                'Approved By', 'Decided At', 'Reason', 'Rejection Reason', 'Remarks',
            ]);

            foreach ($requests as $leaveRequest) {
                fputcsv($handle, [
                    $leaveRequest->user?->employee_id,
                    $leaveRequest->user?->name,
                    $leaveRequest->leaveType?->name,
                    $leaveRequest->start_date?->format('Y-m-d'),
                    $leaveRequest->end_date?->format('Y-m-d'),
                    (float) $leaveRequest->days,
                    $leaveRequest->status,
                    $leaveRequest->approver?->name,
                    $leaveRequest->decided_at?->format('Y-m-d H:i'),
                    $leaveRequest->reason,
                    $leaveRequest->rejection_reason,
                    $leaveRequest->remarks,
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Redirect back for normal form posts, or answer with fresh counts for the
     * dashboard's AJAX (fetch) requests so the UI can update without a reload.
     */
    protected function respond(Request $request, string $status, string $message, LeaveRequest $leaveRequest): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'status' => $status,
                'message' => $message,
                'remarks' => $leaveRequest->remarks,
                'counts' => $this->counts(),
            ]);
        }

        return back()->with('status', $status);
    }

    /**
     * @return array{pending: int, approved: int, rejected: int}
     */
    protected function counts(): array
    {
        return [
            'pending' => LeaveRequest::query()->where('status', 'pending')->count(),
            'approved' => LeaveRequest::query()->where('status', 'approved')->count(),
            'rejected' => LeaveRequest::query()->where('status', 'rejected')->count(),
        ];
    }

    /**
     * Add the approved days to the staff member's balance for that year.
     *
     * If a non-annual leave type is exhausted, the surplus is drawn from the
     * staff member's annual leave for the same year.
     */
    protected function recordUsage(LeaveRequest $leaveRequest): void
    {
        $year = (int) $leaveRequest->start_date->year;
        $days = (float) $leaveRequest->days;
        $leaveType = $leaveRequest->leaveType;

        $balance = LeaveBalance::firstOrCreate(
            [
                'user_id' => $leaveRequest->user_id,
                'leave_type_id' => $leaveRequest->leave_type_id,
                'year' => $year,
            ],
            [
                'entitlement' => (float) $leaveType->default_days,
                'carried_over' => 0,
                'used' => 0,
            ],
        );

        $available = max(0.0, $balance->remaining());

        if ($days <= $available) {
            $balance->increment('used', $days);

            return;
        }

        $surplus = $days - $available;

        $balance->increment('used', $available);

        if ($leaveType->slug === 'annual') {
            $balance->increment('used', $surplus);

            return;
        }

        $annual = $this->annualBalance($leaveRequest->user_id, $year);

        $annual?->increment('used', $surplus);
    }

    /**
     * The staff member's annual leave balance for a year, created on demand.
     */
    protected function annualBalance(int $userId, int $year): ?LeaveBalance
    {
        $annual = LeaveType::where('slug', 'annual')->first();

        if (! $annual) {
            return null;
        }

        return LeaveBalance::firstOrCreate(
            [
                'user_id' => $userId,
                'leave_type_id' => $annual->id,
                'year' => $year,
            ],
            [
                'entitlement' => (float) $annual->default_days,
                'carried_over' => 0,
                'used' => 0,
            ],
        );
    }
}
