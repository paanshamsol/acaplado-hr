@props(['selected' => null, 'name' => 'year'])

@php
    $current = (int) now()->year;
    $selected = (int) ($selected ?? $current);

    $min = min($current - 3, $selected);
    $max = max($current + 3, $selected);
@endphp

<select name="{{ $name }}"
    onchange="this.form.submit()"
    {{ $attributes->merge(['class' => 'rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500']) }}>
    @foreach (range($min, $max) as $year)
        <option value="{{ $year }}" @selected($year === $selected)>{{ $year }}</option>
    @endforeach
</select>
