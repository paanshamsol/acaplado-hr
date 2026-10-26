<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-lg sm:text-xl text-gray-800 leading-tight">
                    {{ __('Attendance') }}
                </h2>
                <p class="mt-0.5 text-xs text-gray-500">{{ __('Realtime daily, weekly and monthly clock-in/out records.') }}</p>
            </div>
            <a href="{{ route('admin.attendance.export', ['year' => now()->year, 'month' => now()->month]) }}"
               class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 shadow-xs hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[var(--brand)] focus:ring-offset-1 transition">
                <x-icon name="download" class="h-4 w-4" />
                {{ __('Export CSV') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6" x-data='attendanceBoard({ dataUrl: "{{ route('admin.attendance.data') }}", today: "{{ now()->toDateString() }}", week: "{{ now()->format('o-\WW') }}", month: "{{ now()->format('Y-m') }}", storeUrl: "{{ route('admin.attendance.store') }}", updateUrl: "{{ route('admin.attendance.update', ['attendance' => '__ID__']) }}", deleteUrl: "{{ route('admin.attendance.destroy', ['attendance' => '__ID__']) }}", canManage: @json(auth()->user()->can('manage-attendance')), staffList: {{ $staff->map(fn ($member) => ['id' => $member->id, 'name' => $member->name, 'employee_id' => $member->employee_id])->values()->toJson() }} })' x-init="init()">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            <!-- Filters -->
            <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                <div class="flex flex-wrap items-end gap-3 p-4 sm:p-5">
                    <div>
                        <x-input-label :value="__('View')" />
                        <div class="mt-1 inline-flex rounded-lg bg-gray-100 p-1 ring-1 ring-inset ring-gray-200">
                            <button type="button" @click="mode = 'day'"
                                class="rounded-md px-4 py-1.5 text-sm transition"
                                :class="mode === 'day' ? 'bg-white font-semibold text-[var(--brand)] shadow-sm ring-1 ring-black/5' : 'font-medium text-gray-600 hover:text-gray-900'">
                                {{ __('Day') }}
                            </button>
                            <button type="button" @click="mode = 'week'"
                                class="-ms-px rounded-md px-4 py-1.5 text-sm transition"
                                :class="mode === 'week' ? 'bg-white font-semibold text-[var(--brand)] shadow-sm ring-1 ring-black/5' : 'font-medium text-gray-600 hover:text-gray-900'">
                                {{ __('Week') }}
                            </button>
                            <button type="button" @click="mode = 'month'"
                                class="-ms-px rounded-md px-4 py-1.5 text-sm transition"
                                :class="mode === 'month' ? 'bg-white font-semibold text-[var(--brand)] shadow-sm ring-1 ring-black/5' : 'font-medium text-gray-600 hover:text-gray-900'">
                                {{ __('Month') }}
                            </button>
                        </div>
                    </div>

                    <div x-show="mode === 'day'" x-cloak>
                        <x-input-label for="attendance_date" :value="__('Date')" />
                        <x-text-input id="attendance_date" type="date" class="mt-1 block w-full sm:w-44" x-model="date" />
                    </div>

                    <div x-show="mode === 'week'" x-cloak>
                        <x-input-label for="attendance_week" :value="__('Week')" />
                        <x-text-input id="attendance_week" type="week" class="mt-1 block w-full sm:w-44" x-model="week" />
                    </div>

                    <div x-show="mode === 'month'" x-cloak>
                        <x-input-label for="attendance_month" :value="__('Month')" />
                        <x-text-input id="attendance_month" type="month" class="mt-1 block w-full sm:w-44" x-model="month" />
                    </div>

                    <div class="grow sm:grow-0 sm:w-56">
                        <x-input-label for="attendance_staff" :value="__('Staff')" />
                        <select id="attendance_staff" x-model="staff"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('All staff') }}</option>
                            <template x-for="member in staffList" :key="member.id">
                                <option :value="member.id" x-text="member.name + (member.employee_id ? ' (' + member.employee_id + ')' : '')"></option>
                            </template>
                        </select>
                    </div>

                    <div class="grow sm:grow-0 sm:w-64">
                        <x-input-label for="attendance_search" :value="__('Search')" />
                        <div class="relative mt-1">
                            <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-gray-400">
                                <x-icon name="search" class="h-4 w-4" />
                            </span>
                            <input id="attendance_search" type="search" x-model="search"
                                placeholder="{{ __('Name or staff ID…') }}"
                                class="block w-full rounded-md border-gray-300 ps-9 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <x-primary-button type="button" class="justify-center gap-1.5" x-on:click="fetchData()" x-bind:disabled="loading">
                            <x-icon name="refresh" class="h-4 w-4" x-show="!loading" />
                            <svg x-show="loading" x-cloak class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            {{ __('Apply') }}
                        </x-primary-button>
                        <x-secondary-button type="button" class="justify-center" x-on:click="resetFilters()">
                            {{ __('Reset') }}
                        </x-secondary-button>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 border-t border-gray-100 bg-gray-50/70 px-4 py-2.5 sm:px-5">
                    <span class="text-sm font-medium text-gray-700" x-text="label"></span>
                    <span class="inline-flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="inline-block h-2 w-2 rounded-full" :class="loading ? 'animate-pulse bg-amber-500' : 'bg-green-500'"></span>
                            <span x-text="loading ? '{{ __('Updating…') }}' : '{{ __('Live • ') }}' + generatedAt"></span>
                        </span>
                        <span>
                            <span class="font-semibold text-gray-700" x-text="filteredRows.length"></span> {{ __('record(s)') }}
                        </span>
                    </span>
                </div>
            </div>

            <!-- Summary cards -->
            <template x-if="mode === 'day'">
                <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                    <div class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ __('Total Staff') }}</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                <x-icon name="users" class="h-4 w-4" />
                            </span>
                        </div>
                        <div class="mt-2 text-2xl font-semibold text-gray-900" x-text="stats.total ?? 0"></div>
                        <div class="mt-0.5 text-[11px] text-gray-400">{{ __('Scheduled for this day') }}</div>
                    </div>

                    <div class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ __('Present') }}</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                                <x-icon name="chart-bar" class="h-4 w-4" />
                            </span>
                        </div>
                        <div class="mt-2 text-2xl font-semibold text-blue-600" x-text="stats.present ?? 0"></div>
                        <div class="mt-0.5 text-[11px] text-gray-400">{{ __('Clocked in today') }}</div>
                    </div>

                    <div class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ __('Clocked Out') }}</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-50 text-green-600">
                                <x-icon name="check-circle" class="h-4 w-4" />
                            </span>
                        </div>
                        <div class="mt-2 text-2xl font-semibold text-green-600" x-text="stats.completed ?? 0"></div>
                        <div class="mt-0.5 text-[11px] text-gray-400">{{ __('Finished their shift') }}</div>
                    </div>

                    <div class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ __('Still In') }}</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                                <x-icon name="clock" class="h-4 w-4" />
                            </span>
                        </div>
                        <div class="mt-2 text-2xl font-semibold text-amber-600" x-text="stats.open ?? 0"></div>
                        <div class="mt-0.5 text-[11px] text-gray-400">{{ __('Clocked in, not out') }}</div>
                    </div>
                </div>
            </template>

            <template x-if="mode !== 'day'">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4">
                    <div class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ __('Records') }}</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                <x-icon name="document-text" class="h-4 w-4" />
                            </span>
                        </div>
                        <div class="mt-2 text-2xl font-semibold text-gray-900" x-text="stats.records ?? 0"></div>
                        <div class="mt-0.5 text-[11px] text-gray-400">{{ __('Rows in this period') }}</div>
                    </div>

                    <div class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ __('Clocked Out') }}</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-50 text-green-600">
                                <x-icon name="check-circle" class="h-4 w-4" />
                            </span>
                        </div>
                        <div class="mt-2 text-2xl font-semibold text-green-600" x-text="stats.completed ?? 0"></div>
                        <div class="mt-0.5 text-[11px] text-gray-400">{{ __('Finished their shift') }}</div>
                    </div>

                    <div class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ __('Still In') }}</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                                <x-icon name="clock" class="h-4 w-4" />
                            </span>
                        </div>
                        <div class="mt-2 text-2xl font-semibold text-amber-600" x-text="stats.open ?? 0"></div>
                        <div class="mt-0.5 text-[11px] text-gray-400">{{ __('Clocked in, not out') }}</div>
                    </div>
                </div>
            </template>

            <!-- Records -->
            <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm"
                 :class="loading ? 'opacity-70' : ''">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-4 py-3 sm:px-5">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">{{ __('Records') }}</h3>
                        <p class="mt-0.5 text-xs text-gray-500">
                            <span x-text="filteredRows.length"></span> {{ __('record(s) shown') }}
                            <span x-show="hasSearch">{{ __('— filtered by search') }}</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span x-show="loading" x-cloak class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-500">
                            <svg class="h-3.5 w-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            {{ __('Refreshing…') }}
                        </span>
                        <template x-if="canManage">
                            <button type="button" @click="openCreate()"
                                class="inline-flex items-center gap-1.5 rounded-md bg-[var(--brand)] px-3 py-1.5 text-xs font-semibold text-white shadow-xs hover:opacity-90 transition">
                                <x-icon name="plus" class="h-3.5 w-3.5" />
                                {{ __('Add record') }}
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Desktop table -->
                <div class="hidden md:block">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-[11px] font-semibold uppercase tracking-wider text-gray-500">
                                <tr class="whitespace-nowrap">
                                    <template x-if="mode !== 'day'">
                                        <th class="px-4 py-3 text-left">{{ __('Date') }}</th>
                                    </template>
                                    <th class="px-4 py-3 text-left">{{ __('Staff') }}</th>
                                    <th class="px-4 py-3 text-left hidden lg:table-cell">{{ __('Staff ID') }}</th>
                                    <th class="px-4 py-3 text-left">{{ __('Clock In') }}</th>
                                    <th class="px-4 py-3 text-left">{{ __('Clock Out') }}</th>
                                    <th class="px-4 py-3 text-left">{{ __('Worked') }}</th>
                                    <th class="px-4 py-3 text-left">{{ __('Status') }}</th>
                                    <template x-if="canManage">
                                        <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="(row, index) in filteredRows" :key="index">
                                    <tr class="hover:bg-gray-50/70">
                                        <template x-if="mode !== 'day'">
                                            <td class="px-4 py-3 text-gray-700 whitespace-nowrap">
                                                <div class="font-medium text-gray-900" x-text="row.date"></div>
                                                <div class="text-xs text-gray-400" x-text="row.weekday"></div>
                                            </td>
                                        </template>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-3">
                                                <template x-if="row.photo">
                                                    <img :src="row.photo" alt="" class="h-8 w-8 rounded-full object-cover">
                                                </template>
                                                <template x-if="!row.photo">
                                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-[11px] font-semibold text-gray-500"
                                                          x-text="(row.name || '?').slice(0, 2).toUpperCase()"></span>
                                                </template>
                                                <span class="font-medium text-gray-900" x-text="row.name"></span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 hidden lg:table-cell" x-text="row.employee_id || '—'"></td>
                                        <td class="px-4 py-3 text-gray-700" x-text="row.clock_in || '—'"></td>
                                        <td class="px-4 py-3 text-gray-700" x-text="row.clock_out || '—'"></td>
                                        <td class="px-4 py-3 text-gray-700" x-text="row.worked || '—'"></td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(row.status)">
                                                <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>
                                                <span x-text="statusLabel(row.status)"></span>
                                            </span>
                                        </td>
                                        <template x-if="canManage">
                                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                                <div class="inline-flex items-center gap-1.5">
                                                    <template x-if="row.id != null">
                                                        <button type="button" @click="openEdit(row)"
                                                            class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 shadow-xs hover:bg-gray-50">
                                                            <x-icon name="pencil" class="h-3.5 w-3.5" />
                                                            {{ __('Edit') }}
                                                        </button>
                                                    </template>
                                                    <template x-if="row.id == null">
                                                        <button type="button" @click="openCreate(row.work_date, row.user_id)"
                                                            class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 shadow-xs hover:bg-gray-50">
                                                            <x-icon name="plus" class="h-3.5 w-3.5" />
                                                            {{ __('Add') }}
                                                        </button>
                                                    </template>
                                                    <template x-if="row.id != null">
                                                        <button type="button" @click="deleteRow(row)"
                                                            class="inline-flex items-center gap-1 rounded-md border border-red-200 bg-white px-2 py-1 text-xs font-medium text-red-600 shadow-xs hover:bg-red-50">
                                                            <x-icon name="trash" class="h-3.5 w-3.5" />
                                                            {{ __('Delete') }}
                                                        </button>
                                                    </template>
                                                </div>
                                            </td>
                                        </template>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Mobile cards -->
                <div class="md:hidden divide-y divide-gray-100">
                    <template x-for="(row, index) in filteredRows" :key="'m-' + index">
                        <div class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <template x-if="row.photo">
                                        <img :src="row.photo" alt="" class="h-9 w-9 rounded-full object-cover">
                                    </template>
                                    <template x-if="!row.photo">
                                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-[11px] font-semibold text-gray-500"
                                              x-text="(row.name || '?').slice(0, 2).toUpperCase()"></span>
                                    </template>
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-semibold text-gray-900" x-text="row.name"></div>
                                        <div class="truncate text-xs text-gray-500">
                                            <span x-text="row.employee_id || ''"></span>
                                            <template x-if="row.date">
                                                <span>• <span x-text="row.date"></span></span>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                                <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(row.status)" x-text="statusLabel(row.status)"></span>
                            </div>

                            <dl class="mt-3 grid grid-cols-3 gap-2 rounded-lg bg-gray-50 px-3 py-2">
                                <div>
                                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">{{ __('In') }}</dt>
                                    <dd class="mt-0.5 text-xs font-medium text-gray-700" x-text="row.clock_in || '—'"></dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">{{ __('Out') }}</dt>
                                    <dd class="mt-0.5 text-xs font-medium text-gray-700" x-text="row.clock_out || '—'"></dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">{{ __('Worked') }}</dt>
                                    <dd class="mt-0.5 text-xs font-medium text-gray-700" x-text="row.worked || '—'"></dd>
                                </div>
                            </dl>

                            <template x-if="canManage">
                                <div class="mt-3 flex items-center justify-end gap-2">
                                    <template x-if="row.id != null">
                                        <button type="button" @click="openEdit(row)"
                                            class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-700 shadow-xs hover:bg-gray-50">
                                            <x-icon name="pencil" class="h-3.5 w-3.5" />
                                            {{ __('Edit') }}
                                        </button>
                                    </template>
                                    <template x-if="row.id == null">
                                        <button type="button" @click="openCreate(row.work_date, row.user_id)"
                                            class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-700 shadow-xs hover:bg-gray-50">
                                            <x-icon name="plus" class="h-3.5 w-3.5" />
                                            {{ __('Add') }}
                                        </button>
                                    </template>
                                    <template x-if="row.id != null">
                                        <button type="button" @click="deleteRow(row)"
                                            class="inline-flex items-center gap-1 rounded-md border border-red-200 bg-white px-2.5 py-1 text-xs font-medium text-red-600 shadow-xs hover:bg-red-50">
                                            <x-icon name="trash" class="h-3.5 w-3.5" />
                                            {{ __('Delete') }}
                                        </button>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <!-- Empty states -->
                <template x-if="filteredRows.length === 0 && rows.length === 0">
                    <div class="px-4 py-10 text-center">
                        <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                            <x-icon name="inbox" class="h-6 w-6" />
                        </span>
                        <p class="mt-3 text-sm font-medium text-gray-900">{{ __('No attendance records for this selection.') }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ __('Try another date, week or month.') }}</p>
                    </div>
                </template>

                <template x-if="filteredRows.length === 0 && rows.length > 0">
                    <div class="px-4 py-10 text-center">
                        <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                            <x-icon name="search" class="h-6 w-6" />
                        </span>
                        <p class="mt-3 text-sm font-medium text-gray-900">{{ __('No staff matches your search.') }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ __('Clear the search box to see all records again.') }}</p>
                        <button type="button" @click="search = ''"
                            class="mt-3 inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-xs hover:bg-gray-50 transition">
                            <x-icon name="close" class="h-3.5 w-3.5" />
                            {{ __('Clear search') }}
                        </button>
                    </div>
                </template>
            </div>
        </div>

        <!-- Manual attendance form -->
        <div x-show="formOpen" x-cloak x-transition.opacity @keydown.escape.window="closeForm()"
            class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0" style="display: none;">
            <div class="fixed inset-0 bg-gray-500/75" @click="closeForm()"></div>

            <div class="relative mx-auto w-full max-w-lg overflow-hidden rounded-lg bg-white shadow-xl">
                <form @submit.prevent="saveForm()" class="p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900"
                                x-text="editingId !== null ? 'Edit attendance' : 'Add attendance'"></h3>
                            <p class="mt-0.5 text-xs text-gray-500">
                                {{ __('Manual entry for staff without a face profile or anything the kiosk missed.') }}
                            </p>
                        </div>
                        <button type="button" @click="closeForm()"
                            class="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                            <x-icon name="close" class="h-4 w-4" />
                            <span class="sr-only">{{ __('Close') }}</span>
                        </button>
                    </div>

                    <div class="mt-5 space-y-4">
                        <div>
                            <x-input-label for="attendance_form_staff" :value="__('Staff')" />
                            <select id="attendance_form_staff" x-model="form.user_id" :disabled="editingId !== null" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-500">
                                <option value="">{{ __('Select staff…') }}</option>
                                <template x-for="member in staffList" :key="member.id">
                                    <option :value="member.id"
                                        x-text="member.name + (member.employee_id ? ' (' + member.employee_id + ')' : '')"></option>
                                </template>
                            </select>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label for="attendance_form_date" :value="__('Date')" />
                                <x-text-input id="attendance_form_date" type="date" class="mt-1 block w-full"
                                    x-model="form.work_date" required />
                            </div>
                            <div>
                                <x-input-label for="attendance_form_in" :value="__('Clock In')" />
                                <x-text-input id="attendance_form_in" type="time" class="mt-1 block w-full"
                                    x-model="form.clock_in_time" required />
                            </div>
                            <div>
                                <x-input-label for="attendance_form_out" :value="__('Clock Out')" />
                                <x-text-input id="attendance_form_out" type="time" class="mt-1 block w-full"
                                    x-model="form.clock_out_time" />
                            </div>
                        </div>

                        <p class="text-xs text-gray-500">
                            {{ __('Leave clock-out empty to keep the record open. A clock-out earlier than clock-in is treated as the next day, which covers overnight shifts.') }}
                        </p>

                        <p x-show="formError" x-text="formError" class="text-sm font-medium text-red-600"></p>
                    </div>

                    <div class="mt-6 flex items-center justify-between gap-3">
                        <template x-if="editingId !== null">
                            <button type="button" @click="deleteRow()" :disabled="deleting"
                                class="inline-flex items-center gap-1.5 rounded-md border border-red-200 bg-white px-3 py-2 text-xs font-semibold text-red-600 shadow-xs hover:bg-red-50 disabled:opacity-50">
                                <x-icon name="trash" class="h-4 w-4" />
                                <span x-text="deleting ? 'Deleting…' : 'Delete'"></span>
                            </button>
                        </template>

                        <div class="ms-auto flex items-center gap-2">
                            <x-secondary-button type="button" @click="closeForm()">{{ __('Cancel') }}</x-secondary-button>
                            <x-primary-button x-bind:disabled="saving">
                                <span x-text="saving ? 'Saving…' : 'Save'"></span>
                            </x-primary-button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
