@php
    $currentYear = (int) now()->year;
    $yearOptions = range(min($currentYear - 3, $year), max($currentYear + 3, $year));
    $monthOptions = [
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
    $periodTitle = ($month ? ($monthOptions[$month].' ') : '').$year;
@endphp

<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-lg sm:text-xl text-gray-800 leading-tight">
                    {{ __('Leave Requests & History') }}
                </h2>
                <p class="mt-0.5 text-sm text-gray-500">
                    {{ __('Monitor pending, approved and rejected leave requests for each staff member (:period).', ['period' => $periodTitle]) }}
                </p>
            </div>
            <a href="{{ route('admin.leave-requests.export', ['year' => $year, 'month' => $month]) }}"
               class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 shadow-xs hover:bg-gray-50 transition">
                {{ __('Export CSV') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status') === 'leave-approved')
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ __('Leave request approved.') }}</div>
            @endif
            @if (session('status') === 'leave-rejected')
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ __('Leave request rejected.') }}</div>
            @endif
            @if (session('status') === 'leave-already-decided')
                <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-700">{{ __('This request has already been decided.') }}</div>
            @endif
            @if (session('status') === 'leave-remarks-saved')
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ __('Remarks saved.') }}</div>
            @endif

            <!-- Filter Bar -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                <form method="GET" action="{{ route('admin.leave-requests.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
                    <div>
                        <x-input-label for="filter_staff" :value="__('Staff Member')" />
                        <select id="filter_staff" name="staff"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                            <option value="">{{ __('All Staff') }}</option>
                            @foreach ($staffList as $s)
                                <option value="{{ $s->id }}" @selected($staffId === $s->id)>
                                    {{ $s->name }} @if ($s->employee_id) ({{ $s->employee_id }}) @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="filter_month" :value="__('Month (Monthly / Yearly)')" />
                        <select id="filter_month" name="month"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                            <option value="">{{ __('All Months (Full Year)') }}</option>
                            @foreach ($monthOptions as $mNum => $mLabel)
                                <option value="{{ $mNum }}" @selected($month === $mNum)>{{ $mLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="filter_year" :value="__('Year')" />
                        <select id="filter_year" name="year"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                            @foreach ($yearOptions as $yr)
                                <option value="{{ $yr }}" @selected($year === $yr)>{{ $yr }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="filter_status" :value="__('Status')" />
                        <select id="filter_status" name="status"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                            <option value="all" @selected($status === 'all')>{{ __('All Statuses') }}</option>
                            <option value="pending" @selected($status === 'pending')>{{ __('Pending') }}</option>
                            <option value="approved" @selected($status === 'approved')>{{ __('Approved') }}</option>
                            <option value="rejected" @selected($status === 'rejected')>{{ __('Rejected') }}</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <x-primary-button class="w-full sm:w-auto justify-center gap-1.5 py-2.5">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
                            </svg>
                            {{ __('Filter') }}
                        </x-primary-button>
                        <a href="{{ route('admin.leave-requests.index', ['status' => 'all', 'year' => now()->year]) }}"
                           class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-xs hover:bg-gray-50 transition">
                            {{ __('Reset') }}
                        </a>
                    </div>
                </form>
            </div>

            <!-- Status Tabs -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap gap-2">
                    @foreach (['all' => __('All Requests'), 'pending' => __('Pending'), 'approved' => __('Approved'), 'rejected' => __('Rejected')] as $key => $label)
                        <a href="{{ route('admin.leave-requests.index', array_filter(['status' => $key, 'year' => $year, 'month' => $month, 'staff' => $staffId])) }}"
                           class="inline-flex items-center gap-2 rounded-md px-3.5 py-2 text-sm font-medium transition {{ $status === $key ? 'bg-[var(--brand)] text-white shadow-xs' : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-200' }}">
                            {{ $label }}
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $status === $key ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600' }}">{{ $counts[$key] ?? 0 }}</span>
                        </a>
                    @endforeach
                </div>

                <div class="text-xs font-medium text-gray-500">
                    {{ __('Showing records for:') }} <span class="font-semibold text-gray-800">{{ $periodTitle }}</span>
                </div>
            </div>

            <!-- Leave Requests Table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900">{{ __('Leave Request Records (:period)', ['period' => $periodTitle]) }}</h3>
                    <span class="text-xs text-gray-500">{{ __(':count record(s)', ['count' => $requests->total()]) }}</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr class="whitespace-nowrap">
                                <th class="px-4 py-3 text-left">{{ __('Staff') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Type') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Dates') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Days') }}</th>
                                <th class="px-4 py-3 text-center">{{ __('Status') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Details') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Remarks') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse ($requests as $leaveRequest)
                                <tr class="hover:bg-gray-50/70">
                                    <td class="px-4 py-3 align-top">
                                        <div class="font-medium text-gray-900">{{ $leaveRequest->user?->name ?? __('Former Staff') }}</div>
                                        <div class="text-xs text-gray-500">{{ $leaveRequest->user?->employee_id ?? '' }}</div>
                                    </td>
                                    <td class="px-4 py-3 align-top text-gray-900">{{ $leaveRequest->leaveType?->name ?? __('Unknown') }}</td>
                                    <td class="px-4 py-3 align-top whitespace-nowrap text-gray-700">
                                        <div>{{ $leaveRequest->start_date->format('d M Y') }} – {{ $leaveRequest->end_date->format('d M Y') }}</div>
                                        <div class="text-xs text-gray-400">{{ $leaveRequest->start_date->format('F Y') }}</div>
                                    </td>
                                    <td class="px-4 py-3 align-top text-right font-semibold text-gray-900">{{ (float) $leaveRequest->days }}</td>
                                    <td class="px-4 py-3 align-top text-center whitespace-nowrap">
                                        @if ($leaveRequest->status === 'approved')
                                            <span class="inline-flex items-center rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-semibold text-green-700 border border-green-200">{{ __('Approved') }}</span>
                                        @elseif ($leaveRequest->status === 'rejected')
                                            <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-700 border border-red-200">{{ __('Rejected') }}</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 border border-amber-200">{{ __('Pending') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        @if ($leaveRequest->reason)
                                            <div class="text-gray-700">{{ $leaveRequest->reason }}</div>
                                        @endif
                                        @if ($leaveRequest->hasAttachment())
                                            <a href="{{ Storage::disk('public')->url($leaveRequest->attachment_path) }}" target="_blank"
                                               class="text-xs text-indigo-600 hover:text-indigo-900">{{ __('Attachment') }}</a>
                                        @endif
                                        @if ($leaveRequest->status !== 'pending')
                                            <div class="text-xs text-gray-500">
                                                {{ ucfirst($leaveRequest->status) }}
                                                @if ($leaveRequest->approver)
                                                    {{ __('by') }} {{ $leaveRequest->approver->name }}
                                                @endif
                                                @if ($leaveRequest->decided_at)
                                                    · {{ $leaveRequest->decided_at->format('d M Y') }}
                                                @endif
                                            </div>
                                            @if ($leaveRequest->rejection_reason)
                                                <div class="text-xs text-red-600">{{ $leaveRequest->rejection_reason }}</div>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <form method="POST" action="{{ route('admin.leave-requests.remarks', $leaveRequest) }}"
                                              class="w-52 space-y-1.5">
                                            @csrf
                                            @method('PATCH')
                                            <textarea name="remarks" rows="2" placeholder="{{ __('Add remarks') }}"
                                                class="block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('remarks', $leaveRequest->remarks) }}</textarea>
                                            <button type="submit"
                                                class="text-xs font-medium text-[var(--brand)] hover:opacity-80">{{ __('Save remarks') }}</button>
                                        </form>
                                    </td>
                                    <td class="px-4 py-3 align-top text-right">
                                        @if ($leaveRequest->isPending())
                                            <div class="flex flex-col items-end gap-2">
                                                <form method="POST" action="{{ route('admin.leave-requests.approve', $leaveRequest) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="inline-flex items-center rounded-md bg-green-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-green-700">{{ __('Approve') }}</button>
                                                </form>

                                                <details class="w-52 text-left">
                                                    <summary class="cursor-pointer list-none text-right text-xs font-semibold text-red-600 hover:text-red-900">{{ __('Reject') }}</summary>
                                                    <form method="POST" action="{{ route('admin.leave-requests.reject', $leaveRequest) }}" class="mt-2 space-y-2">
                                                        @csrf
                                                        @method('PATCH')
                                                        <textarea name="rejection_reason" rows="2" placeholder="{{ __('Reason (optional)') }}"
                                                            class="block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-red-500 focus:ring-red-500"></textarea>
                                                        <x-danger-button class="w-full justify-center">{{ __('Confirm reject') }}</x-danger-button>
                                                    </form>
                                                </details>
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-400">{{ __('Decided') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-gray-500">{{ __('No leave requests found for this period.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($requests->hasPages())
                    <div class="border-t border-gray-100 px-4 py-3">
                        {{ $requests->links() }}
                    </div>
                @endif
            </div>

            <!-- Per-Staff Leave Request History Summary (Pending / Approved / Rejected) -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="font-semibold text-gray-900">{{ __('Staff Leave Request Summary (:period)', ['period' => $periodTitle]) }}</h3>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ __('Overview of pending, approved and rejected leave requests recorded for each staff member.') }}
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr class="whitespace-nowrap">
                                <th class="px-4 py-3 text-left">{{ __('Staff') }}</th>
                                <th class="px-4 py-3 text-center">{{ __('Pending') }}</th>
                                <th class="px-4 py-3 text-center">{{ __('Approved') }}</th>
                                <th class="px-4 py-3 text-center">{{ __('Rejected') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Approved Days') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Total Requests') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse ($staffSummary as $row)
                                @php($member = $row['member'])
                                <tr class="hover:bg-gray-50/70">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">{{ $member->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $member->employee_id }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $row['pending'] > 0 ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-gray-100 text-gray-500' }}">
                                            {{ $row['pending'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $row['approved'] > 0 ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-gray-100 text-gray-500' }}">
                                            {{ $row['approved'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $row['rejected'] > 0 ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-gray-100 text-gray-500' }}">
                                            {{ $row['rejected'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900">{{ $row['approved_days'] }} {{ __('days') }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900">{{ $row['total'] }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <a href="{{ route('admin.leave-requests.index', array_filter(['status' => 'all', 'year' => $year, 'month' => $month, 'staff' => $member->id])) }}"
                                           class="text-xs font-semibold text-[var(--brand)] hover:opacity-80">
                                            {{ __('View requests') }} →
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">{{ __('No staff found.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
