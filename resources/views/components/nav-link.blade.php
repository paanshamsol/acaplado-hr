@props(['active' => false])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center rounded-lg px-3 py-1.5 text-sm font-semibold text-white bg-white/20 shadow-sm focus:outline-none focus:ring-2 focus:ring-white/60 transition duration-150 ease-in-out'
            : 'inline-flex items-center rounded-lg px-3 py-1.5 text-sm font-medium text-white/85 hover:text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/60 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
