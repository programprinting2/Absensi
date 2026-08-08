@props([
    'stats' => [],
    'inset' => false,
])

@php
    $fmtDur = function (int $minutes): string {
        $total = abs($minutes);
        if ($total <= 0) {
            return '';
        }
        $hours = intdiv($total, 60);
        $remain = $total % 60;
        if ($hours > 25) {
            $days = intdiv($hours, 24);
            $hours = $hours % 24;

            return "{$days}d {$hours}h {$remain}m";
        }

        return "{$hours}h {$remain}m";
    };

    $s = array_merge([
        'total' => 0,
        'ok' => 0,
        'not_ok' => 0,
        'tidak_masuk' => 0,
        'terlambat' => 0,
        'istirahat_lebih' => 0,
        'pulang_awal' => 0,
        'jam_kerja_kurang' => 0,
        'lembur' => 0,
        'menit_terlambat' => 0,
        'menit_istirahat_lebih' => 0,
        'menit_pulang_awal' => 0,
        'menit_jam_kerja_kurang' => 0,
        'menit_lembur' => 0,
    ], $stats);
@endphp

<div {{ $attributes->class([
    'rounded-lg overflow-hidden border',
    'border-gray-100 bg-gray-50/60' => $inset,
    'border-gray-200 bg-white' => ! $inset,
]) }}>
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-9 divide-x divide-y xl:divide-y-0 divide-gray-100">
        <div class="px-3 py-2 min-w-0">
            <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500">Total</p>
            <p class="mt-0.5 text-sm font-semibold tabular-nums text-gray-900">{{ $s['total'] }}</p>
        </div>
        <div class="px-3 py-2 min-w-0">
            <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500">OK</p>
            <p class="mt-0.5 text-sm font-semibold tabular-nums text-green-700">{{ $s['ok'] }}</p>
        </div>
        <div class="px-3 py-2 min-w-0">
            <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500">Not OK</p>
            <p class="mt-0.5 text-sm font-semibold tabular-nums text-red-700">{{ $s['not_ok'] }}</p>
        </div>
        <div class="px-3 py-2 min-w-0">
            <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500">Tidak masuk</p>
            <p class="mt-0.5 text-sm font-semibold tabular-nums text-gray-800">{{ $s['tidak_masuk'] }}</p>
        </div>
        <div class="px-3 py-2 min-w-0">
            <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500">Terlambat</p>
            <p class="mt-0.5 text-sm font-semibold tabular-nums text-red-700">
                {{ $s['terlambat'] }}
                @if ($s['menit_terlambat'] > 0)
                    <span class="font-normal text-gray-500">· {{ $fmtDur($s['menit_terlambat']) }}</span>
                @endif
            </p>
        </div>
        <div class="px-3 py-2 min-w-0">
            <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500">Istirahat+</p>
            <p class="mt-0.5 text-sm font-semibold tabular-nums text-amber-800">
                {{ $s['istirahat_lebih'] }}
                @if ($s['menit_istirahat_lebih'] > 0)
                    <span class="font-normal text-gray-500">· {{ $fmtDur($s['menit_istirahat_lebih']) }}</span>
                @endif
            </p>
        </div>
        <div class="px-3 py-2 min-w-0">
            <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500">Pulang awal</p>
            <p class="mt-0.5 text-sm font-semibold tabular-nums text-red-700">
                {{ $s['pulang_awal'] }}
                @if ($s['menit_pulang_awal'] > 0)
                    <span class="font-normal text-gray-500">· {{ $fmtDur($s['menit_pulang_awal']) }}</span>
                @endif
            </p>
        </div>
        <div class="px-3 py-2 min-w-0">
            <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500">Jam kurang</p>
            <p class="mt-0.5 text-sm font-semibold tabular-nums text-red-700">
                {{ $s['jam_kerja_kurang'] }}
                @if ($s['menit_jam_kerja_kurang'] > 0)
                    <span class="font-normal text-gray-500">· {{ $fmtDur($s['menit_jam_kerja_kurang']) }}</span>
                @endif
            </p>
        </div>
        <div class="px-3 py-2 min-w-0">
            <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500">Jam lembur</p>
            <p class="mt-0.5 text-sm font-semibold tabular-nums text-emerald-700">
                {{ $s['lembur'] }}
                @if ($s['menit_lembur'] > 0)
                    <span class="font-normal text-gray-500">· {{ $fmtDur($s['menit_lembur']) }}</span>
                @endif
            </p>
        </div>
    </div>
</div>
