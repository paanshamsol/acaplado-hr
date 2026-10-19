<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email or Staff ID -->
        <div>
            <x-input-label for="email" :value="__('Email or Staff ID')" />
            <x-text-input id="email" class="block mt-1 w-full" type="text" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="mt-6 space-y-3">
            <x-primary-button class="w-full justify-center py-2.5">
                {{ __('Log in') }}
            </x-primary-button>

            <div class="grid grid-cols-2 gap-3">
                <a href="{{ route('attendance.kiosk') }}"
                   class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    {{ __('Clock-in Kiosk') }}
                </a>

                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}"
                       class="inline-flex items-center justify-center rounded-md border border-transparent bg-amber-500 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">
                        {{ __('Forgot password?') }}
                    </a>
                @endif
            </div>
        </div>
    </form>
</x-guest-layout>
