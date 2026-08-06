@props(['active'])

@php
$classes = ($active ?? false)
            ? 'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-[#f7340d] bg-orange-50 transition duration-150 ease-in-out'
            : 'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
