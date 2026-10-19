@props(['active' => false])

@php
$classes = ($active ?? false)
            ? 'flex items-center rounded-md px-3 py-2 text-sm font-medium text-white bg-[var(--brand)] shadow-sm'
            : 'flex items-center rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-[var(--brand)] transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
