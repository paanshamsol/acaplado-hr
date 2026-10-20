<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased" style="--brand: {{ \App\Models\CompanySetting::current()->brandColor() }}">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <div class="flex">
                <!-- Sidebar -->
                <aside class="hidden lg:flex lg:flex-col w-56 xl:w-60 shrink-0 bg-white border-r border-gray-200 lg:sticky lg:top-14 lg:h-[calc(100vh-3.5rem)] lg:overflow-y-auto">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <x-company-logo class="h-7 w-auto max-w-[8rem]" />
                        <span class="mt-1.5 block text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ __('Admin') }}</span>
                    </div>

                    <nav class="flex-1 px-2 py-3">
                        <div class="px-3 pb-1.5 text-[11px] font-semibold uppercase tracking-wider text-gray-400">{{ __('Overview') }}</div>

                        <x-admin-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')" icon="dashboard">
                            {{ __('Dashboard') }}
                        </x-admin-nav-link>

                        @can('view-attendance')
                            <x-admin-nav-link :href="route('admin.attendance.index')" :active="request()->routeIs('admin.attendance.*')" icon="clock">
                                {{ __('Attendance') }}
                            </x-admin-nav-link>
                        @endcan

                        <div class="px-3 pb-1.5 pt-4 text-[11px] font-semibold uppercase tracking-wider text-gray-400">{{ __('Workforce') }}</div>

                        @can('manage-staff')
                            <x-admin-nav-link :href="route('admin.staff.index')" :active="request()->routeIs('admin.staff.*')" icon="users">
                                {{ __('Staff') }}
                            </x-admin-nav-link>

                            <x-admin-nav-link :href="route('admin.dashboard').'#salary-payslips'" icon="banknote">
                                {{ __('Payslips') }}
                            </x-admin-nav-link>
                        @endcan

                        <div class="px-3 pb-1.5 pt-4 text-[11px] font-semibold uppercase tracking-wider text-gray-400">{{ __('Time Off') }}</div>

                        @can('view-leaves')
                            <x-admin-nav-link :href="route('admin.leaves.index')" :active="request()->routeIs('admin.leaves.*')" icon="calendar">
                                {{ __('Leave') }}
                            </x-admin-nav-link>
                        @endcan

                        @can('approve-leaves')
                            <x-admin-nav-link :href="route('admin.leave-requests.index')" :active="request()->routeIs('admin.leave-requests.*')" icon="clipboard-check">
                                {{ __('Leave Requests') }}
                            </x-admin-nav-link>
                        @endcan

                        @can('manage-company')
                            <div class="px-3 pb-1.5 pt-4 text-[11px] font-semibold uppercase tracking-wider text-gray-400">{{ __('Company') }}</div>

                            <x-admin-nav-link :href="route('admin.company.edit')" :active="request()->routeIs('admin.company.*')" icon="building">
                                {{ __('Company Settings') }}
                            </x-admin-nav-link>
                        @endcan
                    </nav>

                    <div class="border-t border-gray-100 px-3 py-3">
                        @php
                            $sidebarInitials = collect(preg_split('/\s+/', trim(auth()->user()->name)))
                                ->filter()
                                ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
                                ->take(2)
                                ->implode('');
                        @endphp

                        <div class="flex items-center gap-2.5 px-1">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[var(--brand)] text-xs font-bold text-white">
                                {{ $sidebarInitials }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-medium text-gray-900">{{ auth()->user()->name }}</div>
                                <div class="truncate text-[11px] text-gray-500">{{ auth()->user()->getRoleNames()->map(fn ($role) => ucfirst($role))->join(', ') }}</div>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('logout') }}" class="mt-2">
                            @csrf
                            <button type="submit"
                                class="flex w-full items-center gap-3 rounded-md px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-red-50 hover:text-red-600 transition duration-150 ease-in-out">
                                <x-icon name="logout" class="h-5 w-5 shrink-0" />
                                {{ __('Log Out') }}
                            </button>
                        </form>
                    </div>
                </aside>

                <!-- Main content -->
                <div class="flex-1 min-w-0">
                    @isset($header)
                        <header class="bg-white shadow">
                            <div class="max-w-7xl mx-auto py-3.5 px-4 sm:px-6 lg:px-8">
                                {{ $header }}
                            </div>
                        </header>
                    @endisset

                    <main>
                        {{ $slot }}
                    </main>
                </div>
            </div>
        </div>
    </body>
</html>
