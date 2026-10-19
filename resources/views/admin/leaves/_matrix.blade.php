@php($canManage = auth()->user()->can('manage-leaves'))
@php($colspan = 1 + $leaveTypes->count() + 1 + ($canManage ? 1 : 0))

<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
            <tr class="whitespace-nowrap">
                <th class="px-4 py-3 text-left">{{ __('Staff') }}</th>
                @foreach ($leaveTypes as $type)
                    <th class="px-4 py-3 text-left">{{ $type->name }}</th>
                @endforeach
                <th class="px-4 py-3 text-left">{{ __('Total') }}</th>
                @if ($canManage)
                    <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                @endif
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($staff as $member)
                @php($totalUsed = 0)
                @php($totalRemaining = 0)
                <tr>
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900">{{ $member->name }}</div>
                        <div class="text-xs text-gray-500">
                            {{ $member->employee_id }}
                            @if ($member->getRoleNames()->isNotEmpty())
                                · {{ $member->getRoleNames()->map(fn ($role) => ucfirst($role))->join(', ') }}
                            @endif
                        </div>
                    </td>
                    @foreach ($leaveTypes as $type)
                        @php($balance = $balances->get($member->id.'-'.$type->id))
                        @php($entitlement = $balance ? (float) $balance->entitlement : (float) $type->default_days)
                        @php($carried = $balance ? (float) $balance->carried_over : 0.0)
                        @php($used = $balance ? (float) $balance->used : 0.0)
                        @php($remaining = $balance ? $balance->remaining() : (float) $type->default_days)
                        @php($totalUsed += $used)
                        @php($totalRemaining += $remaining)
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="font-medium text-gray-900">
                                {{ $entitlement }} {{ __('days') }}
                                @unless ($balance)
                                    <span class="text-xs font-normal text-gray-400">{{ __('(default)') }}</span>
                                @endunless
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ __('Used') }} <span class="font-medium text-gray-700">{{ $used }}</span>
                                · {{ __('Left') }} <span class="font-medium text-gray-700">{{ $remaining }}</span>
                            </div>
                            @if ($carried > 0)
                                <div class="text-xs text-gray-400">
                                    {{ __('Carried over') }}: {{ $carried }}
                                </div>
                            @endif
                        </td>
                    @endforeach
                    <td class="px-4 py-3 whitespace-nowrap">
                        <div class="text-xs text-gray-500">
                            {{ __('Used') }} <span class="font-medium text-gray-700">{{ $totalUsed }}</span>
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ __('Left') }} <span class="font-medium text-gray-700">{{ $totalRemaining }}</span>
                        </div>
                    </td>
                    @if ($canManage)
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.leaves.edit', ['staff' => $member, 'year' => $year]) }}"
                                   class="text-indigo-600 hover:text-indigo-900">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('admin.leaves.destroy', $member) }}"
                                      onsubmit="return confirm('Delete all leave entitlements for this staff member?');">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="year" value="{{ $year }}">
                                    <button type="submit" class="text-red-600 hover:text-red-900">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $colspan }}" class="px-4 py-8 text-center text-gray-500">{{ __('No staff found.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
