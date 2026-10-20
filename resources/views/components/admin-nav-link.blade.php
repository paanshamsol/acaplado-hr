@props(['active' => false, 'icon' => null])

@php
$classes = ($active ?? false)
            ? 'group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-semibold text-white bg-[var(--brand)] shadow-sm'
            : 'group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-[var(--brand)] transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    @if ($icon)
        <x-icon :name="$icon"
                class="h-5 w-5 shrink-0 {{ ($active ?? false) ? 'text-white' : 'text-gray-400 group-hover:text-[var(--brand)]' }}" />
    @endif
    <span class="truncate">{{ $slot }}</span>
</a>
