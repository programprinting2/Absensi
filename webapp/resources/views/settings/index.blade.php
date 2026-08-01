<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Settings</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-green-50 text-green-800 text-sm px-4 py-3 rounded-md border border-green-200">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-4">Perangkat ESP32</h3>

                @if ($devices->isEmpty())
                    <p class="text-sm text-gray-500">Belum ada device terdaftar.</p>
                @else
                    <div class="space-y-4">
                        @foreach ($devices as $entry)
                            @php
                                $device = $entry['device'];
                            @endphp
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex items-start gap-3">
                                    <span class="mt-1.5 h-3 w-3 shrink-0 rounded-full {{ $entry['isOnline'] ? 'bg-green-500' : 'bg-red-500' }}"></span>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-gray-800 truncate">
                                            {{ $device->name }}
                                            <span class="text-sm font-normal text-gray-400">({{ $device->device_code }})</span>
                                        </p>
                                        <p class="text-sm text-gray-500 mt-0.5">
                                            @if ($device->last_seen_at)
                                                Terakhir terhubung {{ $device->last_seen_at->diffForHumans() }}
                                            @else
                                                ESP32 belum pernah terhubung
                                            @endif
                                        </p>
                                        <div class="mt-3">
                                            <div class="flex justify-between text-xs text-gray-500 mb-1">
                                                <span>Kapasitas sensor</span>
                                                <span>{{ $entry['usedSlots'] }} / {{ $entry['capacity'] ?? '?' }}</span>
                                            </div>
                                            <div class="w-full bg-gray-200 rounded-full h-2">
                                                <div class="bg-indigo-600 h-2 rounded-full" style="width: {{ $entry['percent'] }}%"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $entry['isOnline'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                            {{ $entry['isOnline'] ? 'ONLINE' : 'OFFLINE' }}
                                        </span>
                                        <div class="flex items-center gap-1">
                                            <form method="POST"
                                                  action="{{ route('settings.devices.wifi.start', $device) }}"
                                                  target="_blank"
                                                  class="inline">
                                                @csrf
                                                <button type="submit"
                                                        class="inline-flex items-center justify-center w-9 h-9 rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50 hover:text-indigo-600"
                                                        title="Setup WiFi device (buka tab baru)">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                                                    </svg>
                                                </button>
                                            </form>
                                            <a href="{{ route('settings.devices.edit', $device) }}"
                                               class="inline-flex items-center justify-center w-9 h-9 rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50 hover:text-gray-900"
                                               title="Pengaturan device">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
