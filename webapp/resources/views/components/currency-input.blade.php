@props([
    'name' => null,
    'value' => 0,
    'wire' => null,
])

@php
    $initial = (int) round((float) ($value ?: 0));
@endphp

<div
    @if ($wire) wire:key="currency-field-{{ $wire }}" @endif
    x-data="currencyField({{ $initial }}, {{ $wire ? \Illuminate\Support\Js::from($wire) : 'null' }})"
    class="contents"
>
    <input
        type="text"
        inputmode="numeric"
        autocomplete="off"
        x-ref="input"
        :value="display"
        @input="onInput($event)"
        @blur="onBlur()"
        {{ $attributes->merge([
            'class' => 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-right tabular-nums',
        ]) }}
    />
    @if ($name)
        <input type="hidden" name="{{ $name }}" :value="raw" />
    @endif
</div>
