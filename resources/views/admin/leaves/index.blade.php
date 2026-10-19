<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-lg sm:text-xl text-gray-800 leading-tight">
                {{ __('Leave Entitlements') }}
            </h2>

            <div class="flex flex-wrap items-center gap-2">
                @can('manage-leaves')
                    <form method="POST" action="{{ route('admin.leaves.generate') }}"
                          onsubmit="return confirm('Generate leave entitlements for {{ $year + 1 }} from {{ $year }}? Existing used days are kept.');">
                        @csrf
                        <input type="hidden" name="year" value="{{ $year }}">
                        <x-secondary-button type="submit" class="gap-1">
                            {{ __('Generate :year', ['year' => $year + 1]) }}
                        </x-secondary-button>
                    </form>
                @endcan

                <form method="GET" action="{{ route('admin.leaves.index') }}" class="flex items-center gap-2">
                    <x-input-label for="year" :value="__('Year')" class="sr-only" />
                    <x-year-select :selected="$year" id="year" />
                    <x-primary-button>{{ __('View') }}</x-primary-button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if (session('status') === 'leaves-updated')
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ __('Leave entitlements updated.') }}</div>
            @endif
            @if (session('status') === 'leaves-deleted')
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ __('Leave entitlements deleted.') }}</div>
            @endif
            @if (session('status') === 'leaves-generated')
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">
                    {{ __('Generated :year entitlements from :from for :count staff member(s).', [
                        'year' => session('leaves-generated-year'),
                        'from' => session('leaves-generated-year') - 1,
                        'count' => session('leaves-generated-count'),
                    ]) }}
                </div>
            @endif

            <p class="text-sm text-gray-500">
                {{ __('Annual, medical, emergency and bereavement entitlements per staff member for :year.', ['year' => $year]) }}
            </p>
            <p class="text-sm text-gray-500">
                {{ __('Annual leave carries up to :limit unused day(s) into the next year; medical (14), emergency (3) and bereavement (3) renew each year. When those run out, the surplus is deducted from annual leave.', ['limit' => \App\Support\LeaveRollover::ANNUAL_CARRY_OVER_LIMIT]) }}
            </p>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                @include('admin.leaves._matrix', [
                    'leaveTypes' => $leaveTypes,
                    'staff' => $staff,
                    'balances' => $balances,
                    'year' => $year,
                ])
            </div>
        </div>
    </div>
</x-admin-layout>
