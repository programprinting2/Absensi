<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Setup WiFi Device</h2>
            <a href="{{ route('settings.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Kembali</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6 space-y-6">
                <p class="text-sm text-gray-500">
                    Device: <span class="font-medium text-gray-800">{{ $device->name }}</span>
                    <span class="text-gray-400">({{ $device->device_code }})</span>
                </p>

                <ol class="list-decimal list-inside space-y-2 text-sm text-gray-700">
                    <li>Klik <strong>Mulai Setup WiFi</strong> supaya device membuka mode konfigurasi.</li>
                    <li>Di HP/laptop, sambungkan ke WiFi <strong>{{ $apName }}</strong> (password: <strong>{{ $apPassword }}</strong>).</li>
                    <li>Klik <strong>Buka WiFi Manager</strong> — halaman setup WiFi device akan terbuka di browser.</li>
                </ol>

                <div class="flex flex-wrap items-center gap-3">
                    <form method="POST" action="{{ route('settings.devices.wifi.start', $device) }}">
                        @csrf
                        <x-primary-button type="submit">
                            Mulai Setup WiFi
                        </x-primary-button>
                    </form>

                    <a href="{{ $portalUrl }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                        </svg>
                        Buka WiFi Manager
                    </a>
                </div>

                <p class="text-xs text-gray-400">
                    Portal WiFi device: <a href="{{ $portalUrl }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline">{{ $portalUrl }}</a>.
                    Hanya bisa diakses saat HP terhubung ke WiFi <strong>{{ $apName }}</strong>.
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
