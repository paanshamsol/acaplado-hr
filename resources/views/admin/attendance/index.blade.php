<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-lg sm:text-xl text-gray-800 leading-tight">
                {{ __('Attendance') }}
            </h2>
            <span class="inline-flex items-center gap-2 text-xs text-gray-500">
                <span class="inline-block h-2 w-2 rounded-full bg-green-500" :class="loading ? 'animate-pulse' : ''"></span>
                <span x-text="loading ? 'Updating…' : 'Live • ' + generatedAt"></span>
            </span>
            <a href="{{ route('admin.attendance.export', ['year' => now()->year, 'month' => now()->month]) }}"
               class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 shadow-xs hover:bg-gray-50 transition">
                {{ __('Export CSV') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12" x-data='attendanceBoard({ dataUrl: "{{ route('admin.attendance.data') }}", today: "{{ now()->toDateString() }}", week: "{{ now()->format('o-\WW') }}", month: "{{ now()->format('Y-m') }}", staffList: {{ $staff->map(fn ($member) => ['id' => $member->id, 'name' => $member->name, 'employee_id' => $member->employee_id])->values()->toJson() }} })' x-init="init()">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <x-input-label :value="__('View')" />
                    <div class="mt-1 inline-flex rounded-md shadow-sm">
                        <button type="button" @click="mode = 'day'"
                            class="rounded-l-md border px-4 py-2 text-sm"
                            :class="mode === 'day' ? 'bg-[var(--brand)] text-white border-[var(--brand)]' : 'bg-white text-gray-700 border-gray-300'">
                            {{ __('Day') }}
                        </button>
                        <button type="button" @click="mode = 'week'"
                            class="-ml-px border px-4 py-2 text-sm"
                            :class="mode === 'week' ? 'bg-[var(--brand)] text-white border-[var(--brand)]' : 'bg-white text-gray-700 border-gray-300'">
                            {{ __('Week') }}
                        </button>
                        <button type="button" @click="mode = 'month'"
                            class="-ml-px rounded-r-md border px-4 py-2 text-sm"
                            :class="mode === 'month' ? 'bg-[var(--brand)] text-white border-[var(--brand)]' : 'bg-white text-gray-700 border-gray-300'">
                            {{ __('Month') }}
                        </button>
                    </div>
                </div>

                <div x-show="mode === 'day'">
                    <x-input-label for="attendance_date" :value="__('Date')" />
                    <x-text-input id="attendance_date" type="date" class="mt-1" x-model="date" />
                </div>

                <div x-show="mode === 'week'">
                    <x-input-label for="attendance_week" :value="__('Week')" />
                    <x-text-input id="attendance_week" type="week" class="mt-1" x-model="week" />
                </div>

                <div x-show="mode === 'month'">
                    <x-input-label for="attendance_month" :value="__('Month')" />
                    <x-text-input id="attendance_month" type="month" class="mt-1" x-model="month" />
                </div>

                <div>
                    <x-input-label for="attendance_staff" :value="__('Staff')" />
                    <select id="attendance_staff" x-model="staff"
                        class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">{{ __('All staff') }}</option>
                        <template x-for="member in staffList" :key="member.id">
                            <option :value="member.id" x-text="member.name + (member.employee_id ? ' (' + member.employee_id + ')' : '')"></option>
                        </template>
                    </select>
                </div>

                <div class="text-sm text-gray-500" x-text="label"></div>

                <div class="self-end">
                    <x-primary-button type="button" x-on:click="fetchData()" x-bind:disabled="loading">
                        {{ __('Filter') }}
                    </x-primary-button>
                </div>
            </div>

            <template x-if="mode === 'day'">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-white shadow-sm sm:rounded-lg p-4">
                        <div class="text-xs text-gray-500">{{ __('Total Staff') }}</div>
                        <div class="text-2xl font-semibold" x-text="stats.total ?? 0"></div>
                    </div>
                    <div class="bg-white shadow-sm sm:rounded-lg p-4">
                        <div class="text-xs text-gray-500">{{ __('Present') }}</div>
                        <div class="text-2xl font-semibold text-blue-600" x-text="stats.present ?? 0"></div>
                    </div>
                    <div class="bg-white shadow-sm sm:rounded-lg p-4">
                        <div class="text-xs text-gray-500">{{ __('Clocked Out') }}</div>
                        <div class="text-2xl font-semibold text-green-600" x-text="stats.completed ?? 0"></div>
                    </div>
                    <div class="bg-white shadow-sm sm:rounded-lg p-4">
                        <div class="text-xs text-gray-500">{{ __('Still In') }}</div>
                        <div class="text-2xl font-semibold text-amber-600" x-text="stats.open ?? 0"></div>
                    </div>
                </div>
            </template>

            <template x-if="mode !== 'day'">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-white shadow-sm sm:rounded-lg p-4">
                        <div class="text-xs text-gray-500">{{ __('Records') }}</div>
                        <div class="text-2xl font-semibold" x-text="stats.records ?? 0"></div>
                    </div>
                    <div class="bg-white shadow-sm sm:rounded-lg p-4">
                        <div class="text-xs text-gray-500">{{ __('Clocked Out') }}</div>
                        <div class="text-2xl font-semibold text-green-600" x-text="stats.completed ?? 0"></div>
                    </div>
                    <div class="bg-white shadow-sm sm:rounded-lg p-4">
                        <div class="text-xs text-gray-500">{{ __('Still In') }}</div>
                        <div class="text-2xl font-semibold text-amber-600" x-text="stats.open ?? 0"></div>
                    </div>
                </div>
            </template>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr class="whitespace-nowrap">
                                <template x-if="mode !== 'day'">
                                    <th class="px-4 py-3 text-left">{{ __('Date') }}</th>
                                </template>
                                <th class="px-4 py-3 text-left">{{ __('Staff') }}</th>
                                <th class="px-4 py-3 text-left hidden md:table-cell">{{ __('Staff ID') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Clock In') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Clock Out') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Worked') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="(row, index) in rows" :key="index">
                                <tr>
                                    <template x-if="mode !== 'day'">
                                        <td class="px-4 py-3 text-gray-700 whitespace-nowrap">
                                            <span x-text="row.date"></span>
                                            <span class="ml-1 text-xs text-gray-400" x-text="row.weekday"></span>
                                        </td>
                                    </template>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <template x-if="row.photo">
                                                <img :src="row.photo" alt="" class="h-8 w-8 rounded-full object-cover">
                                            </template>
                                            <template x-if="!row.photo">
                                                <div class="h-8 w-8 rounded-full bg-gray-200"></div>
                                            </template>
                                            <span class="font-medium text-gray-900" x-text="row.name"></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 hidden md:table-cell" x-text="row.employee_id || '—'"></td>
                                    <td class="px-4 py-3 text-gray-700" x-text="row.clock_in || '—'"></td>
                                    <td class="px-4 py-3 text-gray-700" x-text="row.clock_out || '—'"></td>
                                    <td class="px-4 py-3 text-gray-700" x-text="row.worked || '—'"></td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-0.5 text-xs" :class="statusClass(row.status)" x-text="statusLabel(row.status)"></span>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="rows.length === 0">
                                <tr>
                                    <td :colspan="mode === 'day' ? 6 : 7" class="px-4 py-8 text-center text-gray-500">{{ __('No data.') }}</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
