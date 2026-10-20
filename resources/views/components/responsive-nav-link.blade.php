@props(['active' => false, 'icon' => null])

@php
$classes = ($active ?? false)
            ? 'group flex items-center gap-3 rounded-lg border-s-4 border-[#33BD08] bg-[#33BD08]/10 px-3 py-2.5 text-sm font-semibold text-green-900 transition duration-150 ease-in-out'
            : 'group flex items-center gap-3 rounded-lg border-s-4 border-transparent px-3 py-2.5 text-sm font-medium text-gray-700 hover:border-gray-300 hover:bg-gray-50 hover:text-gray-900 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    @if ($icon)
        <x-icon :name="$icon"
                class="h-5 w-5 shrink-0 {{ ($active ?? false) ? 'text-[#268E06]' : 'text-gray-400 group-hover:text-[var(--brand)]' }}" />
    @endif
    <span class="flex flex-1 items-center justify-between gap-2">{{ $slot }}</span>
</a>
