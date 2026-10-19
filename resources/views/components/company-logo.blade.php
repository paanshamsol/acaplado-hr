@php($company = \App\Models\CompanySetting::current())
@if ($url = $company->logoUrl())
    <img src="{{ $url }}" alt="{{ $company->name ?? config('app.name') }}"
        {{ $attributes->merge(['class' => 'object-contain']) }}>
@else
    <x-application-logo {{ $attributes }} />
@endif
