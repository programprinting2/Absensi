@php
    $label = $label ?? 'Print';
    $iconOnly = $iconOnly ?? false;
    $btnClass = $btnClass ?? ($iconOnly
        ? 'inline-flex items-center justify-center w-9 h-9 rounded-md text-[#f7340d] hover:bg-orange-50 hover:text-[#d42c0a]'
        : 'text-sm text-[#f7340d] hover:text-[#d42c0a] font-medium inline-flex items-center gap-1');
    $requireSelected = $requireSelected ?? false;
    $selectionRoot = $selectionRoot ?? null;
@endphp
<button
    type="button"
    title="{{ $label }}"
    aria-label="{{ $label }}"
    @click.stop="$dispatch('open-slip-print', {
        baseUrl: @js($baseUrl),
        requireSelected: @js($requireSelected),
        selectionRoot: @js($selectionRoot),
    })"
    class="{{ $btnClass }}"
>
    @unless ($iconOnly)
        <span>{{ $label }}</span>
    @endunless
    <svg class="{{ $iconOnly ? 'w-5 h-5' : 'w-3.5 h-3.5 opacity-70' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
    </svg>
</button>
