<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ __('Attendance Kiosk') }} — {{ $company->name ?? config('app.name') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-900 text-gray-100 min-h-screen">
        <div
            class="min-h-screen flex flex-col items-center justify-center px-4 py-8"
            x-data="clockPunch({ punchUrl: '{{ route('attendance.punch') }}', csrf: '{{ csrf_token() }}' })"
        >
            <template x-if="cameraWarning">
                <div class="w-full max-w-lg mb-6 rounded-lg bg-amber-500/10 border border-amber-500/40 px-4 py-3 text-sm text-amber-200">
                    <div class="font-semibold">{{ __('Camera unavailable') }}</div>
                    <p class="mt-1" x-text="cameraWarning"></p>
                </div>
            </template>

            <div class="mb-6 text-center">
                <x-company-logo class="mx-auto h-28 w-auto max-w-[18rem] fill-current text-gray-100" />
                <h1 class="mt-3 text-2xl font-semibold text-gray-100">{{ $company->name ?? config('app.name') }}</h1>
            </div>

            <div class="w-full max-w-lg bg-gray-800 rounded-2xl shadow-xl overflow-hidden">
                <div class="relative bg-black aspect-[4/3]">
                    <video x-ref="video" class="w-full h-full object-cover" autoplay muted playsinline :class="streaming ? '' : 'opacity-40'"></video>
                    <canvas x-ref="canvas" class="hidden"></canvas>

                    <div x-show="!streaming" class="absolute inset-0 flex items-center justify-center">
                        <button type="button" @click="start()" class="rounded-lg bg-indigo-600 px-6 py-3 font-medium text-white hover:bg-indigo-500">
                            Start Camera
                        </button>
                    </div>

                    <template x-if="result">
                        <div
                            class="absolute inset-0 flex flex-col items-center justify-center text-center px-6"
                            :class="result.ok ? 'bg-green-600/90' : 'bg-red-600/90'"
                        >
                            <div class="text-3xl font-bold" x-text="result.ok ? (result.type === 'in' ? 'CLOCKED IN' : 'CLOCKED OUT') : 'FAILED'"></div>
                            <template x-if="result.ok && result.photo">
                                <img :src="result.photo" alt="" class="mt-3 h-24 w-24 rounded-full object-cover ring-4 ring-white/40">
                            </template>
                            <div class="mt-2 text-lg" x-text="result.name || ''"></div>
                            <div class="text-sm opacity-90" x-text="result.message"></div>
                            <div class="mt-1 text-sm opacity-90" x-show="result.time" x-text="result.time"></div>
                            <button type="button" @click="reset()" class="mt-4 rounded bg-white/20 px-4 py-2 text-sm hover:bg-white/30">Next</button>
                        </div>
                    </template>
                </div>

                <form @submit.prevent="punch()" class="p-6 space-y-4">
                    <div
                        class="text-center"
                        x-data="{
                            time: '',
                            date: '',
                            init() {
                                const update = () => {
                                    const now = new Date();
                                    this.time = now.toLocaleTimeString('en-MY', { timeZone: 'Asia/Kuala_Lumpur', hour12: true });
                                    this.date = now.toLocaleDateString('en-MY', { timeZone: 'Asia/Kuala_Lumpur', weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
                                };
                                update();
                                setInterval(update, 1000);
                            },
                        }"
                        x-init="init()"
                    >
                        <div class="text-4xl sm:text-5xl font-bold tabular-nums" x-text="time"></div>
                        <div class="text-sm text-gray-400" x-text="date"></div>
                    </div>

                    <div>
                        <label for="staff_id" class="block text-sm font-medium text-gray-300">Staff ID</label>
                        <input id="staff_id" type="text" x-model="staffId" autocomplete="off"
                            class="mt-1 block w-full rounded-lg border-gray-600 bg-gray-900 text-gray-100 focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-300">Password</label>
                        <input id="password" type="password" x-model="password" autocomplete="off"
                            class="mt-1 block w-full rounded-lg border-gray-600 bg-gray-900 text-gray-100 focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <p x-show="error" x-text="error" class="text-sm text-red-400"></p>

                    <template x-if="!ready">
                        <p class="text-sm text-gray-400">Loading face recognition…</p>
                    </template>

                    <button type="submit" :disabled="busy || !ready"
                        class="w-full rounded-lg bg-indigo-600 px-4 py-3 font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50">
                        <span x-show="!busy">Clock In / Out</span>
                        <span x-show="busy">Verifying…</span>
                    </button>

                    <button type="button" x-show="streaming" @click="stop()" class="w-full text-sm text-gray-400 hover:text-gray-200">
                        Stop Camera
                    </button>

                    <a href="{{ route('login') }}" class="block text-center text-xs text-gray-500 hover:text-gray-300">{{ __('Staff login') }}</a>
                </form>
            </div>
        </div>
    </body>
</html>
