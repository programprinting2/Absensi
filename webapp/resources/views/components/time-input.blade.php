@props([
    'value' => '',
])

@php
    $initial = substr((string) $value, 0, 5);
@endphp

<div x-data="timeInput(@js($initial))" class="contents">
    <input
        type="text"
        inputmode="numeric"
        autocomplete="off"
        maxlength="5"
        placeholder="08:00"
        pattern="^([01][0-9]|2[0-3]):[0-5][0-9]$"
        title="Format 24 jam, contoh: 08:00 atau 17:00"
        x-model="value"
        @input="onInput($event)"
        @blur="onBlur()"
        {{ $attributes->merge([
            'class' => 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm font-mono tabular-nums',
        ]) }}
    />
</div>
