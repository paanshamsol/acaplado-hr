<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-lg sm:text-xl text-gray-800 leading-tight">
                {{ __('Leave Entitlements') }} — {{ $staff->name }}
            </h2>
            <a href="{{ route('admin.leaves.index', ['year' => $year]) }}"
               class="text-sm text-gray-600 hover:text-gray-900">{{ __('Back') }}</a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 border-b border-gray-100">
                    <h3 class="font-medium text-gray-900">{{ __('Leave balance for :year', ['year' => $year]) }}</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ __('Entitlement + carried over - used = remaining, per leave type.') }}
                    </p>
                </div>

                @php($sumEntitlement = 0)
                @php($sumCarried = 0)
                @php($sumUsed = 0)
                @php($sumRemaining = 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr class="whitespace-nowrap">
                                <th class="px-4 py-3 text-left">{{ __('Leave type') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Entitlement') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Carried over') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Used') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Remaining') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($leaveTypes as $type)
                                @php($balance = $balances->get($type->id))
                                @php($entitlement = $balance ? (float) $balance->entitlement : (float) $type->default_days)
                                @php($carried = $balance ? (float) $balance->carried_over : 0.0)
                                @php($used = $balance ? (float) $balance->used : 0.0)
                                @php($remaining = $entitlement + $carried - $used)
                                @php($sumEntitlement += $entitlement)
                                @php($sumCarried += $carried)
                                @php($sumUsed += $used)
                                @php($sumRemaining += $remaining)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">{{ $type->name }}</div>
                                        <div class="text-xs text-gray-500">
                                            {{ __('Default') }}: {{ (float) $type->default_days }} {{ __('days') }}
                                            @unless ($balance) · {{ __('not set, showing default') }} @endunless
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-900">{{ $entitlement }}</td>
                                    <td class="px-4 py-3 text-right text-gray-900">{{ $carried }}</td>
                                    <td class="px-4 py-3 text-right font-medium text-gray-900">{{ $used }}</td>
                                    <td class="px-4 py-3 text-right font-semibold {{ $remaining < 0 ? 'text-red-600' : 'text-green-600' }}">
                                        {{ $remaining }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 font-medium text-gray-900">
                            <tr>
                                <td class="px-4 py-3 text-left">{{ __('Total') }}</td>
                                <td class="px-4 py-3 text-right">{{ $sumEntitlement }}</td>
                                <td class="px-4 py-3 text-right">{{ $sumCarried }}</td>
                                <td class="px-4 py-3 text-right">{{ $sumUsed }}</td>
                                <td class="px-4 py-3 text-right {{ $sumRemaining < 0 ? 'text-red-600' : 'text-green-600' }}">
                                    {{ $sumRemaining }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('admin.leaves.update', $staff) }}" class="p-6 space-y-6">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="year" value="{{ $year }}">

                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">
                            {{ __('Edit entitlements for :year', ['year' => $year]) }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ __('Set entitlement, carried over and days already used for each leave type. Tick "Delete" to remove an entitlement.') }}
                        </p>
                    </div>

                    <div class="space-y-4">
                        @foreach ($leaveTypes as $type)
                            @php($balance = $balances->get($type->id))
                            <div class="rounded-lg border border-gray-200 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-medium text-gray-900">{{ $type->name }}</div>
                                        <div class="text-xs text-gray-500">
                                            {{ __('Default') }}: {{ (float) $type->default_days }} {{ __('days') }}
                                            @if ($type->requires_attachment) · {{ __('Attachment required') }} @endif
                                        </div>
                                    </div>
                                    @if ($balance)
                                        <label class="inline-flex items-center gap-2 text-sm text-red-600">
                                            <input type="checkbox" name="delete[{{ $type->id }}]" value="1"
                                                class="rounded border-gray-300 text-red-600 shadow-sm focus:ring-red-500">
                                            {{ __('Delete') }}
                                        </label>
                                    @endif
                                </div>

                                <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <x-input-label :for="'entitlement_'.$type->id" :value="__('Entitlement (days)')" />
                                        <x-text-input :id="'entitlement_'.$type->id"
                                            name="entitlements[{{ $type->id }}]" type="number" step="0.5" min="0" max="365"
                                            class="mt-1 block w-full"
                                            :value="old('entitlements.'.$type->id, $balance ? (float) $balance->entitlement : '')"
                                            placeholder="{{ (float) $type->default_days }}" />
                                        <x-input-error :messages="$errors->get('entitlements.'.$type->id)" class="mt-2" />
                                    </div>

                                    <div>
                                        <x-input-label :for="'carried_over_'.$type->id" :value="__('Carried over (days)')" />
                                        <x-text-input :id="'carried_over_'.$type->id"
                                            name="carried_over[{{ $type->id }}]" type="number" step="0.5" min="0" max="365"
                                            class="mt-1 block w-full"
                                            :value="old('carried_over.'.$type->id, $balance ? (float) $balance->carried_over : '')" />
                                        <x-input-error :messages="$errors->get('carried_over.'.$type->id)" class="mt-2" />
                                    </div>

                                    <div>
                                        <x-input-label :for="'used_'.$type->id" :value="__('Used (days)')" />
                                        <x-text-input :id="'used_'.$type->id"
                                            name="used[{{ $type->id }}]" type="number" step="0.5" min="0" max="365"
                                            class="mt-1 block w-full"
                                            :value="old('used.'.$type->id, $balance ? (float) $balance->used : '')" />
                                        <x-input-error :messages="$errors->get('used.'.$type->id)" class="mt-2" />
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>{{ __('Save') }}</x-primary-button>
                        <a href="{{ route('admin.leaves.index', ['year' => $year]) }}"
                           class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('admin.leaves.destroy', $staff) }}" class="p-6"
                      onsubmit="return confirm('Delete all leave entitlements for this staff member?');">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="year" value="{{ $year }}">

                    <h3 class="text-lg font-medium text-gray-900">{{ __('Delete entitlements') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ __('Remove all leave entitlements for :name in :year.', ['name' => $staff->name, 'year' => $year]) }}
                    </p>

                    <x-danger-button class="mt-4">{{ __('Delete all entitlements') }}</x-danger-button>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
