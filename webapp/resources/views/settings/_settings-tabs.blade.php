@php
    $settingsTabs = [
        ['label' => 'Perangkat', 'url' => route('settings.index', ['tab' => 'perangkat']), 'active' => false],
        ['label' => 'Parameter', 'url' => route('settings.index', ['tab' => 'parameter']), 'active' => false],
        ['label' => 'Jam Kerja', 'url' => route('settings.index', ['tab' => 'jam-kerja']), 'active' => false],
        ['label' => 'Identitas Usaha', 'url' => route('settings.index', ['tab' => 'identitas']), 'active' => false],
        ['label' => 'Hak Akses', 'url' => route('settings.index', ['tab' => 'hak-akses']), 'active' => false],
        ['label' => 'Database', 'url' => route('tools.database'), 'active' => request()->routeIs('tools.database*', 'tools.google-drive*')],
    ];
@endphp

<nav class="shrink-0 border-b border-gray-200 px-4 bg-white">
    <div class="flex gap-1 -mb-px overflow-x-auto">
        @foreach ($settingsTabs as $tab)
            <a href="{{ $tab['url'] }}"
               @class([
                   'no-underline inline-flex items-center gap-2 whitespace-nowrap px-4 py-3 text-sm font-medium border-b-2 transition-colors',
                   $tab['active']
                       ? 'border-[#f7340d] text-[#f7340d]'
                       : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
               ])>
                {{ $tab['label'] }}
            </a>
        @endforeach
    </div>
</nav>
