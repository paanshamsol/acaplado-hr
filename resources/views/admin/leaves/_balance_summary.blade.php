@php($canManage = auth()->user()->can('manage-leaves'))

<div x-data="{
    search: '',
    staff: @js($staff->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'employee_id' => $m->employee_id])),
    matches(id) {
        if (! this.search) return true;
        const s = this.staff.find(x => x.id === id);
        if (! s) return false;
        const q = this.search.toLowerCase();
        return s.name.toLowerCase().includes(q) || s.employee_id.toLowerCase().includes(q);
    }
}">

    <!-- Search / filter bar -->
    <div class="flex flex-col sm:flex-row gap-2.5 mb-3">
        <div class="relative flex-1">
            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <input type="search" x-model="search" placeholder="{{ __('Search staff…') }}"
                class="block w-full pl-9 pr-3 py-1.5 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand)] focus:border-[var(--brand)]" />
        </div>
        <div class="text-xs text-gray-500 whitespace-nowrap self-center">
            {{ __('Showing') }}
            <span x-text="staff.filter(s => matches(s.id)).length">{{ $staff->count() }}</span>
            {{ __('of') }}
            <span x-text="staff.length">{{ $staff->count() }}</span>
            {{ __('staff') }}
        </div>
        @can('manage-leaves')
            <a href="{{ route('admin.leaves.index', ['year' => $year]) }}"
               class="px-3 py-1.5 text-xs font-medium text-[var(--brand)] bg-[var(--brand)]/10 rounded-md hover:bg-[var(--brand)]/20 transition flex items-center justify-center gap-1.5">
                {{ __('Manage all') }}
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5L6 15" />
                </svg>
            </a>
        @endcan
    </div>

    <!-- Overall summary strip -->
    <div class="grid grid-cols-3 gap-2 sm:gap-3 mb-3">
        <div class="bg-[var(--brand)]/5 rounded-lg p-2.5 sm:p-3 border border-[var(--brand)]/20">
            <div class="text-[10px] sm:text-xs text-[var(--brand)] font-semibold uppercase tracking-wider mb-0.5 leading-tight">{{ __('Total entitlement') }}</div>
            <div class="text-sm sm:text-base font-semibold text-gray-900">
                {{ $staffWithBalances->sum(fn ($i) => $i['memberBalances']->sum(fn ($b) => $b['entitlement'] + $b['carried'])) }}
                {{ __('days') }}
            </div>
        </div>
        <div class="bg-red-50 rounded-lg p-2.5 sm:p-3 border border-red-100">
            <div class="text-[10px] sm:text-xs text-red-600 font-semibold uppercase tracking-wider mb-0.5 leading-tight">{{ __('Total used') }}</div>
            <div class="text-sm sm:text-base font-semibold text-gray-900">
                {{ $staffWithBalances->sum('totalUsed') }}
                {{ __('days') }}
            </div>
        </div>
        <div class="bg-green-50 rounded-lg p-2.5 sm:p-3 border border-green-100">
            <div class="text-[10px] sm:text-xs text-green-600 font-semibold uppercase tracking-wider mb-0.5 leading-tight">{{ __('Total left') }}</div>
            <div class="text-sm sm:text-base font-semibold text-gray-900">
                {{ $staffWithBalances->sum('totalLeft') }}
                {{ __('days') }}
            </div>
        </div>
    </div>

    <!-- Staff cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-2.5 sm:gap-3">
        @forelse ($staffWithBalances as $item)
            @php($member = $item['member'])
            @php($memberBalances = $item['memberBalances'])
            @php($totalUsed = $item['totalUsed'])
            @php($totalLeft = $item['totalLeft'])
            <div x-show="matches({{ $member->id }})"
                 class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-all duration-200 group">
                <div class="p-3">
                    <!-- Staff header -->
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="relative flex-shrink-0">
                                @if ($member->profile_photo_path && Storage::disk('public')->exists($member->profile_photo_path))
                                    <img src="{{ Storage::disk('public')->url($member->profile_photo_path) }}"
                                         class="h-8 w-8 rounded-full object-cover border-gray-200" alt="">
                                @else
                                    <div class="h-8 w-8 rounded-full bg-gray-100 flex items-center justify-center">
                                        <span class="text-xs font-medium text-gray-600">
                                            {{ strtoupper(collect(explode(' ', $member->name))->map(fn ($n) => substr($n, 0, 1))->join('')) }}
                                        </span>
                                    </div>
                                @endif
                                <span class="absolute -right-1 -bottom-1 h-2 w-2 rounded-full {{ $member->status === 'active' ? 'bg-green-500' : 'bg-gray-400' }} border-2 border-white"></span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-xs sm:text-sm font-semibold text-gray-900 truncate">{{ $member->name }}</div>
                                <div class="text-[11px] text-gray-500 truncate">
                                    {{ $member->employee_id }}
                                    @if ($member->getRoleNames()->isNotEmpty())
                                        · {{ $member->getRoleNames()->map(fn ($role) => ucfirst($role))->join(', ') }}
                                    @endif
                                </div>
                            </div>
                        </div>
                        @can('manage-leaves')
                            <a href="{{ route('admin.leaves.edit', ['staff' => $member, 'year' => $year]) }}"
                               class="text-[11px] font-medium text-[var(--brand)] bg-[var(--brand)]/10 px-2 py-0.5 rounded hover:bg-[var(--brand)]/20 transition whitespace-nowrap">
                                {{ __('Edit') }}
                            </a>
                        @endcan
                    </div>

                    <!-- Leave type rows -->
                    <div class="space-y-1.5">
                        @foreach ($memberBalances as $bal)
                            <div class="flex items-center justify-between gap-2 px-2.5 py-1.5 rounded-md bg-gray-50 hover:bg-gray-100 transition-colors">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <span class="text-[11px] font-medium text-gray-700 truncate">{{ $bal['type']->name }}</span>
                                    @if ($bal['type']->requires_attachment)
                                        <span class="text-[var(--brand)] text-[11px]" title="{{ __('Attachment required') }}">📎</span>
                                    @endif
                                    @if ($bal['status'] === 'negative')
                                        <span class="text-red-500 text-[11px]" title="{{ __('Overused') }}">⚠</span>
                                    @endif
                                </div>
                                <div class="w-14 h-1.5 bg-gray-200 rounded-full overflow-hidden flex-shrink-0">
                                    <div class="h-full {{ $bal['bar'] }} w-[{{ $bal['percent'] }}%] transition-all duration-300"></div>
                                </div>
                                <div class="text-right text-[11px] font-semibold {{ $bal['color'] }} whitespace-nowrap w-14">
                                    {{ $bal['remaining'] }} {{ __('left') }}
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Total footer -->
                    <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between">
                        <div>
                            <div class="text-[10px] text-gray-500 uppercase tracking-wider">{{ __('Total left') }}</div>
                            <div class="text-xs font-semibold {{ $totalLeft <= 0 ? 'text-red-600' : ($totalLeft <= 2 ? 'text-amber-600' : 'text-green-600') }}">
                                {{ $totalLeft }}
                            </div>
                        </div>
                        <div class="text-right text-[11px] text-gray-500">
                            <div class="text-gray-700">{{ $totalUsed }} {{ __('used') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full bg-white rounded-lg shadow-sm border border-gray-100 p-8 text-center">
                <p class="text-sm text-gray-500">{{ __('No staff found.') }}</p>
            </div>
        @endforelse
    </div>

</div>
