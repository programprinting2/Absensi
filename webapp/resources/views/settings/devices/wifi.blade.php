<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Konfigurasi Device</h2>
            <a href="{{ route('settings.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Kembali ke Settings</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6 space-y-6">
                <p class="text-sm text-gray-500">
                    Device: <span class="font-medium text-gray-800">{{ $device->name }}</span>
                    <span class="text-gray-400">({{ $device->device_code }})</span>
                </p>

                @if (filled($device->last_ip))
                    <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                        Device terdeteksi di jaringan kantor. Klik tombol di bawah untuk membuka portal konfigurasi.
                    </div>

                    <a href="http://{{ $device->last_ip }}/"
                       class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 transition">
                        Buka http://{{ $device->last_ip }}/
                    </a>
                @else
                    <div class="rounded-md bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900 space-y-2">
                        <p><strong>IP device belum diketahui</strong> — heartbeat belum sampai ke Laravel (device OFFLINE).</p>
                        <p>Lakukan salah satu cara berikut:</p>
                    </div>

                    <ol class="list-decimal list-inside space-y-2 text-sm text-gray-700">
                        <li>Di keypad ESP32 tekan <strong>*</strong> lalu <strong>2</strong> → catat IP di LCD.</li>
                        <li>Buka di browser PC: <strong>http://&lt;IP-device&gt;/</strong></li>
                        <li>Atau tekan <strong>*</strong> lalu <strong>1</strong> → LCD menampilkan alamat portal.</li>
                        <li>Pastikan <strong>Server URL</strong> = <code class="text-xs bg-gray-100 px-1 rounded">{{ rtrim(config('app.url'), '/') }}</code></li>
                    </ol>

                    <div class="rounded-md bg-gray-50 border border-gray-200 px-4 py-3 text-sm text-gray-700">
                        <p class="font-medium text-gray-800 mb-1">Fallback: mode Access Point</p>
                        <p class="text-xs text-gray-500 mb-3">Hanya jika device belum connect WiFi kantor.</p>
                        <form method="POST" action="{{ route('settings.devices.wifi.start', $device) }}">
                            @csrf
                            <x-primary-button type="submit">
                                Mulai Setup WiFi (AP Absensi-Setup)
                            </x-primary-button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
