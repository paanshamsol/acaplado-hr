@php
    $staff = $staff ?? null;
    $currentPassword = $staff?->visiblePassword();
    $profilePhotoUrl = $staff?->profile_photo_path
        ? Storage::disk('public')->url($staff->profile_photo_path)
        : null;
    $autoSyncPhoto = $profilePhotoUrl && request()->boolean('sync') ? $profilePhotoUrl : '';
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-8">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-6">
        <h3 class="text-base font-semibold text-gray-900">{{ __('Account') }}</h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <x-input-label for="name" :value="__('Full Name')" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $staff?->name)" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="employee_id" :value="__('Staff ID')" />
                <x-text-input id="employee_id" name="employee_id" type="text" class="mt-1 block w-full" :value="old('employee_id', $staff?->employee_id)" required />
                <x-input-error :messages="$errors->get('employee_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $staff?->email)" required />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="phone" :value="__('Phone')" />
                <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone', $staff?->phone)" />
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="ic_number" :value="__('IC Number (MyKad / NRIC)')" />
                <x-text-input id="ic_number" name="ic_number" type="text" class="mt-1 block w-full" :value="old('ic_number', $staff?->ic_number)" placeholder="YYMMDD-PB-###G" />
                <p class="mt-1 text-xs text-gray-500">{{ __('Malaysian IC number, e.g. 850101-10-5553') }}</p>
                <x-input-error :messages="$errors->get('ic_number')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="address" :value="__('Address')" />
                <x-text-input id="address" name="address" type="text" class="mt-1 block w-full" :value="old('address', $staff?->address)" />
                <x-input-error :messages="$errors->get('address')" class="mt-2" />
            </div>

            @if ($staff)
                <div class="sm:col-span-2">
                    <x-input-label for="current_password_display" :value="__('Current Password')" />
                    <x-text-input id="current_password_display" type="text" class="mt-1 block w-full bg-gray-50" :value="$currentPassword" readonly autocomplete="off" />
                    @if ($currentPassword)
                        <p class="mt-1 text-xs text-gray-500">{{ __("Read-only. This is the employee's current login password, synced from the database.") }}</p>
                    @else
                        <p class="mt-1 text-xs text-gray-500">{{ __('Not available - no stored copy for this account. Set a new password below.') }}</p>
                    @endif
                </div>
            @endif

            <div>
                <x-input-label for="password" :value="$staff ? __('New Password (leave blank to keep)') : __('Password')" />
                <x-password-input id="password" name="password" class="mt-1" :required="! $staff" autocomplete="new-password" />
                <p class="mt-1 text-xs text-gray-500">{{ __('Minimum 7 characters with an uppercase letter, a lowercase letter and a symbol.') }}</p>
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="role" :value="__('Role')" />
                <select id="role" name="role" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach (['staff' => 'Staff', 'contract' => 'Contract', 'part-time' => 'Part-Time', 'hr' => 'HR', 'admin' => 'Admin'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('role', $staff?->getRoleNames()->first() ?? 'staff') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('role')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="status" :value="__('Status')" />
                <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach (['active' => 'Active', 'inactive' => 'Inactive'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $staff?->status ?? 'active') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('status')" class="mt-2" />
            </div>
        </div>
    </div>

    <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-6">
        <h3 class="text-base font-semibold text-gray-900">{{ __('Employment') }}</h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <x-input-label for="join_date" :value="__('Join Date')" />
                <x-text-input id="join_date" name="join_date" type="date" class="mt-1 block w-full" :value="old('join_date', $staff?->join_date?->format('Y-m-d'))" />
                <x-input-error :messages="$errors->get('join_date')" class="mt-2" />
            </div>
        </div>
    </div>

    <div
        x-data="faceEnroll({{ $staff?->hasFaceEnrolled() ? 'true' : 'false' }}, @js($autoSyncPhoto))"
        x-init="init()"
        class="bg-white shadow-sm sm:rounded-lg p-6 space-y-6"
    >
        <h3 class="text-base font-semibold text-gray-900">{{ __('Profile Photo & Face Enrollment') }}</h3>

        <div>
            <x-input-label for="profile_photo" :value="__('Profile Photo')" />
            @if ($profilePhotoUrl)
                <img src="{{ $profilePhotoUrl }}" alt="" class="mt-2 h-20 w-20 rounded-full object-cover">
            @endif
            <input id="profile_photo" name="profile_photo" type="file" accept="image/*"
                @change="onPhotoSelected($event)"
                class="mt-2 block w-full text-sm text-gray-600">
            <p class="mt-1 text-xs text-gray-500">{{ __('Uploading a photo scans it and syncs the face used for clock-in/out.') }}</p>
            <template x-if="photoBusy">
                <p class="mt-1 text-xs text-indigo-600">{{ __('Scanning photo…') }}</p>
            </template>
            <x-input-error :messages="$errors->get('profile_photo')" class="mt-2" />
        </div>

        <div class="rounded-lg border border-gray-200 p-4">
            <input type="hidden" name="face_descriptor" :value="descriptor">

            <div class="flex flex-wrap items-center gap-3">
                <span class="text-sm font-medium text-gray-700">Face enrollment</span>
                <span class="rounded-full px-2 py-0.5 text-xs"
                      :class="enrolled ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
                      x-text="enrolled ? 'Enrolled' : 'Not enrolled'"></span>
                @if ($profilePhotoUrl)
                    <button type="button" @click="syncFromPhotoUrl(@js($profilePhotoUrl))" :disabled="photoBusy || !ready"
                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500 disabled:opacity-50">
                        {{ __('Sync from current photo') }}
                    </button>
                @else
                    <span class="text-xs text-gray-500">{{ __('Upload a profile photo to sync a face.') }}</span>
                @endif
            </div>

            <template x-if="cameraWarning">
                <div class="rounded-md bg-amber-50 border border-amber-300 p-3 text-sm text-amber-800">
                    <div class="font-semibold">{{ __('Camera unavailable') }}</div>
                    <p class="mt-1" x-text="cameraWarning"></p>
                </div>
            </template>

            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="relative bg-black rounded-lg overflow-hidden aspect-[4/3]">
                    <video x-ref="video" class="w-full h-full object-cover" autoplay muted playsinline></video>
                    <canvas x-ref="canvas" class="hidden"></canvas>
                    <div x-show="!streaming" class="absolute inset-0 flex items-center justify-center">
                        <span class="text-xs text-gray-300" x-show="ready">Camera off</span>
                        <span class="text-xs text-gray-400" x-show="!ready" x-text="message"></span>
                    </div>
                </div>
                <div class="flex flex-col justify-center gap-2">
                    <template x-if="preview">
                        <img :src="preview" alt="Captured face" class="h-28 w-28 rounded-lg object-cover border border-gray-200">
                    </template>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="start()" x-show="!streaming" :disabled="!ready"
                            class="rounded-md bg-gray-800 px-3 py-2 text-sm text-white hover:bg-gray-700 disabled:opacity-50">Start Camera</button>
                        <button type="button" @click="capture()" x-show="streaming" :disabled="busy"
                            class="rounded-md bg-indigo-600 px-3 py-2 text-sm text-white hover:bg-indigo-500 disabled:opacity-50">Capture</button>
                        <button type="button" @click="stop()" x-show="streaming"
                            class="rounded-md bg-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-300">Stop</button>
                        <button type="button" @click="clear()" x-show="enrolled"
                            class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-600 hover:bg-red-100">Clear</button>
                    </div>
                </div>
            </div>

            <p class="mt-3 text-sm" :class="error ? 'text-red-600' : 'text-gray-500'" x-text="error || message"></p>
        </div>
    </div>

    <div class="flex items-center gap-4">
        <x-primary-button>{{ $submitLabel }}</x-primary-button>
        <a href="{{ route('admin.staff.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
    </div>
</form>
