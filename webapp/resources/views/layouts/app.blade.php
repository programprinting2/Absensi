<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Styles -->
        @livewireStyles
        @vite(['resources/css/app.css'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100 flex">
            <livewire:layout.navigation />

            <div class="flex-1 flex flex-col min-w-0 lg:ml-64">
                {{-- Fixed top bar, sejajar dengan logo sidebar --}}
                <header
                    class="sticky top-0 z-40 h-16 bg-white border-b border-gray-200 shrink-0 flex items-center px-4 sm:px-6 lg:px-8 gap-4"
                    x-data="{ now: new Date({{ now()->timestamp * 1000 }}) }"
                    x-init="setInterval(() => now = new Date(now.getTime() + 1000), 1000)"
                >
                    {{-- Mobile menu --}}
                    <button type="button" @click="$dispatch('toggle-sidebar')" class="lg:hidden text-gray-500 hover:text-gray-700 focus:outline-none shrink-0">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <a href="{{ route(auth()->user()?->homeRouteName() ?? 'dashboard') }}" class="lg:hidden shrink-0">
                        <x-application-logo class="h-8 w-auto" />
                    </a>

                    <div class="flex-1 flex items-center justify-between gap-4 min-w-0">
                        <div class="flex items-center gap-2 min-w-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                            </svg>
                            <span class="text-xs text-gray-500 uppercase tracking-wider shrink-0 hidden sm:inline">Tanggal</span>
                            <span class="text-sm font-semibold text-gray-800 truncate"
                                  x-text="now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })"></span>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-xs text-gray-500 uppercase tracking-wider hidden sm:inline">Waktu Server</span>
                            <span class="text-sm sm:text-base font-mono font-semibold text-gray-800 tabular-nums"
                                  x-text="now.toLocaleTimeString('id-ID', { hour12: false })"></span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                            </svg>

                            <button
                                type="button"
                                title="Refresh konten"
                                aria-label="Refresh konten"
                                class="ml-1 inline-flex items-center justify-center h-9 w-9 rounded-lg text-gray-500 hover:text-gray-800 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-50"
                                x-data="{ loading: false }"
                                x-bind:disabled="loading"
                                @click="
                                    const root = document.querySelector('main [wire\\:id]');
                                    if (!root || !window.Livewire) return;
                                    const component = Livewire.find(root.getAttribute('wire:id'));
                                    if (!component) return;
                                    loading = true;
                                    const finish = () => { loading = false; };
                                    const unhook = Livewire.hook('commit', ({ component: c, succeed }) => {
                                        if (c.id !== component.id) return;
                                        succeed(() => {
                                            finish();
                                            unhook();
                                        });
                                    });
                                    component.$refresh();
                                    setTimeout(() => { try { unhook(); } catch (e) {} finish(); }, 10000);
                                "
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" :class="loading && 'animate-spin'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </header>

                {{-- Page Heading --}}
                @if (isset($header))
                    <div class="bg-white border-b border-gray-200 shrink-0">
                        <div class="py-3 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </div>
                @endif

                {{-- Page Content --}}
                <main class="flex-1 min-h-0 flex flex-col">
                    {{ $slot }}
                </main>
            </div>
        </div>

        <x-toast-hub />
        <x-dialog-hub />

        @stack('scripts')
        {{-- Config dulu, baru app.js + Livewire.start() --}}
        @livewireScriptConfig
        @vite(['resources/js/app.js'])
    </body>
</html>
