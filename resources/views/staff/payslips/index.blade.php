<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-lg sm:text-xl text-gray-800 leading-tight">
            {{ __('My Payslips') }}
        </h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if (session('status') === 'payslip-removed')
                <div class="rounded-md bg-green-50 px-3 py-2.5 text-xs sm:text-sm text-green-700">
                    {{ __('Payslip removed.') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 border-b border-gray-100">
                    <h3 class="font-medium text-gray-900">{{ __('Payslip history') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ __('Payslips are prepared by HR. Basic pay = rate per hour × total hours × total days.') }}
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr class="whitespace-nowrap">
                                <th class="px-4 py-3 text-left">{{ __('Period') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Basic') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Allowances') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Deductions') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Net pay') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Payslip') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($payslips as $payslip)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="font-medium text-gray-900">{{ $payslip->periodLabel() }}</div>
                                        <div class="text-xs text-gray-500">
                                            {{ __('RM :rate/hr × :hours hrs × :days days', [
                                                'rate' => number_format((float) $payslip->hourly_rate, 2),
                                                'hours' => (float) $payslip->hours_of_work,
                                                'days' => (int) $payslip->days_of_work,
                                            ]) }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap text-gray-900">{{ number_format((float) $payslip->basic, 2) }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap text-gray-900">{{ number_format((float) $payslip->allowances, 2) }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap text-gray-900">{{ number_format((float) $payslip->other_deductions, 2) }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap font-semibold text-gray-900">RM {{ number_format((float) $payslip->net, 2) }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <div class="inline-flex items-center gap-2">
                                            <a href="{{ route('staff.payslips.pdf', $payslip) }}"
                                               class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-xs hover:bg-gray-50 transition">
                                                {{ __('Download PDF') }}
                                            </a>

                                            <form method="POST" action="{{ route('staff.payslips.destroy', $payslip) }}"
                                                  onsubmit="return confirm('Remove this payslip?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" title="{{ __('Remove') }}" aria-label="{{ __('Remove payslip') }}"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-gray-200 bg-white text-gray-400 shadow-xs hover:border-red-200 hover:bg-red-50 hover:text-red-600 transition">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">
                                        {{ __('No payslips yet — they will appear here once HR prepares them.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
