<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Notifications\LeaveRequested;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class LeaveController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $year = (int) ($request->integer('year') ?: now()->year);

        $leaveTypes = LeaveType::query()->orderBy('id')->get();

        $balances = $user->leaveBalances()
            ->where('year', $year)
            ->get()
            ->keyBy('leave_type_id');

        $requests = $user->leaveRequests()
            ->with(['leaveType', 'approver'])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        return view('staff.leaves.index', compact('user', 'leaveTypes', 'balances', 'requests', 'year'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'max:4096', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $leaveType = LeaveType::findOrFail($data['leave_type_id']);

        if ($leaveType->requires_attachment && ! $request->hasFile('attachment')) {
            return back()
                ->withErrors(['attachment' => __(':type requires a supporting attachment.', ['type' => $leaveType->name])])
                ->withInput();
        }

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        $days = (float) ($start->diffInDays($end) + 1);

        $overlaps = $user->leaveRequests()
            ->where('status', '!=', 'rejected')
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->exists();

        if ($overlaps) {
            return back()
                ->withErrors([
                    'start_date' => __('You already have a leave request that overlaps these dates.'),
                ])
                ->withInput();
        }

        $year = (int) $start->year;

        $available = max(0.0, $this->remainingDays($user, $leaveType, $year));

        if ($leaveType->slug !== 'annual') {
            $annualType = LeaveType::where('slug', 'annual')->first();

            if ($annualType) {
                $available += max(0.0, $this->remainingDays($user, $annualType, $year));
            }
        }

        if ($days > $available) {
            return back()
                ->withErrors([
                    'start_date' => __('Insufficient :type balance: :remaining day(s) remaining.', [
                        'type' => $leaveType->name,
                        'remaining' => $available,
                    ]),
                ])
                ->withInput();
        }

        $attachmentPath = $request->hasFile('attachment')
            ? $request->file('attachment')->store('leave-attachments', 'public')
            : null;

        $leaveRequest = $user->leaveRequests()->create([
            'leave_type_id' => $leaveType->id,
            'start_date' => $start,
            'end_date' => $end,
            'days' => $days,
            'reason' => $data['reason'] ?? null,
            'attachment_path' => $attachmentPath,
            'status' => 'pending',
        ]);

        User::role(['hr', 'admin'])
            ->get()
            ->each(fn (User $approver) => $approver->notify(new LeaveRequested($leaveRequest->load('leaveType', 'user'))));

        return redirect()
            ->route('staff.leaves.index')
            ->with('status', 'leave-requested');
    }

    public function destroy(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        abort_unless($leaveRequest->user_id === $request->user()->id, 403);

        if (! $leaveRequest->isPending()) {
            return redirect()
                ->route('staff.leaves.index')
                ->with('status', 'leave-not-cancellable');
        }

        if ($leaveRequest->hasAttachment()) {
            Storage::disk('public')->delete($leaveRequest->attachment_path);
        }

        $leaveRequest->delete();

        return redirect()
            ->route('staff.leaves.index')
            ->with('status', 'leave-cancelled');
    }

    /**
     * Days still available for a leave type in a given year.
     */
    protected function remainingDays(User $user, LeaveType $leaveType, int $year): float
    {
        $balance = $user->leaveBalances()
            ->where('leave_type_id', $leaveType->id)
            ->where('year', $year)
            ->first();

        return $balance
            ? $balance->remaining()
            : (float) $leaveType->default_days;
    }
}
