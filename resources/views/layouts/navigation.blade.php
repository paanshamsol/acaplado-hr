@php
    $currentUser = auth()->user();
    $unreadNotifications = $currentUser->unreadNotifications()->count();
    $userInitials = collect(preg_split('/\s+/', trim($currentUser->name)))
        ->filter()
        ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->take(2)
        ->implode('');
    $userRoles = $currentUser->getRoleNames()->map(fn ($role) => ucfirst($role))->join(', ');
@endphp

<nav x-data="{ mobileOpen: false }"
     @keydown.escape.window="mobileOpen = false"
     class="sticky top-0 z-40 bg-[#33BD08] border-b border-[#268E06]">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex h-14 items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                <!-- Logo -->
                <a href="{{ route('dashboard') }}" class="flex shrink-0 items-center">
                    <x-company-logo class="block h-8 w-auto max-w-[7rem] fill-current text-white" />
                </a>

                <!-- Navigation Links -->
                <div class="hidden items-center gap-1 lg:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>

                    @if ($currentUser->hasAnyRole(['admin', 'hr']))
                        <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                            {{ __('Admin') }}
                        </x-nav-link>
                    @endif

                    @can('view-attendance')
                        <x-nav-link :href="route('admin.attendance.index')" :active="request()->routeIs('admin.attendance.*')">
                            {{ __('Attendance') }}
                        </x-nav-link>
                    @endcan

                    @can('view-leaves')
                        <x-nav-link :href="route('admin.leaves.index')" :active="request()->routeIs('admin.leaves.*')">
                            {{ __('Leave') }}
                        </x-nav-link>
                    @endcan

                    @can('approve-leaves')
                        <x-nav-link :href="route('admin.leave-requests.index')" :active="request()->routeIs('admin.leave-requests.*')">
                            {{ __('Leave Requests') }}
                        </x-nav-link>
                    @endcan

                    @if ($currentUser->hasRole('staff'))
                        <x-nav-link :href="route('staff.leaves.index')" :active="request()->routeIs('staff.leaves.*')">
                            {{ __('My Leave') }}
                        </x-nav-link>

                        <x-nav-link :href="route('staff.payslips.index')" :active="request()->routeIs('staff.payslips.*')">
                            {{ __('My Payslips') }}
                        </x-nav-link>
                    @endif

                    @can('manage-staff')
                        <x-nav-link :href="route('admin.staff.index')" :active="request()->routeIs('admin.staff.*')">
                            {{ __('Staff') }}
                        </x-nav-link>
                    @endcan
                </div>
            </div>

            <div class="flex items-center gap-1 sm:gap-2">
                <!-- Notifications -->
                <a href="{{ route('notifications.index') }}"
                   class="relative inline-flex h-9 w-9 items-center justify-center rounded-full text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/60 transition"
                   title="{{ __('Notifications') }}" aria-label="{{ __('Notifications') }}">
                    <x-icon name="bell" class="h-5 w-5" />
                    @if ($unreadNotifications > 0)
                        <span class="absolute -top-0.5 -end-0.5 inline-flex min-w-[1.1rem] items-center justify-center rounded-full bg-red-500 px-1 py-0.5 text-[10px] font-bold leading-none text-white ring-2 ring-[#33BD08]">
                            {{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}
                        </span>
                    @endif
                </a>

                <!-- User Dropdown -->
                <div class="hidden lg:block">
                    <x-dropdown align="right" width="w-64">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center gap-2 rounded-full py-1 pe-2 ps-1 text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/60 transition">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-white/25 text-xs font-bold text-white ring-1 ring-white/40">
                                    {{ $userInitials }}
                                </span>
                                <span class="max-w-[9rem] truncate text-sm font-medium">{{ $currentUser->name }}</span>
                                <svg class="h-4 w-4 fill-current opacity-80" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <div class="px-4 py-2 text-xs text-gray-500">
                                {{ $userRoles ?: __('No role assigned') }}
                            </div>

                            <x-dropdown-link :href="route('profile.edit')">
                                <span class="inline-flex items-center gap-2">
                                    <x-icon name="user" class="h-4 w-4 text-gray-400" />
                                    {{ __('Profile') }}
                                </span>
                            </x-dropdown-link>

                            @can('manage-company')
                                <x-dropdown-link :href="route('admin.company.edit')">
                                    <span class="inline-flex items-center gap-2">
                                        <x-icon name="building" class="h-4 w-4 text-gray-400" />
                                        {{ __('Company') }}
                                    </span>
                                </x-dropdown-link>

                                <x-dropdown-link :href="route('admin.audit-logs.index')">
                                    <span class="inline-flex items-center gap-2">
                                        <x-icon name="shield-check" class="h-4 w-4 text-gray-400" />
                                        {{ __('Audit Logs') }}
                                    </span>
                                </x-dropdown-link>
                            @endcan

                            <div class="my-1 border-t border-gray-100"></div>

                            <!-- Authentication -->
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf

                                <x-dropdown-link :href="route('logout')"
                                        onclick="event.preventDefault();
                                                    this.closest('form').submit();">
                                    <span class="inline-flex items-center gap-2 text-red-600">
                                        <x-icon name="logout" class="h-4 w-4" />
                                        {{ __('Log Out') }}
                                    </span>
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>

                <!-- Hamburger (shown below lg so tablets always have a menu) -->
                <div class="flex items-center lg:hidden">
                    <button type="button" @click="mobileOpen = true"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-full text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/60 transition"
                            aria-label="{{ __('Open menu') }}">
                        <x-icon name="menu" class="h-6 w-6" />
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Responsive drawer (mobile & tablet) -->
    <div x-show="mobileOpen" x-cloak class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="{{ __('Menu') }}">
        <!-- Backdrop -->
        <div x-show="mobileOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="absolute inset-0 bg-black/40"
             @click="mobileOpen = false"></div>

        <!-- Panel -->
        <aside x-show="mobileOpen"
               x-transition:enter="transition ease-out duration-300 transform"
               x-transition:enter-start="-translate-x-full"
               x-transition:enter-end="translate-x-0"
               x-transition:leave="transition ease-in duration-200 transform"
               x-transition:leave-start="translate-x-0"
               x-transition:leave-end="-translate-x-full"
               class="absolute inset-y-0 start-0 flex w-[min(20rem,85vw)] flex-col bg-white shadow-2xl">
            <div class="flex items-center justify-between gap-3 border-b border-[#268E06] bg-[#33BD08] px-4 py-3">
                <a href="{{ route('dashboard') }}" class="flex items-center">
                    <x-company-logo class="block h-8 w-auto max-w-[7rem] fill-current text-white" />
                </a>
                <button type="button" @click="mobileOpen = false"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/60 transition"
                        aria-label="{{ __('Close menu') }}">
                    <x-icon name="close" class="h-5 w-5" />
                </button>
            </div>

            <div class="border-b border-gray-100 bg-gray-50 px-4 py-3">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[var(--brand)] text-sm font-bold text-white">
                        {{ $userInitials }}
                    </span>
                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold text-gray-900">{{ $currentUser->name }}</div>
                        <div class="truncate text-xs text-gray-500">{{ $currentUser->email }}</div>
                    </div>
                </div>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @foreach ($currentUser->getRoleNames() as $role)
                        <span class="inline-flex items-center rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-gray-600 ring-1 ring-inset ring-gray-300">
                            {{ $role }}
                        </span>
                    @endforeach
                </div>
            </div>

            <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-4">
                <div>
                    <div class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-gray-400">{{ __('Menu') }}</div>

                    <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="home">
                        {{ __('Dashboard') }}
                    </x-responsive-nav-link>

                    @if ($currentUser->hasAnyRole(['admin', 'hr']))
                        <x-responsive-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')" icon="dashboard">
                            {{ __('Admin') }}
                        </x-responsive-nav-link>
                    @endif

                    @can('view-attendance')
                        <x-responsive-nav-link :href="route('admin.attendance.index')" :active="request()->routeIs('admin.attendance.*')" icon="clock">
                            {{ __('Attendance') }}
                        </x-responsive-nav-link>
                    @endcan

                    @can('view-leaves')
                        <x-responsive-nav-link :href="route('admin.leaves.index')" :active="request()->routeIs('admin.leaves.*')" icon="calendar">
                            {{ __('Leave') }}
                        </x-responsive-nav-link>
                    @endcan

                    @can('approve-leaves')
                        <x-responsive-nav-link :href="route('admin.leave-requests.index')" :active="request()->routeIs('admin.leave-requests.*')" icon="clipboard-check">
                            {{ __('Leave Requests') }}
                        </x-responsive-nav-link>
                    @endcan

                    @if ($currentUser->hasRole('staff'))
                        <x-responsive-nav-link :href="route('staff.leaves.index')" :active="request()->routeIs('staff.leaves.*')" icon="calendar">
                            {{ __('My Leave') }}
                        </x-responsive-nav-link>

                        <x-responsive-nav-link :href="route('staff.payslips.index')" :active="request()->routeIs('staff.payslips.*')" icon="banknote">
                            {{ __('My Payslips') }}
                        </x-responsive-nav-link>
                    @endif

                    @can('manage-staff')
                        <x-responsive-nav-link :href="route('admin.staff.index')" :active="request()->routeIs('admin.staff.*')" icon="users">
                            {{ __('Staff') }}
                        </x-responsive-nav-link>

                        <x-responsive-nav-link :href="route('admin.dashboard').'#salary-payslips'" icon="banknote">
                            {{ __('Payslips') }}
                        </x-responsive-nav-link>
                    @endcan
                </div>

                <div>
                    <div class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-gray-400">{{ __('Account') }}</div>

                    <x-responsive-nav-link :href="route('notifications.index')" :active="request()->routeIs('notifications.*')" icon="bell">
                        {{ __('Notifications') }}
                        @if ($unreadNotifications > 0)
                            <span class="ms-auto inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-red-500 px-1.5 py-0.5 text-[10px] font-bold leading-none text-white">
                                {{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}
                            </span>
                        @endif
                    </x-responsive-nav-link>

                    <x-responsive-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')" icon="user">
                        {{ __('Profile') }}
                    </x-responsive-nav-link>

                    @can('manage-company')
                        <x-responsive-nav-link :href="route('admin.company.edit')" :active="request()->routeIs('admin.company.*')" icon="building">
                            {{ __('Company') }}
                        </x-responsive-nav-link>

                        <x-responsive-nav-link :href="route('admin.audit-logs.index')" :active="request()->routeIs('admin.audit-logs.*')" icon="shield-check">
                            {{ __('Audit Logs') }}
                        </x-responsive-nav-link>
                    @endcan
                </div>
            </nav>

            <div class="border-t border-gray-100 p-3">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 transition">
                        <x-icon name="logout" class="h-5 w-5" />
                        {{ __('Log Out') }}
                    </button>
                </form>
            </div>
        </aside>
    </div>
</nav>
