@props([
    'count' => 0,
])

@php
    $n = max(0, (int) $count);
@endphp

<span {{ $attributes->class('inline-flex items-center gap-0.5 shrink-0 opacity-90 tabular-nums leading-none') }}
    title="{{ $n }} anggota">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
    </svg>
    <span>{{ $n }}</span>
</span>
