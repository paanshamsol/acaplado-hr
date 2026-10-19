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

                    <nav class="flex-1 px-2 py-3 space-y-0.5">
                        <x-admin-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                            {{ __('Dashboard') }}
                        </x-admin-nav-link>

                        @can('view-attendance')
                            <x-admin-nav-link :href="route('admin.attendance.index')" :active="request()->routeIs('admin.attendance.*')">
                                {{ __('Attendance') }}
                            </x-admin-nav-link>
                        @endcan

                        @can('manage-staff')
                            <x-admin-nav-link :href="route('admin.staff.index')" :active="request()->routeIs('admin.staff.*')">
                                {{ __('Staff') }}
                            </x-admin-nav-link>

                            <x-admin-nav-link :href="route('admin.dashboard').'#salary-payslips'">
                                {{ __('Payslips') }}
                            </x-admin-nav-link>
                        @endcan

                        @can('view-leaves')
                            <x-admin-nav-link :href="route('admin.leaves.index')" :active="request()->routeIs('admin.leaves.*')">
                                {{ __('Leave') }}
                            </x-admin-nav-link>
                        @endcan

                        @can('approve-leaves')
                            <x-admin-nav-link :href="route('admin.leave-requests.index')" :active="request()->routeIs('admin.leave-requests.*')">
                                {{ __('Leave Requests') }}
                            </x-admin-nav-link>
                        @endcan

                        @can('manage-company')
                            <x-admin-nav-link :href="route('admin.company.edit')" :active="request()->routeIs('admin.company.*')">
                                {{ __('Company') }}
                            </x-admin-nav-link>
                        @endcan
                    </nav>

                    <div class="border-t border-gray-100 px-2 py-3">
                        <div class="px-3 pb-1.5 text-xs text-gray-500 truncate">{{ auth()->user()->name }}</div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                class="flex w-full items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition duration-150 ease-in-out">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                                </svg>
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
