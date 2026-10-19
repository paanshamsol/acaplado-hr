<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-lg sm:text-xl text-gray-800 leading-tight">
                {{ __('Notifications') }}
            </h2>
            <div class="flex flex-wrap items-center gap-2">
                @if (auth()->user()->unreadNotifications()->exists())
                    <form method="POST" action="{{ route('notifications.read-all') }}">
                        @csrf
                        <x-secondary-button class="text-xs px-2.5 py-1.5">{{ __('Mark all as read') }}</x-secondary-button>
                    </form>
                @endif
                @if ($notifications->total() > 0)
                    <form method="POST" action="{{ route('notifications.destroy-all') }}"
                          onsubmit="return confirm('Delete your entire notification history?');">
                        @csrf
                        @method('DELETE')
                        <x-danger-button class="text-xs px-2.5 py-1.5">{{ __('Clear all') }}</x-danger-button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-3">
            @if (session('status') === 'notifications-read')
                <div class="rounded-md bg-green-50 px-3 py-2.5 text-xs sm:text-sm text-green-700">{{ __('All notifications marked as read.') }}</div>
            @elseif (session('status') === 'notification-deleted')
                <div class="rounded-md bg-green-50 px-3 py-2.5 text-xs sm:text-sm text-green-700">{{ __('Notification removed.') }}</div>
            @elseif (session('status') === 'notifications-cleared')
                <div class="rounded-md bg-green-50 px-3 py-2.5 text-xs sm:text-sm text-green-700">{{ __('Notification history cleared.') }}</div>
            @endif

            @forelse ($notifications as $notification)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 flex items-start gap-3 {{ $notification->read_at ? 'opacity-60' : '' }}">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-800">{{ $notification->data['message'] ?? '' }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ $notification->created_at?->diffForHumans() }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if (! $notification->read_at)
                            <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                @csrf
                                @method('PATCH')
                                <x-primary-button class="text-xs px-2.5 py-1.5 justify-center">{{ __('Open') }}</x-primary-button>
                            </form>
                        @elseif (filled($notification->data['link'] ?? null))
                            <a href="{{ $notification->data['link'] }}"
                               class="inline-flex items-center rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-700 shadow-xs hover:bg-gray-50 transition">
                                {{ __('Open') }}
                            </a>
                        @endif

                        <form method="POST" action="{{ route('notifications.destroy', $notification->id) }}"
                              onsubmit="return confirm('Remove this notification?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" title="{{ __('Remove') }}" aria-label="{{ __('Remove notification') }}"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-gray-200 bg-white text-gray-400 shadow-xs hover:border-red-200 hover:bg-red-50 hover:text-red-600 transition">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-8 text-center text-gray-500">
                    {{ __('No notifications yet.') }}
                </div>
            @endforelse

            @if ($notifications->hasPages())
                <div>{{ $notifications->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
