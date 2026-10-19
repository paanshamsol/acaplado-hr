<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-lg sm:text-xl text-gray-800 leading-tight">
                {{ __('Edit Staff') }} — {{ $staff->name }}
            </h2>
            <a href="{{ route('admin.staff.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Back') }}</a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            @include('admin.staff._form', [
                'staff' => $staff,
                'action' => route('admin.staff.update', $staff),
                'method' => 'PUT',
                'submitLabel' => __('Save Changes'),
            ])

            @include('admin.staff._salary', [
                'staff' => $staff,
                'adjustments' => $adjustments,
                'salary' => $salary,
            ])
        </div>
    </div>
</x-admin-layout>
