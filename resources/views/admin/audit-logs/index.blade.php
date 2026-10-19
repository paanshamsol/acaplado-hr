<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-lg sm:text-xl text-gray-800 leading-tight">
            {{ __('Audit Logs') }}
        </h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-4 py-3 sm:px-5 sm:py-3.5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-[var(--brand)]">{{ __('Compliance') }}</div>
                        <h3 class="text-sm sm:text-base font-semibold text-gray-900">{{ __('Audit Logs') }}</h3>
                        <p class="mt-0.5 text-xs text-gray-500">{{ __('Recent create / update / delete events across staff, leave, payroll and settings.') }}</p>
                    </div>

                    <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="inline-flex flex-wrap items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5">
                        <label for="audit_action" class="text-xs font-medium text-gray-600">{{ __('Action:') }}</label>
                        <select id="audit_action" name="action"
                            class="rounded-md border-gray-300 bg-white py-1 pl-2.5 pr-7 text-sm font-medium text-gray-900 shadow-sm focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                            <option value="" @selected($action === '')>{{ __('All') }}</option>
                            <option value="created" @selected($action === 'created')>{{ __('Created') }}</option>
                            <option value="updated" @selected($action === 'updated')>{{ __('Updated') }}</option>
                            <option value="deleted" @selected($action === 'deleted')>{{ __('Deleted') }}</option>
                        </select>

                        <label for="audit_model" class="text-xs font-medium text-gray-600">{{ __('Model:') }}</label>
                        <select id="audit_model" name="model"
                            class="rounded-md border-gray-300 bg-white py-1 pl-2.5 pr-7 text-sm font-medium text-gray-900 shadow-sm focus:border-[var(--brand)] focus:ring-[var(--brand)]">
                            <option value="" @selected($model === '')>{{ __('All') }}</option>
                            @foreach ($modelOptions as $option)
                                <option value="{{ $option }}" @selected($model === $option)>{{ class_basename($option) }}</option>
                            @endforeach
                        </select>

                        <x-secondary-button class="text-xs px-2.5 py-1">{{ __('Filter') }}</x-secondary-button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-left">{{ __('When') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('User') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Action') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Model') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Changes') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($logs as $log)
                                <tr>
                                    <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                                    <td class="px-4 py-3 text-gray-900">{{ $log->user?->name ?? __('System') }}</td>
                                    <td class="px-4 py-3">
                                        @php
                                            $color = match ($log->action) {
                                                'created' => 'bg-green-100 text-green-700',
                                                'updated' => 'bg-sky-100 text-sky-700',
                                                'deleted' => 'bg-red-100 text-red-700',
                                                default => 'bg-gray-100 text-gray-700',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $color }}">
                                            {{ ucfirst($log->action) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-700">
                                        {{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-600 max-w-md">
                                        @if (filled($log->new_values))
                                            <details>
                                                <summary class="cursor-pointer text-[var(--brand)]">{{ __('View') }}</summary>
                                                <pre class="mt-1 whitespace-pre-wrap break-all text-[10px] text-gray-600">{{ json_encode($log->new_values, JSON_PRETTY_PRINT) }}</pre>
                                                @if (filled($log->old_values))
                                                    <pre class="mt-1 whitespace-pre-wrap break-all text-[10px] text-gray-400">{{ json_encode($log->old_values, JSON_PRETTY_PRINT) }}</pre>
                                                @endif
                                            </details>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">{{ __('No audit events recorded yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($logs->hasPages())
                    <div class="px-4 py-3 border-t border-gray-100">
                        {{ $logs->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-admin-layout>
