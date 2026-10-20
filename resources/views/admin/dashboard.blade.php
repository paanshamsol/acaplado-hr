<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-base sm:text-lg text-gray-800 leading-tight">
            {{ __('Admin Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-4 sm:py-6">
        <div class="max-w-7xl mx-auto px-3 sm:px-5 lg:px-6 space-y-4 sm:space-y-5">
            @if (session('status') === 'leave-approved')
                <div class="rounded-md bg-green-50 px-3 py-2.5 text-xs sm:text-sm text-green-700">{{ __('Leave request approved.') }}</div>
            @endif
            @if (session('status') === 'leave-rejected')
                <div class="rounded-md bg-green-50 px-3 py-2.5 text-xs sm:text-sm text-green-700">{{ __('Leave request rejected.') }}</div>
            @endif
            @if (session('status') === 'leave-already-decided')
                <div class="rounded-md bg-amber-50 px-3 py-2.5 text-xs sm:text-sm text-amber-700">{{ __('This request has already been decided.') }}</div>
            @endif
            @if (session('status') === 'leave-remarks-saved')
                <div class="rounded-md bg-green-50 px-3 py-2.5 text-xs sm:text-sm text-green-700">{{ __('Remarks saved.') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-4 py-3 sm:px-5 sm:py-3.5 text-gray-900 flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                    <p class="text-xs sm:text-sm text-gray-500">{{ \App\Models\CompanySetting::current()->name }}</p>
                    <p class="text-sm sm:text-base font-medium">{{ __('Welcome back,') }} {{ auth()->user()->name }}.</p>
                </div>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg px-3 py-3 sm:px-4 sm:py-3.5 flex items-start gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <x-icon name="users" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <div class="text-[11px] sm:text-xs text-gray-500 uppercase tracking-wide">{{ __('Staff Members') }}</div>
                        <div class="text-xl sm:text-2xl font-semibold text-gray-900">{{ $stats['staff'] }}</div>
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg px-3 py-3 sm:px-4 sm:py-3.5 flex items-start gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-green-50 text-green-600">
                        <x-icon name="check-circle" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <div class="text-[11px] sm:text-xs text-gray-500 uppercase tracking-wide">{{ __('Active Accounts') }}</div>
                        <div class="text-xl sm:text-2xl font-semibold text-gray-900">{{ $stats['active'] }}</div>
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg px-3 py-3 sm:px-4 sm:py-3.5 flex items-start gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-600">
                        <x-icon name="eye" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <div class="text-[11px] sm:text-xs text-gray-500 uppercase tracking-wide">{{ __('Face Enrolled') }}</div>
                        <div class="text-xl sm:text-2xl font-semibold text-gray-900">{{ $stats['face_enrolled'] }}</div>
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg px-3 py-3 sm:px-4 sm:py-3.5 flex items-start gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-[var(--brand)]">
                        <x-icon name="clock" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <div class="text-[11px] sm:text-xs text-gray-500 uppercase tracking-wide">{{ __('Present Today') }}</div>
                        <div class="text-xl sm:text-2xl font-semibold text-[var(--brand)]">{{ $stats['present_today'] }}</div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">
                <div class="lg:col-span-2 bg-white overflow-hidden shadow-sm sm:rounded-lg"
                    x-data="attendanceChart({ onTime: {{ $attendanceChart['on_time'] }}, late: {{ $attendanceChart['late'] }}, absent: {{ $attendanceChart['absent'] }}, noRecord: {{ $attendanceChart['no_record'] }}, names: { onTime: @js($attendanceStaff['on_time']->pluck('name')->values()), late: @js($attendanceStaff['late']->pluck('name')->values()), absent: @js($attendanceStaff['absent']->pluck('name')->values()), noRecord: @js($attendanceStaff['no_record']->pluck('name')->values()) } })">
                    <div class="px-4 py-3 sm:px-5 sm:py-3.5 border-b border-gray-100 flex flex-wrap items-center justify-between gap-x-3 gap-y-1.5">
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-[var(--brand)]">
                                <x-icon name="chart-bar" class="h-5 w-5" />
                            </span>
                            <div>
                                <h3 class="font-medium text-sm sm:text-base text-gray-900">{{ __("Today's Attendance") }}</h3>
                                <p class="text-xs text-gray-500">
                                    {{ __('On time, late and absent staff for :date.', ['date' => now()->format('l, d M Y')]) }}
                                </p>
                            </div>
                        </div>
                        @can('view-attendance')
                            <a href="{{ route('admin.attendance.index') }}"
                               class="inline-flex items-center gap-1 text-xs sm:text-sm font-medium text-[var(--brand)] hover:opacity-80 focus:outline-none focus:ring-2 focus:ring-[var(--brand)] focus:ring-offset-2 rounded">
                                {{ __('View attendance') }}
                                <x-icon name="arrow-right" class="h-3.5 w-3.5" />
                            </a>
                        @endcan
                    </div>
                    <div class="px-4 py-3 sm:px-5 sm:py-4">
                        <div class="relative mx-auto h-40 sm:h-48 lg:h-56 max-w-[15rem]">
                            <canvas x-ref="canvas"></canvas>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-4 py-3 sm:px-5 sm:py-3.5 border-b border-gray-100">
                        <h3 class="font-medium text-sm sm:text-base text-gray-900">{{ __('Breakdown') }}</h3>
                    </div>
                    <div class="px-4 py-3 sm:px-5 sm:py-4 space-y-3 sm:space-y-3.5">
                        @php $attendanceTotal = max(array_sum($attendanceChart), 1); @endphp

                        <div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="inline-flex items-center gap-2 text-xs sm:text-sm text-gray-600">
                                    <span class="inline-block h-2.5 w-2.5 rounded-full bg-green-500"></span>
                                    {{ __('On time') }}
                                </span>
                                <span class="text-sm sm:text-base font-semibold text-gray-900">{{ $attendanceChart['on_time'] }}</span>
                            </div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-100">
                                <div class="h-full rounded-full bg-green-500" style="width: {{ (int) round($attendanceChart['on_time'] / $attendanceTotal * 100) }}%"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="inline-flex items-center gap-2 text-xs sm:text-sm text-gray-600">
                                    <span class="inline-block h-2.5 w-2.5 rounded-full bg-red-500"></span>
                                    {{ __('Late') }}
                                </span>
                                <span class="text-sm sm:text-base font-semibold text-gray-900">{{ $attendanceChart['late'] }}</span>
                            </div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-100">
                                <div class="h-full rounded-full bg-red-500" style="width: {{ (int) round($attendanceChart['late'] / $attendanceTotal * 100) }}%"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="inline-flex items-center gap-2 text-xs sm:text-sm text-gray-600">
                                    <span class="inline-block h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                                    {{ __('Absent') }}
                                </span>
                                <span class="text-sm sm:text-base font-semibold text-gray-900">{{ $attendanceChart['absent'] }}</span>
                            </div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-100">
                                <div class="h-full rounded-full bg-amber-500" style="width: {{ (int) round($attendanceChart['absent'] / $attendanceTotal * 100) }}%"></div>
                            </div>
                        </div>

                        @if ($attendanceChart['no_record'] > 0)
                            <div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="inline-flex items-center gap-2 text-xs sm:text-sm text-gray-600">
                                        <span class="inline-block h-2.5 w-2.5 rounded-full bg-gray-400"></span>
                                        {{ __('No record') }}
                                    </span>
                                    <span class="text-sm sm:text-base font-semibold text-gray-900">{{ $attendanceChart['no_record'] }}</span>
                                </div>
                                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-100">
                                    <div class="h-full rounded-full bg-gray-400" style="width: {{ (int) round($attendanceChart['no_record'] / $attendanceTotal * 100) }}%"></div>
                                </div>
                            </div>
                        @endif

                        <div class="flex items-center justify-between border-t border-gray-100 pt-3">
                            <span class="text-xs sm:text-sm font-medium text-gray-700">{{ __('Total staff') }}</span>
                            <span class="text-sm sm:text-base font-semibold text-gray-900">
                                {{ array_sum($attendanceChart) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            @if ($leaveRequests)
                @php
                    $leaveCounts = $leaveRequests['counts'];
                    $pendingShown = count($leaveRequests['latest']);

                    $leaveTypeStyles = [
                        'annual' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                        'medical' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
                        'emergency' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
                        'bereavement' => 'bg-violet-50 text-violet-700 ring-violet-600/20',
                    ];
                @endphp

                <div class="space-y-4 sm:space-y-5"
                    x-data="pendingApprovals({
                        pending: {{ $leaveCounts['pending'] }},
                        approved: {{ $leaveCounts['approved'] }},
                        rejected: {{ $leaveCounts['rejected'] }},
                        summaryText: @js(__('{count} request(s) awaiting your decision.')),
                        summaryEmpty: @js(__('No requests awaiting your decision.'))
                    })"
                    @leave-decided.window="onDecided($event.detail)"
                    @leave-remarks-saved.window="onRemarksSaved($event.detail)">

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg"
                            x-data="leaveRequestChart({ pending: {{ $leaveCounts['pending'] }}, approved: {{ $leaveCounts['approved'] }}, rejected: {{ $leaveCounts['rejected'] }} })">
                            <div class="px-4 py-3 sm:px-5 sm:py-3.5 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <h3 class="font-medium text-sm sm:text-base text-gray-900">{{ __('Leave Requests') }}</h3>
                                    <p class="mt-0.5 text-xs text-gray-500">
                                        {{ __('Pending, approved and rejected requests.') }}
                                    </p>
                                </div>
                                <a href="{{ route('admin.leave-requests.index') }}"
                                   class="text-xs sm:text-sm text-[var(--brand)] hover:opacity-80">{{ __('View all') }}</a>
                            </div>
                            <div class="px-4 py-3 sm:px-5 sm:py-4">
                                <div class="relative mx-auto h-40 sm:h-44 max-w-[15rem]">
                                    <canvas x-ref="canvas"></canvas>
                                </div>

                                <div class="mt-3 grid grid-cols-3 gap-1.5 sm:gap-2">
                                    <a href="{{ route('admin.leave-requests.index', ['status' => 'pending']) }}"
                                       class="group flex items-center justify-between gap-1 rounded-lg border border-gray-200 px-2 py-1.5 transition hover:border-[var(--brand)] hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[var(--brand)] focus:ring-offset-1">
                                        <span class="flex items-center gap-1.5 text-[11px] sm:text-xs text-gray-600 group-hover:text-gray-900">
                                            <span class="h-2 w-2 shrink-0 rounded-full bg-amber-500"></span>
                                            {{ __('Pending') }}
                                        </span>
                                        <span class="text-xs sm:text-sm font-semibold text-gray-900" x-text="$store.leaveCounts.pending">{{ $leaveCounts['pending'] }}</span>
                                    </a>
                                    <a href="{{ route('admin.leave-requests.index', ['status' => 'approved']) }}"
                                       class="group flex items-center justify-between gap-1 rounded-lg border border-gray-200 px-2 py-1.5 transition hover:border-[var(--brand)] hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[var(--brand)] focus:ring-offset-1">
                                        <span class="flex items-center gap-1.5 text-[11px] sm:text-xs text-gray-600 group-hover:text-gray-900">
                                            <span class="h-2 w-2 shrink-0 rounded-full bg-green-500"></span>
                                            {{ __('Approved') }}
                                        </span>
                                        <span class="text-xs sm:text-sm font-semibold text-gray-900" x-text="$store.leaveCounts.approved">{{ $leaveCounts['approved'] }}</span>
                                    </a>
                                    <a href="{{ route('admin.leave-requests.index', ['status' => 'rejected']) }}"
                                       class="group flex items-center justify-between gap-1 rounded-lg border border-gray-200 px-2 py-1.5 transition hover:border-[var(--brand)] hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[var(--brand)] focus:ring-offset-1">
                                        <span class="flex items-center gap-1.5 text-[11px] sm:text-xs text-gray-600 group-hover:text-gray-900">
                                            <span class="h-2 w-2 shrink-0 rounded-full bg-red-500"></span>
                                            {{ __('Rejected') }}
                                        </span>
                                        <span class="text-xs sm:text-sm font-semibold text-gray-900" x-text="$store.leaveCounts.rejected">{{ $leaveCounts['rejected'] }}</span>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="lg:col-span-2 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="px-4 py-3 sm:px-5 sm:py-3.5 border-b border-gray-100 flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-medium text-sm sm:text-base text-gray-900">{{ __('Pending Approvals') }}</h3>
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700"
                                              x-text="$store.leaveCounts.pending">{{ $leaveCounts['pending'] }}</span>
                                    </div>
                                    <p class="mt-0.5 text-xs sm:text-sm text-gray-500" x-text="pendingSummary()">
                                        {{ __(':count request(s) awaiting your decision.', ['count' => $leaveCounts['pending']]) }}
                                    </p>
                                </div>
                                <a href="{{ route('admin.leave-requests.index') }}"
                                   class="text-xs sm:text-sm text-[var(--brand)] hover:opacity-80">{{ __('View all') }}</a>
                            </div>

                            <div class="divide-y divide-gray-100">
                                @forelse ($leaveRequests['latest'] as $leaveRequest)
                                    @php
                                        $staffName = $leaveRequest->user?->name ?? __('Former Staff');
                                        $initials = collect(preg_split('/\s+/', trim($staffName)))
                                            ->filter()
                                            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
                                            ->take(2)
                                            ->implode('');

                                        $typeSlug = strtolower($leaveRequest->leaveType?->slug ?? '');
                                        $typeStyle = $leaveTypeStyles[$typeSlug] ?? 'bg-indigo-50 text-indigo-700 ring-indigo-600/20';

                                        $today = \Illuminate\Support\Carbon::today();
                                        $startsAt = $leaveRequest->start_date->copy()->startOfDay();
                                        $endsAt = $leaveRequest->end_date->copy()->startOfDay();
                                        $daysUntil = (int) $today->copy()->diff($startsAt)->days;

                                        if ($endsAt->lt($today)) {
                                            $whenLabel = __('Ended');
                                            $whenStyle = 'bg-gray-100 text-gray-600 ring-gray-500/20';
                                        } elseif ($startsAt->eq($today)) {
                                            $whenLabel = __('Starts today');
                                            $whenStyle = 'bg-sky-100 text-sky-700 ring-sky-500/20';
                                        } elseif ($startsAt->lt($today)) {
                                            $whenLabel = __('In progress');
                                            $whenStyle = 'bg-sky-100 text-sky-700 ring-sky-500/20';
                                        } elseif ($daysUntil <= 3) {
                                            $whenLabel = __('Starts in :days day(s)', ['days' => $daysUntil]);
                                            $whenStyle = 'bg-amber-100 text-amber-700 ring-amber-500/20';
                                        } else {
                                            $whenLabel = __('Upcoming');
                                            $whenStyle = 'bg-gray-100 text-gray-600 ring-gray-500/20';
                                        }
                                    @endphp

                                    <div x-data="approvalItem({ id: {{ $leaveRequest->id }}, remarks: @js($leaveRequest->remarks ?? '') })"
                                         x-show="! decided"
                                         x-transition:enter="ease-out duration-200"
                                         x-transition:enter-start="opacity-0 translate-y-1"
                                         x-transition:enter-end="opacity-100 translate-y-0"
                                         x-transition:leave="ease-in duration-150"
                                         x-transition:leave-start="opacity-100"
                                         x-transition:leave-end="opacity-0 -translate-y-1"
                                         class="p-3 sm:p-4 transition hover:bg-gray-50/60">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-start gap-2.5">
                                                    @if ($leaveRequest->user?->profile_photo_path)
                                                        <img src="{{ Storage::disk('public')->url($leaveRequest->user->profile_photo_path) }}"
                                                             alt="{{ $staffName }}"
                                                             class="h-8 w-8 sm:h-9 sm:w-9 shrink-0 rounded-full object-cover">
                                                    @else
                                                        <span class="flex h-8 w-8 sm:h-9 sm:w-9 shrink-0 items-center justify-center rounded-full bg-[var(--brand)] text-xs sm:text-sm font-semibold text-white"
                                                              aria-hidden="true">{{ $initials }}</span>
                                                    @endif

                                                    <div class="min-w-0">
                                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                                            <span class="font-medium text-gray-900">{{ $staffName }}</span>
                                                            @if ($leaveRequest->user?->employee_id)
                                                                <span class="text-xs text-gray-500">{{ $leaveRequest->user->employee_id }}</span>
                                                            @endif
                                                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $typeStyle }}">
                                                                {{ $leaveRequest->leaveType?->name ?? __('Unknown') }}
                                                            </span>
                                                        </div>

                                                        <div class="mt-1.5 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xs sm:text-sm text-gray-600">
                                                            <span class="inline-flex items-center gap-1.5">
                                                                <svg class="h-3.5 w-3.5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                                                                </svg>
                                                                {{ $leaveRequest->start_date->format('d M Y') }} – {{ $leaveRequest->end_date->format('d M Y') }}
                                                            </span>
                                                            <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-500/20">
                                                                {{ (float) $leaveRequest->days }} {{ __('day(s)') }}
                                                            </span>
                                                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $whenStyle }}">
                                                                {{ $whenLabel }}
                                                            </span>
                                                        </div>

                                                        @if ($leaveRequest->reason)
                                                            <p class="mt-2 text-sm text-gray-600 line-clamp-2">{{ $leaveRequest->reason }}</p>
                                                        @endif

                                                        @if (filled($leaveRequest->remarks))
                                                            <div class="mt-2 rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-600"
                                                                 x-show="remarks !== '' && ! editingRemarks"
                                                                 x-text="remarks"
                                                                 style="display: none;">
                                                                <span class="font-medium text-gray-500">{{ __('Remarks') }}:</span>
                                                                {{ $leaveRequest->remarks }}
                                                            </div>
                                                        @else
                                                            <div class="mt-2 rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-600"
                                                                 x-show="remarks !== '' && ! editingRemarks"
                                                                 x-text="remarks"
                                                                 style="display: none;"></div>
                                                        @endif

                                                        <details class="group mt-2">
                                                            <summary class="inline-flex cursor-pointer list-none items-center gap-1 text-xs font-medium text-[var(--brand)] hover:opacity-80 focus:outline-none focus:ring-2 focus:ring-[var(--brand)] focus:ring-offset-2 rounded">
                                                                <svg class="h-3.5 w-3.5 transition-transform group-open:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                                                </svg>
                                                                {{ __('Details') }}
                                                            </summary>

                                                            <div class="details-panel mt-3 space-y-3 rounded-lg border border-gray-100 bg-gray-50/60 p-3">
                                                                @if ($leaveRequest->reason)
                                                                    <div>
                                                                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('Reason') }}</div>
                                                                        <p class="mt-1 text-sm text-gray-700">{{ $leaveRequest->reason }}</p>
                                                                    </div>
                                                                @endif

                                                                @if ($leaveRequest->hasAttachment())
                                                                    <div>
                                                                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('Attachment') }}</div>
                                                                        <a href="{{ Storage::disk('public')->url($leaveRequest->attachment_path) }}" target="_blank"
                                                                           class="mt-1 inline-flex items-center gap-1.5 text-sm text-[var(--brand)] hover:opacity-80">
                                                                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.8 7.8a5.528 5.528 0 01-7.84-7.84l7.84-7.84a5.528 5.528 0 017.84 7.84l-.01.01" />
                                                                            </svg>
                                                                            {{ __('View attachment') }}
                                                                        </a>
                                                                    </div>
                                                                @endif

                                                                <div>
                                                                    <div class="flex items-center justify-between gap-2">
                                                                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('Remarks') }}</div>
                                                                        <button type="button"
                                                                                x-show="! editingRemarks"
                                                                                @click="editingRemarks = true; $nextTick(() => $refs.remarksBox && $refs.remarksBox.focus())"
                                                                                class="text-xs font-medium text-[var(--brand)] hover:opacity-80">{{ __('Edit') }}</button>
                                                                    </div>

                                                                    <form method="POST" action="{{ route('admin.leave-requests.remarks', $leaveRequest) }}"
                                                                          x-show="editingRemarks" x-cloak
                                                                          @submit.prevent="submit($event)" class="mt-2 space-y-2">
                                                                        @csrf
                                                                        @method('PATCH')
                                                                        <textarea name="remarks" rows="2" x-ref="remarksBox" :value="remarks"
                                                                            placeholder="{{ __('Add remarks') }}"
                                                                            class="block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                                                        <div class="flex items-center gap-3">
                                                                            <button type="submit" :disabled="busy"
                                                                                class="inline-flex items-center gap-1.5 rounded-md bg-[var(--brand)] px-3 py-1.5 text-xs font-semibold text-white hover:opacity-90 disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-[var(--brand)] focus:ring-offset-1 transition">
                                                                                {{ __('Save remarks') }}
                                                                                <svg x-show="busy" class="h-3 w-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                                                                </svg>
                                                                            </button>
                                                                            <button type="button" @click="editingRemarks = false"
                                                                                class="text-xs font-medium text-gray-500 hover:text-gray-700">{{ __('Cancel') }}</button>
                                                                        </div>
                                                                    </form>

                                                                    <p x-show="! editingRemarks && remarks !== ''" x-text="remarks"
                                                                       class="mt-1 text-sm text-gray-700"></p>
                                                                    <p x-show="! editingRemarks && remarks === ''" x-cloak
                                                                       class="mt-1 text-sm text-gray-400">{{ __('No remarks yet.') }}</p>
                                                                </div>
                                                            </div>
                                                        </details>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="flex shrink-0 flex-col items-stretch gap-2 sm:items-end">
                                                <form method="POST" action="{{ route('admin.leave-requests.approve', $leaveRequest) }}"
                                                      @submit.prevent="submit($event)">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" :disabled="busy"
                                                        class="inline-flex w-full items-center justify-center gap-1.5 rounded-md bg-green-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-green-700 disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition sm:w-auto">
                                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                        </svg>
                                                        <span>{{ __('Approve') }}</span>
                                                        <svg x-show="busy" x-cloak class="h-3.5 w-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                                        </svg>
                                                    </button>
                                                </form>

                                                <details class="group/reject w-full sm:w-60">
                                                    <summary class="inline-flex w-full cursor-pointer list-none items-center justify-center gap-1.5 rounded-md border border-red-200 bg-white px-3 py-1.5 text-xs font-semibold text-red-600 shadow-sm transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                        <span>{{ __('Reject') }}</span>
                                                    </summary>

                                                    <form method="POST" action="{{ route('admin.leave-requests.reject', $leaveRequest) }}"
                                                          @submit.prevent="submit($event)"
                                                          class="details-panel mt-2 space-y-2 rounded-lg border border-red-100 bg-red-50/50 p-3">
                                                        @csrf
                                                        @method('PATCH')
                                                        <label for="rejection_reason_{{ $leaveRequest->id }}" class="block text-xs font-medium text-gray-600">
                                                            {{ __('Reason (optional)') }}
                                                        </label>
                                                        <textarea id="rejection_reason_{{ $leaveRequest->id }}" name="rejection_reason" rows="2"
                                                            placeholder="{{ __('Why is this request rejected?') }}"
                                                            class="block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-red-500 focus:ring-red-500"></textarea>
                                                        <div class="flex items-center gap-2">
                                                            <button type="submit" :disabled="busy"
                                                                class="inline-flex items-center gap-1.5 rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-red-700 disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1 transition">
                                                                {{ __('Confirm reject') }}
                                                                <svg x-show="busy" x-cloak class="h-3 w-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </form>
                                                </details>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="p-4 sm:p-5">
                                        <div class="flex flex-col items-center text-center">
                                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-green-100 text-green-600">
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                </svg>
                                            </span>
                                            <p class="mt-3 text-sm font-medium text-gray-900">{{ __('All caught up.') }}</p>
                                            <p class="mt-1 text-sm text-gray-500">{{ __('There are no leave requests waiting for your decision.') }}</p>
                                        </div>
                                    </div>
                                @endforelse
                            </div>

                            @if ($pendingShown > 0)
                                <div class="border-t border-gray-100 px-3 py-2.5 text-center sm:px-5" x-cloak>
                                    <template x-if="$store.leaveCounts.pending === 0">
                                        <p class="text-sm text-gray-500">{{ __('All caught up. No requests awaiting your decision.') }}</p>
                                    </template>
                                    <template x-if="$store.leaveCounts.pending > 0">
                                        <a href="{{ route('admin.leave-requests.index') }}" class="text-sm font-medium text-[var(--brand)] hover:opacity-80">
                                            {{ __('View all pending requests') }} (<span x-text="$store.leaveCounts.pending">{{ $leaveCounts['pending'] }}</span>)
                                        </a>
                                    </template>
                                </div>
                            @endif
                        </div>
                    </div>

                    @php
                        $lrFilter = $leaveRequests['filter'] ?? ['year' => (int) now()->year, 'month' => null, 'staff' => null, 'status' => 'all'];
                        $lrYearOptions = range(min((int) now()->year - 3, $lrFilter['year']), max((int) now()->year + 3, $lrFilter['year']));
                        $lrMonthOptions = [
                            1 => __('January'),
                            2 => __('February'),
                            3 => __('March'),
                            4 => __('April'),
                            5 => __('May'),
                            6 => __('June'),
                            7 => __('July'),
                            8 => __('August'),
                            9 => __('September'),
                            10 => __('October'),
                            11 => __('November'),
                            12 => __('December'),
                        ];
                        $lrPeriodLabel = ($lrFilter['month'] ? ($lrMonthOptions[$lrFilter['month']].' ') : '').$lrFilter['year'];
                    @endphp

                    <!-- Leave Request History (Yearly & Monthly Filter per Staff) -->
                    <div id="leave-request-history" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="px-4 py-3 sm:px-5 sm:py-3.5 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <div class="text-[11px] font-semibold uppercase tracking-wider text-[var(--brand)]">{{ __('Leave History') }}</div>
                                <h3 class="text-sm sm:text-base font-semibold text-gray-900">{{ __('Leave Request History (:period)', ['period' => $lrPeriodLabel]) }}</h3>
                                <p class="mt-0.5 text-xs text-gray-500">
                                    {{ __('Monitor all pending, approved and rejected leave requests for each staff member by month and year.') }}
                                </p>
                            </div>
                            <a href="{{ route('admin.leave-requests.index', array_filter(['status' => $lrFilter['status'], 'year' => $lrFilter['year'], 'month' => $lrFilter['month'], 'staff' => $lrFilter['staff']])) }}"
                               class="text-xs sm:text-sm font-medium text-[var(--brand)] hover:opacity-80">{{ __('Open full Leave Requests page') }} →</a>
                        </div>

                        <div class="px-4 py-3 sm:px-5 sm:py-4 space-y-3 sm:space-y-4">
                            <form method="GET" action="{{ route('admin.dashboard') }}#leave-request-history" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-2.5 sm:gap-3 items-end rounded-lg border border-gray-200 bg-gray-50/70 p-3">
                                <div>
                                    <x-input-label for="lr_staff" :value="__('Staff Member')" />
                                    <select id="lr_staff" name="lr_staff"
                                        class="mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                                        <option value="">{{ __('All Staff') }}</option>
                                        @foreach ($leaveRequests['staffList'] ?? [] as $s)
                                            <option value="{{ $s->id }}" @selected($lrFilter['staff'] === $s->id)>
                                                {{ $s->name }} @if ($s->employee_id) ({{ $s->employee_id }}) @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <x-input-label for="lr_month" :value="__('Month (Monthly / Yearly)')" />
                                    <select id="lr_month" name="lr_month"
                                        class="mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                                        <option value="">{{ __('All Months (Full Year)') }}</option>
                                        @foreach ($lrMonthOptions as $mNum => $mLabel)
                                            <option value="{{ $mNum }}" @selected($lrFilter['month'] === $mNum)>{{ $mLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <x-input-label for="lr_year" :value="__('Year')" />
                                    <select id="lr_year" name="lr_year"
                                        class="mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                                        @foreach ($lrYearOptions as $yr)
                                            <option value="{{ $yr }}" @selected($lrFilter['year'] === $yr)>{{ $yr }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <x-input-label for="lr_status" :value="__('Status')" />
                                    <select id="lr_status" name="lr_status"
                                        class="mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                                        <option value="all" @selected($lrFilter['status'] === 'all')>{{ __('All (Pending, Approved, Rejected)') }}</option>
                                        <option value="pending" @selected($lrFilter['status'] === 'pending')>{{ __('Pending') }}</option>
                                        <option value="approved" @selected($lrFilter['status'] === 'approved')>{{ __('Approved') }}</option>
                                        <option value="rejected" @selected($lrFilter['status'] === 'rejected')>{{ __('Rejected') }}</option>
                                    </select>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <x-primary-button class="w-full sm:w-auto justify-center gap-1.5 py-2 text-xs">
                                        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
                                        </svg>
                                        {{ __('Filter') }}
                                    </x-primary-button>
                                    <a href="{{ route('admin.dashboard') }}#leave-request-history"
                                       class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-[11px] font-semibold uppercase tracking-widest text-gray-700 shadow-xs hover:bg-gray-50 transition">
                                        {{ __('Reset') }}
                                    </a>
                                </div>
                            </form>

                            <!-- Staff Summary Table -->
                            <div class="overflow-x-auto rounded-lg border border-gray-200">
                                <table class="min-w-[38rem] w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50 text-[11px] font-semibold uppercase tracking-wider text-gray-500">
                                        <tr class="whitespace-nowrap">
                                            <th class="px-3 py-2.5 text-left">{{ __('Staff') }}</th>
                                            <th class="px-2 py-2.5 text-center">{{ __('Pending') }}</th>
                                            <th class="px-2 py-2.5 text-center">{{ __('Approved') }}</th>
                                            <th class="px-2 py-2.5 text-center">{{ __('Rejected') }}</th>
                                            <th class="px-2 py-2.5 text-right">{{ __('Approved Days') }}</th>
                                            <th class="px-2 py-2.5 text-right">{{ __('Total Requests') }}</th>
                                            <th class="px-3 py-2.5 text-right">{{ __('Action') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white">
                                        @forelse ($leaveRequests['staffSummary'] ?? [] as $row)
                                            @php($member = $row['member'])
                                            <tr class="hover:bg-gray-50/70">
                                                <td class="px-3 py-2">
                                                    <div class="font-medium text-gray-900">{{ $member->name }}</div>
                                                    <div class="text-xs text-gray-500">{{ $member->employee_id }}</div>
                                                </td>
                                                <td class="px-2 py-2 text-center">
                                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $row['pending'] > 0 ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-gray-100 text-gray-500' }}">
                                                        {{ $row['pending'] }}
                                                    </span>
                                                </td>
                                                <td class="px-2 py-2 text-center">
                                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $row['approved'] > 0 ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-gray-100 text-gray-500' }}">
                                                        {{ $row['approved'] }}
                                                    </span>
                                                </td>
                                                <td class="px-2 py-2 text-center">
                                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $row['rejected'] > 0 ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-gray-100 text-gray-500' }}">
                                                        {{ $row['rejected'] }}
                                                    </span>
                                                </td>
                                                <td class="px-2 py-2 text-right font-semibold text-gray-900">{{ $row['approved_days'] }} {{ __('days') }}</td>
                                                <td class="px-2 py-2 text-right font-semibold text-gray-900">{{ $row['total'] }}</td>
                                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                                    <a href="{{ route('admin.leave-requests.index', array_filter(['status' => 'all', 'year' => $lrFilter['year'], 'month' => $lrFilter['month'], 'staff' => $member->id])) }}"
                                                       class="text-xs font-semibold text-[var(--brand)] hover:opacity-80">
                                                        {{ __('View history') }} →
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="px-3 py-5 text-center text-gray-500">{{ __('No staff found.') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div x-show="toast.show" x-transition.opacity x-cloak role="status" aria-live="polite"
                         class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-4 sm:max-w-xs z-50 rounded-lg border px-3 py-2.5 text-xs sm:text-sm font-medium shadow-lg"
                         :class="toastClass()">
                        <span x-text="toast.message"></span>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <a href="{{ route('admin.attendance.index') }}" class="group flex items-start gap-3 bg-white shadow-sm sm:rounded-lg px-4 py-3.5 ring-1 ring-transparent hover:ring-[var(--brand)] hover:shadow-md transition">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-[var(--brand)] group-hover:bg-[var(--brand)] group-hover:text-white transition">
                        <x-icon name="chart-bar" class="h-5 w-5" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center justify-between gap-2">
                            <span class="text-sm font-medium text-gray-900">{{ __('Attendance') }}</span>
                            <x-icon name="arrow-right" class="h-4 w-4 text-gray-300 group-hover:text-[var(--brand)] transition" />
                        </span>
                        <span class="mt-0.5 block text-xs text-gray-500">{{ __('Realtime daily and monthly clock-in/out.') }}</span>
                    </span>
                </a>
                @can('manage-staff')
                    <a href="{{ route('admin.staff.index') }}" class="group flex items-start gap-3 bg-white shadow-sm sm:rounded-lg px-4 py-3.5 ring-1 ring-transparent hover:ring-[var(--brand)] hover:shadow-md transition">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-[var(--brand)] group-hover:bg-[var(--brand)] group-hover:text-white transition">
                            <x-icon name="users" class="h-5 w-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center justify-between gap-2">
                                <span class="text-sm font-medium text-gray-900">{{ __('Staff') }}</span>
                                <x-icon name="arrow-right" class="h-4 w-4 text-gray-300 group-hover:text-[var(--brand)] transition" />
                            </span>
                            <span class="mt-0.5 block text-xs text-gray-500">{{ __('Add, edit and enroll staff members.') }}</span>
                        </span>
                    </a>
                @endcan
                <a href="{{ route('attendance.kiosk') }}" class="group flex items-start gap-3 bg-white shadow-sm sm:rounded-lg px-4 py-3.5 ring-1 ring-transparent hover:ring-[var(--brand)] hover:shadow-md transition">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-[var(--brand)] group-hover:bg-[var(--brand)] group-hover:text-white transition">
                        <x-icon name="check-circle" class="h-5 w-5" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center justify-between gap-2">
                            <span class="text-sm font-medium text-gray-900">{{ __('Clock-in Kiosk') }}</span>
                            <x-icon name="arrow-right" class="h-4 w-4 text-gray-300 group-hover:text-[var(--brand)] transition" />
                        </span>
                        <span class="mt-0.5 block text-xs text-gray-500">{{ __('Open the staff clock-in/out screen.') }}</span>
                    </span>
                </a>
            </div>

            @if ($leave)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-4 py-3 sm:px-5 sm:py-3.5 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="font-medium text-sm sm:text-base text-gray-900">{{ __('Leave Entitlements') }}</h3>
                            <p class="mt-0.5 text-xs text-gray-500">
                                {{ __('Leave balance used and left for each staff member in :year.', ['year' => $leave['year']]) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <form method="GET" action="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
                                <x-input-label for="year" :value="__('Year')" class="sr-only" />
                                <x-year-select :selected="$leave['year']" id="year" />
                            </form>
                            @can('manage-leaves')
                                <a href="{{ route('admin.leaves.index', ['year' => $leave['year']]) }}"
                                   class="text-xs sm:text-sm text-[var(--brand)] hover:opacity-80">{{ __('Manage') }}</a>
                            @endcan
                        </div>
                    </div>

                    @include('admin.leaves._balance_summary', $leave)
                </div>
            @endif

            @can('manage-staff')
                @include('admin.dashboard._salary', [
                    'salaryStaff' => $salaryStaff,
                    'salaryYear' => $salaryYear,
                    'salaryMonth' => $salaryMonth,
                    'salaryTotal' => $salaryTotal,
                    'leave' => $leave,
                ])
            @endcan
        </div>
    </div>
</x-admin-layout>
