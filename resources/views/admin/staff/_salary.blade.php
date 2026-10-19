@php
    $yearOptions = range(min((int) now()->year - 3, $salary['year']), max((int) now()->year + 3, $salary['year']));
    $periodLabel = fn (int $year, int $month) => \Illuminate\Support\Carbon::create($year, $month, 1)->format('F Y');
@endphp

<div class="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg"
     x-data="{
         year: {{ $salary['year'] }},
         month: {{ $salary['month'] }},
         rate: {{ $salary['rate'] }},
         hours: {{ $salary['hours'] }},
         days: {{ $salary['days'] }},
         get total() { return Number(this.rate || 0) * Number(this.hours || 0) * Number(this.days || 0); },
         money(v) { return Number(v || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
         reload() { this.$refs.periodForm.submit(); }
     }">
    <div class="px-4 py-3 sm:px-5 sm:py-3.5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <div class="text-[11px] font-semibold uppercase tracking-wider text-[var(--brand)]">{{ __('Payroll') }}</div>
            <h3 class="text-sm sm:text-base font-semibold text-gray-900">{{ __('Salary Adjustment') }}</h3>
            <p class="mt-0.5 text-xs text-gray-500">
                {{ __('Rate / Hr × Total Hours × Total Days. Saving recalculates this period and keeps it in the salary history.') }}
            </p>
        </div>

        <form method="GET" action="{{ route('admin.staff.edit', $staff) }}" x-ref="periodForm" class="flex flex-wrap items-end gap-2">
            <div>
                <label for="salary_month" class="block text-[11px] font-medium text-gray-500">{{ __('Period') }}</label>
                <select id="salary_month" name="month" x-model.number="month" @change="reload()"
                    class="mt-0.5 rounded-md border-gray-300 bg-white py-1.5 pl-2.5 pr-7 text-sm font-medium text-gray-900 shadow-sm focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                    @for ($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected($m === $salary['month'])>{{ \Illuminate\Support\Carbon::create(2000, $m, 1)->format('F') }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label for="salary_year" class="block text-[11px] font-medium text-gray-500">{{ __('Year') }}</label>
                <select id="salary_year" name="year" x-model.number="year" @change="reload()"
                    class="mt-0.5 rounded-md border-gray-300 bg-white py-1.5 pl-2.5 pr-7 text-sm font-medium text-gray-900 shadow-sm focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                    @foreach ($yearOptions as $yr)
                        <option value="{{ $yr }}" @selected($yr === $salary['year'])>{{ $yr }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    <div class="p-4 sm:p-6 space-y-5">
        @if (session('status') === 'salary-updated')
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ __('Salary adjustment saved for :period.', ['period' => session('saved_period', '')]) }}
            </div>
        @endif

        @if ($errors->hasAny(['hourly_rate', 'hours_of_work', 'days_of_work', 'year', 'month']))
            <div class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-5 lg:grid-cols-5">
            <div class="lg:col-span-3">
                <form method="POST" action="{{ route('admin.staff.salary', $staff) }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <input type="hidden" name="year" :value="year" value="{{ old('year', $salary['year']) }}">
                    <input type="hidden" name="month" :value="month" value="{{ old('month', $salary['month']) }}">

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <x-input-label for="hourly_rate" :value="__('Rate / Hour (RM)')" />
                            <input id="hourly_rate" name="hourly_rate" type="number" step="0.01" min="0" required
                                x-model.number="rate" value="{{ old('hourly_rate', $salary['rate']) }}"
                                class="mt-1 block w-full rounded-lg border-gray-300 text-sm text-right shadow-sm focus:border-[var(--brand)] focus:ring-[var(--brand)]" />
                            <x-input-error :messages="$errors->get('hourly_rate')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="hours_of_work" :value="__('Total Hours')" />
                            <input id="hours_of_work" name="hours_of_work" type="number" step="0.5" min="0" max="9999.99" required
                                x-model.number="hours" value="{{ old('hours_of_work', $salary['hours']) }}"
                                class="mt-1 block w-full rounded-lg border-gray-300 text-sm text-right shadow-sm focus:border-[var(--brand)] focus:ring-[var(--brand)]" />
                            <x-input-error :messages="$errors->get('hours_of_work')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="days_of_work" :value="__('Total Days')" />
                            <input id="days_of_work" name="days_of_work" type="number" step="1" min="0" max="366" required
                                x-model.number="days" value="{{ old('days_of_work', $salary['days']) }}"
                                class="mt-1 block w-full rounded-lg border-gray-300 text-sm text-right shadow-sm focus:border-[var(--brand)] focus:ring-[var(--brand)]" />
                            <x-input-error :messages="$errors->get('days_of_work')" class="mt-2" />
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-green-100 bg-green-50/60 px-4 py-3">
                        <div>
                            <div class="text-[11px] font-semibold uppercase tracking-wider text-green-700">{{ __('Basic Pay') }}</div>
                            <div class="text-xs text-green-700/80">
                                RM <span x-text="money(rate)">{{ number_format((float) $salary['rate'], 2) }}</span>
                                × <span x-text="hours">{{ (float) $salary['hours'] }}</span>
                                × <span x-text="days">{{ (int) $salary['days'] }}</span>
                            </div>
                        </div>
                        <div class="text-lg sm:text-xl font-semibold text-green-700">
                            RM <span x-text="money(total)">{{ number_format((float) $salary['rate'] * (float) $salary['hours'] * (int) $salary['days'], 2) }}</span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <x-primary-button>{{ __('Save Salary Adjustment') }}</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="lg:col-span-2">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">{{ __('Salary History') }}</div>

                @if ($adjustments->isEmpty())
                    <p class="mt-2 rounded-lg border border-dashed border-gray-200 px-3 py-6 text-center text-xs text-gray-500">
                        {{ __('No salary adjustments saved yet.') }}
                    </p>
                @else
                    <div class="mt-2 overflow-hidden rounded-lg border border-gray-200">
                        <table class="w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-[11px] font-semibold uppercase tracking-wider text-gray-500">
                                <tr>
                                    <th scope="col" class="px-3 py-2 text-left">{{ __('Period') }}</th>
                                    <th scope="col" class="px-3 py-2 text-right">{{ __('Basic') }}</th>
                                    <th scope="col" class="px-2 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach ($adjustments as $adjustment)
                                    @php
                                        $isLoaded = $adjustment->year === $salary['year'] && $adjustment->month === $salary['month'];
                                    @endphp
                                    <tr class="{{ $isLoaded ? 'bg-green-50/40' : '' }}">
                                        <td class="px-3 py-2 align-middle">
                                            <div class="font-medium text-gray-900">{{ $periodLabel($adjustment->year, $adjustment->month) }}</div>
                                            <div class="text-xs text-gray-500">
                                                RM {{ number_format((float) $adjustment->hourly_rate, 2) }}
                                                × {{ (float) $adjustment->hours_of_work }}
                                                × {{ (int) $adjustment->days_of_work }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-right align-middle whitespace-nowrap text-gray-900">
                                            RM {{ number_format((float) $adjustment->total(), 2) }}
                                        </td>
                                        <td class="px-2 py-2 text-right align-middle whitespace-nowrap">
                                            @if ($isLoaded)
                                                <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-green-700">{{ __('In form') }}</span>
                                            @else
                                                <form method="GET" action="{{ route('admin.staff.edit', $staff) }}">
                                                    <input type="hidden" name="year" value="{{ $adjustment->year }}">
                                                    <input type="hidden" name="month" value="{{ $adjustment->month }}">
                                                    <button type="submit"
                                                        class="rounded-md border border-gray-300 bg-white px-2 py-1 text-[11px] font-semibold text-gray-700 shadow-xs hover:bg-gray-50 transition">
                                                        {{ __('Load') }}
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
