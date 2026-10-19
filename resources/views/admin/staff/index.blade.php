<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-lg sm:text-xl text-gray-800 leading-tight">
                {{ __('Staff') }}
            </h2>
            <a href="{{ route('admin.staff.create') }}"
               class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                {{ __('Add Staff') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @foreach (['staff-created' => 'Staff member created.', 'staff-updated' => 'Staff member updated.', 'staff-photo-updated' => 'Staff photo updated.', 'staff-deleted' => 'Staff member deleted.'] as $key => $text)
                @if (session('status') === $key)
                    <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ __($text) }}</div>
                @endif
            @endforeach
            @if (session('status') === 'cannot-delete-self')
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">{{ __('You cannot delete your own account.') }}</div>
            @endif

            <form method="GET" action="{{ route('admin.staff.index') }}" class="flex flex-wrap items-center gap-2">
                <x-text-input name="search" type="text" class="block w-full sm:w-72" :value="$search" placeholder="{{ __('Search name, staff id or email') }}" />
                <x-primary-button>{{ __('Search') }}</x-primary-button>
                @if ($search)
                    <a href="{{ route('admin.staff.index') }}" class="inline-flex items-center px-4 text-sm text-gray-600 hover:text-gray-900">{{ __('Clear') }}</a>
                @endif
            </form>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr class="whitespace-nowrap">
                                <th class="px-4 py-3 text-left">{{ __('Staff') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Staff ID') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Role') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Face') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Status') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($staff as $member)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <form method="POST" action="{{ route('admin.staff.photo', $member) }}"
                                                  enctype="multipart/form-data" class="shrink-0">
                                                @csrf
                                                <label class="group relative block h-9 w-9 cursor-pointer"
                                                       title="{{ __('Upload profile photo') }}">
                                                    @if ($member->profile_photo_path)
                                                        <img src="{{ Storage::url($member->profile_photo_path) }}" alt=""
                                                             class="h-9 w-9 rounded-full object-cover ring-2 ring-transparent group-hover:ring-indigo-300">
                                                    @else
                                                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-200 text-xs font-medium text-gray-500 ring-2 ring-transparent group-hover:ring-indigo-300">
                                                            {{ Str::of($member->name)->substr(0, 1)->upper() }}
                                                        </span>
                                                    @endif
                                                    <span class="pointer-events-none absolute inset-0 flex items-center justify-center rounded-full bg-black/40 opacity-0 transition group-hover:opacity-100">
                                                        <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.66-.9l.82-1.2A2 2 0 0110.07 4h3.86a2 2 0 011.66.9l.82 1.2a2 2 0 001.66.9H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                        </svg>
                                                    </span>
                                                    <input type="file" id="photo-{{ $member->id }}" name="profile_photo"
                                                           accept="image/*" class="hidden" onchange="this.form.submit()">
                                                </label>
                                            </form>
                                            <div>
                                                <div class="font-medium text-gray-900">{{ $member->name }}</div>
                                                <div class="text-xs text-gray-500">{{ $member->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 whitespace-nowrap">{{ $member->employee_id }}</td>
                                    <td class="px-4 py-3 text-gray-700 whitespace-nowrap">{{ $member->getRoleNames()->map(fn ($r) => ucfirst($r))->join(', ') }}</td>
                                    <td class="px-4 py-3">
                                        @if ($member->hasFaceEnrolled())
                                            <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-700">{{ __('Yes') }}</span>
                                        @else
                                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">{{ __('No') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-0.5 text-xs {{ $member->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                            {{ ucfirst($member->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-3">
                                            <label for="photo-{{ $member->id }}"
                                                   class="cursor-pointer text-gray-600 hover:text-gray-900">{{ __('Photo') }}</label>
                                            <a href="{{ route('admin.staff.edit', $member) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('Edit') }}</a>
                                            <form method="POST" action="{{ route('admin.staff.destroy', $member) }}"
                                                  onsubmit="return confirm('Delete this staff member?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900">{{ __('Delete') }}</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">{{ __('No staff found.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $staff->links() }}
        </div>
    </div>
</x-admin-layout>
