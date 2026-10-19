<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-lg sm:text-xl text-gray-800 leading-tight">
                {{ __('My Leave') }}
            </h2>

            <form method="GET" action="{{ route('staff.leaves.index') }}" class="flex items-center gap-2">
                <x-input-label for="year" :value="__('Year')" class="sr-only" />
                <x-text-input id="year" name="year" type="number" min="2000" max="2100" class="w-28"
                    :value="$year" />
                <x-primary-button>{{ __('View') }}</x-primary-button>
            </form>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status') === 'leave-requested')
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ __('Leave request submitted for approval.') }}</div>
            @endif
            @if (session('status') === 'leave-cancelled')
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ __('Leave request cancelled.') }}</div>
            @endif
            @if (session('status') === 'leave-not-cancellable')
                <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-700">{{ __('Only pending requests can be cancelled.') }}</div>
            @endif

            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Entitlements --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 border-b border-gray-100">
                    <h3 class="font-medium text-gray-900">{{ __('My entitlements for :year', ['year' => $year]) }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ __('Entitlement + carried over - used = remaining.') }}</p>
                    <p class="mt-1 text-sm text-gray-500">{{ __('If your medical, emergency or bereavement leave runs out, the surplus is taken from your annual leave.') }}</p>
                </div>
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
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">{{ $type->name }}</div>
                                        @if ($type->requires_attachment)
                                            <div class="text-xs text-gray-500">{{ __('Attachment required') }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-900">{{ $entitlement }}</td>
                                    <td class="px-4 py-3 text-right text-gray-900">{{ $carried }}</td>
                                    <td class="px-4 py-3 text-right text-gray-900">{{ $used }}</td>
                                    <td class="px-4 py-3 text-right font-semibold {{ $remaining < 0 ? 'text-red-600' : 'text-green-600' }}">
                                        {{ $remaining }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Apply --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('staff.leaves.store') }}" enctype="multipart/form-data"
                    class="p-6 space-y-4" x-data="{ needsDoc: false }">
                    @csrf

                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ __('Apply for leave') }}</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ __('Your request will be reviewed by HR or an administrator.') }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ __('A supporting document is only available for Medical Leave. Annual, emergency and bereavement leave do not need one.') }}</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <x-input-label for="leave_type_id" :value="__('Leave type')" />
                            <select id="leave_type_id" name="leave_type_id"
                                x-on:change="needsDoc = $event.target.selectedOptions[0]?.dataset.requires === '1'"
                                x-init="needsDoc = $el.selectedOptions[0]?.dataset.requires === '1'"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('Select a leave type') }}</option>
                                @foreach ($leaveTypes as $type)
                                    <option value="{{ $type->id }}" data-requires="{{ $type->requires_attachment ? '1' : '0' }}" @selected(old('leave_type_id') == $type->id)>
                                        {{ $type->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('leave_type_id')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="start_date" :value="__('Start date')" />
                            <x-text-input id="start_date" name="start_date" type="date" class="mt-1 block w-full"
                                :value="old('start_date')" />
                            <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="end_date" :value="__('End date')" />
                            <x-text-input id="end_date" name="end_date" type="date" class="mt-1 block w-full"
                                :value="old('end_date')" />
                            <x-input-error :messages="$errors->get('end_date')" class="mt-2" />
                        </div>

                        <div class="sm:col-span-2">
                            <x-input-label for="reason" :value="__('Reason (optional)')" />
                            <textarea id="reason" name="reason" rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('reason') }}</textarea>
                            <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                        </div>

                        <div class="sm:col-span-2">
                            <x-input-label for="attachment">
                                <span x-text="needsDoc ? '{{ __('Supporting document (required)') }}' : '{{ __('Supporting document (not required)') }}'"></span>
                            </x-input-label>
                            <input id="attachment" name="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png"
                                x-bind:required="needsDoc" x-bind:disabled="! needsDoc"
                                class="mt-1 block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100 disabled:cursor-not-allowed disabled:opacity-50">
                            <p class="mt-1 text-xs text-gray-500" x-show="! needsDoc">{{ __('Only Medical Leave accepts a supporting document.') }}</p>
                            <p class="mt-1 text-xs text-red-600" x-show="needsDoc" x-cloak>{{ __('Required for Medical Leave. PDF, JPG or PNG, up to 4 MB.') }}</p>
                            <x-input-error :messages="$errors->get('attachment')" class="mt-2" />
                        </div>
                    </div>

                    <x-primary-button>{{ __('Submit request') }}</x-primary-button>
                </form>
            </div>

            {{-- History --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 border-b border-gray-100">
                    <h3 class="font-medium text-gray-900">{{ __('My requests') }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr class="whitespace-nowrap">
                                <th class="px-4 py-3 text-left">{{ __('Type') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Dates') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Days') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Status') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($requests as $leaveRequest)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">{{ $leaveRequest->leaveType->name }}</div>
                                        @if ($leaveRequest->reason)
                                            <div class="text-xs text-gray-500">{{ $leaveRequest->reason }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-700">
                                        {{ $leaveRequest->start_date->format('d M Y') }} – {{ $leaveRequest->end_date->format('d M Y') }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-900">{{ (float) $leaveRequest->days }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @php($badge = [
                                            'pending' => 'bg-amber-100 text-amber-700',
                                            'approved' => 'bg-green-100 text-green-700',
                                            'rejected' => 'bg-red-100 text-red-700',
                                        ][$leaveRequest->status] ?? 'bg-gray-100 text-gray-700')
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $badge }}">
                                            {{ ucfirst($leaveRequest->status) }}
                                        </span>
                                        @if ($leaveRequest->status === 'rejected' && $leaveRequest->rejection_reason)
                                            <div class="mt-1 text-xs text-gray-500">{{ $leaveRequest->rejection_reason }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if ($leaveRequest->hasAttachment())
                                            <a href="{{ Storage::disk('public')->url($leaveRequest->attachment_path) }}" target="_blank"
                                               class="text-indigo-600 hover:text-indigo-900">{{ __('Attachment') }}</a>
                                        @endif
                                        @if ($leaveRequest->isPending())
                                            <form method="POST" action="{{ route('staff.leaves.destroy', $leaveRequest) }}"
                                                  class="inline" onsubmit="return confirm('Cancel this leave request?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="ml-3 text-red-600 hover:text-red-900">{{ __('Cancel') }}</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">{{ __('No leave requests yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
