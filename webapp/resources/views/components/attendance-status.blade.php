@props([
    'parts' => [],
    'fallbackStatus' => null,
])

@if (($parts ?? []) === [] || count($parts) === 0)
    @if (filled($fallbackStatus))
        @php
            $statusColor = match ($fallbackStatus) {
                'Bekerja' => 'bg-green-100 text-green-800',
                'Istirahat' => 'bg-yellow-100 text-yellow-800',
                'Pulang' => 'bg-blue-100 text-blue-800',
                'Cuti' => 'bg-sky-100 text-sky-800',
                'Tidak Masuk' => 'bg-red-100 text-red-700',
                default => 'bg-gray-100 text-gray-600',
            };
        @endphp
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">
            {{ $fallbackStatus }}
        </span>
    @else
        <span class="text-xs text-gray-400">—</span>
    @endif
@else
    <div class="space-y-0.5 text-xs leading-snug whitespace-nowrap">
        @foreach ($parts as $part)
            @if (! empty($part['metric']) && filled($part['display'] ?? null))
                <div @class([
                    'font-medium',
                    'text-green-700' => ! empty($part['ok']),
                    'text-red-700' => empty($part['ok']),
                ])>
                    {{ $part['label'] }} = {{ $part['display'] }}
                </div>
            @else
                <div @class([
                    'font-medium',
                    'text-green-700' => ! empty($part['ok']),
                    'text-red-700' => empty($part['ok']),
                ])>
                    {{ $part['label'] }} = {{ ! empty($part['ok']) ? 'OK' : 'Not OK' }}@if (filled($part['display'] ?? null))
                        ({{ ! empty($part['negative']) ? '- ' : '' }}{{ $part['display'] }})
                    @endif
                </div>
            @endif
        @endforeach
    </div>
@endif
