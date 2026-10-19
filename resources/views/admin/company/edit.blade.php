<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Company Information') }}
        </h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            @if (session('status') === 'company-updated')
                <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">
                    {{ __('Company information updated.') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('admin.company.update') }}"
                    enctype="multipart/form-data"
                    x-data="operationalTimesForm(@js(old('operational_times', $company->operationalTimes->map(fn ($t) => ['id' => $t->id, 'label' => $t->label, 'start_time' => $t->start_time, 'end_time' => $t->end_time]))), @js($errors->any()))"
                    @input="dirty = true"
                    @change="dirty = true"
                    class="p-6 space-y-6">
                    @csrf
                    @method('PUT')

                    <div class="border-b border-gray-200 pb-6" x-data="companyLogo({ hasLogo: @js((bool) $company->logoUrl()), url: @js($company->logoUrl()) })">
                        <h3 class="text-lg font-semibold text-gray-900">
                            {{ __('Company Logo') }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ __('Shown on the staff login page and the admin dashboard. PNG, JPG, SVG or WebP up to 2 MB.') }}
                        </p>

                        <div class="mt-4 flex flex-wrap items-center gap-6">
                            <div class="flex h-20 w-20 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 p-2">
                                <template x-if="preview && ! remove">
                                    <img :src="preview" alt="{{ __('Company logo') }}" class="h-full w-full object-contain">
                                </template>
                                <template x-if="! preview || remove">
                                    <x-application-logo class="h-10 w-10 fill-current text-gray-400" />
                                </template>
                            </div>

                            <div class="flex-1 min-w-[16rem]">
                                <input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp"
                                    @change="onSelect($event)"
                                    class="block w-full text-sm text-gray-600 file:me-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                                <x-input-error :messages="$errors->get('logo')" class="mt-2" />

                                @if ($company->logoUrl())
                                    <label class="mt-3 inline-flex items-center gap-2 text-sm text-red-600">
                                        <input type="checkbox" name="remove_logo" value="1" x-model="remove"
                                            class="rounded border-gray-300 text-red-600 shadow-sm focus:ring-red-500">
                                        {{ __('Remove current logo') }}
                                    </label>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="border-b border-gray-200 pb-6" x-data="{ color: @js(old('brand_color', $company->brandColor())) }">
                        <h3 class="text-lg font-semibold text-gray-900">
                            {{ __('Brand Colour') }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ __('Accent colour for the top navigation, sidebar and buttons. Pick one that matches your logo.') }}
                        </p>

                        <div class="mt-4 flex flex-wrap items-center gap-4">
                            <input id="brand_color" name="brand_color" type="color" x-model="color"
                                value="{{ old('brand_color', $company->brandColor()) }}"
                                class="h-10 w-16 cursor-pointer rounded-md border border-gray-300 bg-white p-1">
                            <span class="inline-flex items-center gap-2 text-sm text-gray-600">
                                <span class="h-6 w-6 rounded-full border border-gray-200 shadow-inner" :style="{ backgroundColor: color }"></span>
                                <span x-text="color"></span>
                            </span>
                            <span class="text-xs text-gray-400">{{ __('Default: :hex', ['hex' => \App\Models\CompanySetting::DEFAULT_BRAND_COLOR]) }}</span>
                        </div>

                        <x-input-error :messages="$errors->get('brand_color')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="name" :value="__('Company Name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                            :value="old('name', $company->name)" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="address" :value="__('Address')" />
                        <textarea id="address" name="address" rows="3" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('address', $company->address) }}</textarea>
                        <x-input-error :messages="$errors->get('address')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <x-input-label for="phone" :value="__('Phone')" />
                            <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full"
                                :value="old('phone', $company->phone)" required />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="email" :value="__('Email')" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                                :value="old('email', $company->email)" required />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>
                    </div>

                    <div x-data="companyLocationMap({ latitude: @js(old('latitude', $company->latitude)), longitude: @js(old('longitude', $company->longitude)) })"
                        class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-semibold text-gray-900">
                            {{ __('Company Location') }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ __('Exact location on Google Maps from the company coordinates (latitude, longitude).') }}
                        </p>

                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div>
                                <x-input-label for="latitude" :value="__('Latitude')" />
                                <x-text-input id="latitude" name="latitude" type="text" class="mt-1 block w-full"
                                    x-model="latitude" placeholder="{{ __('e.g. 3.139003') }}" required />
                                <x-input-error :messages="$errors->get('latitude')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="longitude" :value="__('Longitude')" />
                                <x-text-input id="longitude" name="longitude" type="text" class="mt-1 block w-full"
                                    x-model="longitude" placeholder="{{ __('e.g. 101.686855') }}" required />
                                <x-input-error :messages="$errors->get('longitude')" class="mt-2" />
                            </div>
                        </div>

                        <button type="button" @click="useCurrentLocation()"
                            class="mt-3 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                            {{ __('Use my current location') }}
                        </button>

                        <p x-show="locationError" x-cloak x-text="locationError"
                            class="mt-2 text-sm text-red-600"></p>

                        <div x-show="mapSrc" class="mt-4 overflow-hidden rounded-lg border border-gray-200">
                            <iframe :src="mapSrc" class="block h-64 w-full border-0" loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"></iframe>
                        </div>

                        <p x-show="!mapSrc" x-cloak class="mt-4 text-sm text-gray-400">
                            {{ __('Enter coordinates or use your location to preview the map.') }}
                        </p>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">
                            {{ __('Operational Times') }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ __('Operating windows in 24-hour format, Malaysia time (Asia/Kuala_Lumpur). A window that ends earlier than it starts runs overnight. At least one window is required.') }}
                        </p>

                        <div class="mt-4 space-y-3">
                            <template x-for="(row, index) in rows" :key="row.id ?? 'new-' + index">
                                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end rounded-lg border border-gray-200 p-3">
                                    <input type="hidden" :name="'operational_times[' + index + '][id]'" x-model="row.id">

                                    <div>
                                        <x-input-label x-bind:for="'label_' + index" :value="__('Label (optional)')" />
                                        <x-text-input x-bind:id="'label_' + index" x-bind:name="'operational_times[' + index + '][label]'" type="text" class="mt-1 block w-full"
                                            x-model="row.label" placeholder="{{ __('e.g. Night shift') }}" />
                                    </div>

                                    <div>
                                        <x-input-label x-bind:for="'start_time_' + index" :value="__('Start time')" />
                                        <x-text-input x-bind:id="'start_time_' + index" x-bind:name="'operational_times[' + index + '][start_time]'" type="time" class="mt-1 block w-full"
                                            x-model="row.start_time" required />
                                    </div>

                                    <div>
                                        <x-input-label x-bind:for="'end_time_' + index" :value="__('End time')" />
                                        <x-text-input x-bind:id="'end_time_' + index" x-bind:name="'operational_times[' + index + '][end_time]'" type="time" class="mt-1 block w-full"
                                            x-model="row.end_time" required />
                                    </div>

                                    <div>
                                        <button type="button" @click="removeRow(index)"
                                            class="text-sm font-medium text-red-600 hover:text-red-500">
                                            {{ __('Remove') }}
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <p x-show="rows.length === 0" x-cloak class="text-sm text-gray-400">
                                {{ __('No operational time yet — add at least one.') }}
                            </p>
                        </div>

                        <button type="button" @click="addRow()"
                            class="mt-3 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                            + {{ __('Add operational time') }}
                        </button>

                        <x-input-error :messages="$errors->get('operational_times')" class="mt-2" />
                    </div>

                    <div class="flex flex-wrap items-center gap-4">
                        <x-primary-button x-bind:disabled="!dirty"
                            class="disabled:opacity-50 disabled:cursor-not-allowed">{{ __('Save') }}</x-primary-button>
                        <span x-show="!dirty" x-cloak class="text-sm text-gray-500">
                            {{ __('No changes to save yet.') }}
                        </span>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>