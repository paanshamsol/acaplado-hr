<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-lg sm:text-xl text-gray-800 leading-tight">
            {{ __('Staff Portal') }}
        </h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center gap-4 sm:gap-5">
                        @php
                            $initials = collect(explode(' ', trim((string) $user->name)))
                                ->filter()
                                ->take(2)
                                ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                                ->implode('');
                        @endphp

                        @if ($user->profile_photo_path)
                            <img src="{{ Storage::disk('public')->url($user->profile_photo_path) }}"
                                 alt="{{ $user->name }}"
                                 class="h-16 w-16 sm:h-20 sm:w-20 shrink-0 rounded-full object-cover ring-2 ring-gray-200">
                        @else
                            <span class="flex h-16 w-16 sm:h-20 sm:w-20 shrink-0 items-center justify-center rounded-full bg-[var(--brand)] text-xl sm:text-2xl font-semibold text-white ring-2 ring-gray-200"
                                  aria-hidden="true">{{ $initials !== '' ? $initials : '?' }}</span>
                        @endif

                        <div class="min-w-0 space-y-1">
                            <p class="text-sm text-gray-500">
                                {{ __('Company :') }}
                                <span class="font-medium text-gray-700">{{ \App\Models\CompanySetting::current()->name }}</span>
                            </p>
                            <p class="text-lg font-medium text-gray-900 truncate">{{ __('Hello,') }} {{ $user->name }}</p>
                            <p class="text-sm text-gray-500">
                                {{ __('Staff ID :') }}
                                <span class="font-medium text-gray-700">{{ $user->employee_id ?: '—' }}</span>
                            </p>
                        </div>
                    </div>

                    <p class="mt-4 text-xs text-gray-400">
                        {{ __('Your photo, name and staff ID are maintained by HR and can only be viewed here.') }}
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="{{ route('staff.leaves.index') }}"
                   class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 hover:bg-gray-50 transition">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Leave') }}</div>
                    <div class="mt-2 text-sm font-medium text-indigo-600">{{ __('Apply or view my leave') }}</div>
                    @if ($pendingRequests > 0)
                        <div class="mt-1 text-xs text-amber-600">{{ __(':count pending request(s)', ['count' => $pendingRequests]) }}</div>
                    @endif
                </a>

                <a href="{{ route('staff.payslips.index') }}"
                   class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 hover:bg-gray-50 transition">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Payslips') }}</div>
                    <div class="mt-2 text-sm font-medium text-indigo-600">{{ __('View or download my payslips') }}</div>
                    @if ($payslipsCount > 0)
                        <div class="mt-1 text-xs text-gray-500">{{ __(':count payslip(s) available', ['count' => $payslipsCount]) }}</div>
                    @else
                        <div class="mt-1 text-xs text-gray-500">{{ __('None yet') }}</div>
                    @endif
                </a>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Clock-in / out') }}</div>
                    <div class="mt-2 text-sm text-gray-500">{{ __('Use the shared kiosk at :path.', ['path' => '/attendance']) }}</div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Today\'s Attendance') }}</div>
                    <div class="mt-2 flex items-center gap-2">
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold
                            @switch($todayStatus)
                                @case('on_time') bg-green-100 text-green-700 @break
                                @case('late') bg-amber-100 text-amber-700 @break
                                @case('absent') bg-red-100 text-red-700 @break
                                @default bg-gray-100 text-gray-700 @endswitch
                        ">
                            @switch($todayStatus)
                                @case('on_time') {{ __('On time') }} @break
                                @case('late') {{ __('Late') }} @break
                                @case('absent') {{ __('Absent') }} @break
                                @default {{ __('No record') }} @endswitch
                        </span>
                        @if ($todayAttendance)
                            <div class="text-sm text-gray-600">
                                {{ __('Clock-in') }}: {{ $todayAttendance->clock_in_at?->format('H:i') ?? __('—') }}
                                @if ($todayAttendance->clock_out_at)
                                    &nbsp;·&nbsp; {{ __('Clock-out') }}: {{ $todayAttendance->clock_out_at->format('H:i') }}
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 border-b border-gray-100">
                    <h3 class="font-medium text-gray-900">{{ __('My leave entitlements (:year)', ['year' => $year]) }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-left">{{ __('Leave type') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Used') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Remaining') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($leaveTypes as $type)
                                @php($balance = $balances->get($type->id))
                                @php($remaining = $balance ? $balance->remaining() : (float) $type->default_days)
                                @php($used = $balance ? (float) $balance->used : 0.0)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $type->name }}</td>
                                    <td class="px-4 py-3 text-right text-gray-900">{{ $used }}</td>
                                    <td class="px-4 py-3 text-right font-semibold {{ $remaining < 0 ? 'text-red-600' : 'text-green-600' }}">{{ $remaining }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
