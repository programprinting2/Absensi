@props(['value', 'tooltip', 'flagged' => false])

@if ($flagged)
    <span class="relative inline-block group cursor-default">
        <span class="text-red-600 font-medium">{{ $value }}</span>
        <span class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 hidden group-hover:block whitespace-nowrap rounded bg-gray-800 px-2 py-1 text-xs text-white shadow-lg z-10">
            {{ $tooltip }}
            <span class="absolute top-full left-1/2 -translate-x-1/2 -mt-px border-4 border-transparent border-t-gray-800"></span>
        </span>
    </span>
@else
    <span class="text-gray-700">{{ $value ?? '-' }}</span>
@endif
