@php
    $periodLabel = \Illuminate\Support\Carbon::create($salaryYear, $salaryMonth, 1)->format('F Y');
    $salaryYearOptions = range(min((int) now()->year - 5, $salaryYear), max((int) now()->year + 1, $salaryYear));
    $leaveYear = $leave['year'] ?? now()->year;
@endphp

<div id="salary-payslips" class="scroll-mt-16 bg-white overflow-hidden shadow-sm sm:rounded-lg">
    <div class="px-4 py-3 sm:px-5 sm:py-3.5 border-b border-gray-100 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="font-medium text-sm sm:text-base text-gray-900">{{ __('Salary & Payslips') }}</h3>
                <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-green-700">{{ $periodLabel }}</span>
            </div>
            <p class="mt-0.5 text-xs text-gray-500">
                {{ __('Basic pay = rate per hour × total hours × total days. Saving generates and syncs the payslip for that employee and period.') }}
            </p>
        </div>

        <form method="GET" action="{{ route('admin.dashboard') }}#salary-payslips" class="flex flex-wrap items-end gap-2">
            <input type="hidden" name="year" value="{{ $leaveYear }}">
            <div>
                <label for="salary_month" class="block text-[11px] font-medium text-gray-500">{{ __('Month') }}</label>
                <select id="salary_month" name="salary_month" onchange="this.form.submit()"
                    class="mt-0.5 rounded-md border-gray-300 bg-white py-1.5 pl-2.5 pr-7 text-sm font-medium text-gray-900 shadow-sm focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                    @for ($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected($m === $salaryMonth)>{{ \Illuminate\Support\Carbon::create(2000, $m, 1)->format('F') }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label for="salary_year" class="block text-[11px] font-medium text-gray-500">{{ __('Year') }}</label>
                <select id="salary_year" name="salary_year" onchange="this.form.submit()"
                    class="mt-0.5 rounded-md border-gray-300 bg-white py-1.5 pl-2.5 pr-7 text-sm font-medium text-gray-900 shadow-sm focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                    @foreach ($salaryYearOptions as $yearOption)
                        <option value="{{ $yearOption }}" @selected($yearOption === $salaryYear)>{{ $yearOption }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    @if (session('status') === 'salary-updated')
        <div class="mx-4 mt-4 rounded-md bg-green-50 px-4 py-2.5 text-xs sm:text-sm text-green-700">
            {{ __('Salary adjustment saved for :period. The payslip is in sync.', ['period' => session('saved_period', '')]) }}
        </div>
    @endif

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-[11px] uppercase tracking-wider text-gray-500">
                <tr>
                    <th scope="col" class="px-4 py-2.5 text-left font-semibold">{{ __('Employee') }}</th>
                    <th scope="col" class="px-3 py-2.5 text-right font-semibold">{{ __('Rate (RM / hour)') }}</th>
                    <th scope="col" class="px-3 py-2.5 text-right font-semibold">{{ __('Total hours') }}</th>
                    <th scope="col" class="px-3 py-2.5 text-right font-semibold">{{ __('Total days') }}</th>
                    <th scope="col" class="px-3 py-2.5 text-right font-semibold">{{ __('Basic (RM)') }}</th>
                    <th scope="col" class="px-4 py-2.5 text-right font-semibold">{{ __('Payslip') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($salaryStaff as $member)
                    @php
                        $adjustment = $member->salaryAdjustments->first();
                        $rate = (float) ($adjustment?->hourly_rate ?? $member->hourly_rate ?? 0);
                        $hours = (float) ($adjustment?->hours_of_work ?? $member->hours_of_work ?? 0);
                        $days = (int) ($adjustment?->days_of_work ?? $member->days_of_work ?? 0);
                        $formId = 'salary-row-'.$member->id;
                    @endphp
                    <tr x-data="{
                            rate: {{ $rate }},
                            hours: {{ $hours }},
                            days: {{ $days }},
                            get total() { return Number(this.rate || 0) * Number(this.hours || 0) * Number(this.days || 0); },
                            money(v) { return Number(v || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
                        }">
                        <td class="px-4 py-2.5">
                            <form id="{{ $formId }}" method="POST" action="{{ route('admin.staff.salary', $member) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="year" value="{{ $salaryYear }}">
                                <input type="hidden" name="month" value="{{ $salaryMonth }}">
                                <input type="hidden" name="context" value="dashboard">
                                <input type="hidden" name="return_year" value="{{ $leaveYear }}">
                            </form>
                            <div class="font-medium text-gray-900">{{ $member->name }}</div>
                            <div class="text-xs text-gray-500">{{ $member->employee_id ?: __('No staff ID') }}</div>
                            @if (! $adjustment)
                                <div class="mt-0.5 text-[11px] text-amber-600">{{ __('Not saved for this period — prefilled from staff defaults.') }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-right whitespace-nowrap">
                            <input form="{{ $formId }}" type="number" name="hourly_rate" step="0.01" min="0" max="999999.99" required
                                   value="{{ $rate }}" x-model.number="rate"
                                   aria-label="{{ __('Rate per hour for :name', ['name' => $member->name]) }}"
                                   class="w-24 rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-[var(--brand)] focus:ring-[var(--brand)]" />
                        </td>
                        <td class="px-3 py-2.5 text-right whitespace-nowrap">
                            <input form="{{ $formId }}" type="number" name="hours_of_work" step="0.5" min="0" max="9999.99" required
                                   value="{{ $hours }}" x-model.number="hours"
                                   aria-label="{{ __('Total hours for :name', ['name' => $member->name]) }}"
                                   class="w-20 rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-[var(--brand)] focus:ring-[var(--brand)]" />
                        </td>
                        <td class="px-3 py-2.5 text-right whitespace-nowrap">
                            <input form="{{ $formId }}" type="number" name="days_of_work" step="1" min="0" max="366" required
                                   value="{{ $days }}" x-model.number="days"
                                   aria-label="{{ __('Total days for :name', ['name' => $member->name]) }}"
                                   class="w-16 rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-[var(--brand)] focus:ring-[var(--brand)]" />
                        </td>
                        <td class="px-3 py-2.5 text-right whitespace-nowrap">
                            <span class="font-semibold text-gray-900">RM <span x-text="money(total)">{{ number_format($rate * $hours * $days, 2) }}</span></span>
                        </td>
                        <td class="px-4 py-2.5 text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-2">
                                <button form="{{ $formId }}" type="submit"
                                        class="rounded-md bg-[var(--brand)] px-2.5 py-1.5 text-xs font-semibold text-white shadow-xs hover:opacity-90 transition">
                                    {{ __('Save') }}
                                </button>
                                @if ($adjustment)
                                    <a href="{{ route('admin.staff.payslip.pdf', ['staff' => $member, 'year' => $salaryYear, 'month' => $salaryMonth]) }}"
                                       target="_blank" rel="noopener"
                                       class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-700 shadow-xs hover:bg-gray-50 transition">
                                        {{ __('Payslip PDF') }}
                                    </a>
                                @else
                                    <span class="text-[11px] text-gray-400">{{ __('Save first') }}</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">{{ __('No staff members yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($salaryStaff->isNotEmpty())
                <tfoot class="bg-gray-50">
                    <tr>
                        <td colspan="4" class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            {{ __('Total payroll') }} — {{ $periodLabel }}
                        </td>
                        <td class="px-3 py-2.5 text-right font-semibold text-gray-900">RM {{ number_format($salaryTotal, 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
