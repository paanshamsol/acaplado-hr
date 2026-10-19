<nav x-data="{ open: false }" class="sticky top-0 z-40 bg-[#33BD08] border-b border-[#268E06]">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-14">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-company-logo class="block h-8 w-auto max-w-[7rem] fill-current text-white" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-6 sm:-my-px sm:ms-8 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>

                    @if (auth()->user()->hasAnyRole(['admin', 'hr']))
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

                    @if (auth()->user()->hasRole('staff'))
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

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-transparent hover:text-white/80 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                            <div class="px-4 py-2 text-xs text-gray-500">
                                {{ auth()->user()->getRoleNames()->map(fn ($role) => ucfirst($role))->join(', ') }}
                            </div>

                            <x-dropdown-link :href="route('notifications.index')">
                                {{ __('Notifications') }}
                                @if (auth()->user()->unreadNotifications()->count() > 0)
                                    <span class="ml-1 inline-flex items-center justify-center rounded-full bg-red-500 px-1.5 py-0.5 text-[10px] font-bold text-white">
                                        {{ auth()->user()->unreadNotifications()->count() }}
                                    </span>
                                @endif
                            </x-dropdown-link>

                        @can('manage-company')
                            <x-nav-link :href="route('admin.company.edit')" :active="request()->routeIs('admin.company.*')">
                                {{ __('Company') }}
                            </x-nav-link>

                            <x-nav-link :href="route('admin.audit-logs.index')" :active="request()->routeIs('admin.audit-logs.*')">
                                {{ __('Audit Logs') }}
                            </x-nav-link>
                        @endcan

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-white hover:text-white/80 hover:bg-white/10 focus:outline-none focus:bg-white/10 focus:text-white transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>

            @if (auth()->user()->hasAnyRole(['admin', 'hr']))
                <x-responsive-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                    {{ __('Admin') }}
                </x-responsive-nav-link>
            @endif

            @can('view-attendance')
                <x-responsive-nav-link :href="route('admin.attendance.index')" :active="request()->routeIs('admin.attendance.*')">
                    {{ __('Attendance') }}
                </x-responsive-nav-link>
            @endcan

            @can('view-leaves')
                <x-responsive-nav-link :href="route('admin.leaves.index')" :active="request()->routeIs('admin.leaves.*')">
                    {{ __('Leave') }}
                </x-responsive-nav-link>
            @endcan

            @can('approve-leaves')
                <x-responsive-nav-link :href="route('admin.leave-requests.index')" :active="request()->routeIs('admin.leave-requests.*')">
                    {{ __('Leave Requests') }}
                </x-responsive-nav-link>
            @endcan

            @if (auth()->user()->hasRole('staff'))
                <x-responsive-nav-link :href="route('staff.leaves.index')" :active="request()->routeIs('staff.leaves.*')">
                    {{ __('My Leave') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('staff.payslips.index')" :active="request()->routeIs('staff.payslips.*')">
                    {{ __('My Payslips') }}
                </x-responsive-nav-link>
            @endif

            @can('manage-staff')
                <x-responsive-nav-link :href="route('admin.staff.index')" :active="request()->routeIs('admin.staff.*')">
                    {{ __('Staff') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('admin.dashboard').'#salary-payslips'">
                    {{ __('Payslips') }}
                </x-responsive-nav-link>
            @endcan
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-white/20">
            <div class="px-4">
                <div class="font-medium text-base text-white">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-white/70">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                        @can('manage-company')
                            <x-responsive-nav-link :href="route('admin.company.edit')" :active="request()->routeIs('admin.company.*')">
                                {{ __('Company') }}
                            </x-responsive-nav-link>

                            <x-responsive-nav-link :href="route('admin.audit-logs.index')" :active="request()->routeIs('admin.audit-logs.*')">
                                {{ __('Audit Logs') }}
                            </x-responsive-nav-link>
                        @endcan

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
