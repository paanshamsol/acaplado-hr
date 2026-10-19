<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-lg sm:text-xl text-gray-800 leading-tight">
                {{ __('New Staff') }}
            </h2>
            <a href="{{ route('admin.staff.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Back') }}</a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            @include('admin.staff._form', [
                'staff' => null,
                'action' => route('admin.staff.store'),
                'method' => 'POST',
                'submitLabel' => __('Create Staff'),
            ])
        </div>
    </div>
</x-admin-layout>
