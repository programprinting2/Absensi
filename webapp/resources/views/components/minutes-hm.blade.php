@props([
    'minutes' => 0,
    'suffix' => '',
])

@php
    $total = abs((int) $minutes);
    $days = 0;
    $hours = intdiv($total, 60);
    $remain = $total % 60;

    // Lebih dari 25 jam → tampilkan hari
    if ($hours > 25) {
        $days = intdiv($hours, 24);
        $hours = $hours % 24;
    }

    $hm = $hours.' h : '.$remain.' m';
    $label = $days > 0 ? $days.' d, '.$hm : $hm;
@endphp

<div {{ $attributes->class(['leading-tight']) }}>
    <span>{{ $total }} m{{ $suffix }}</span>
    <span class="block text-[11px] text-gray-400">{{ $label }}</span>
</div>
