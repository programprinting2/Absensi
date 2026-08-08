<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Settings</h2>
    </x-slot>

    @php
        $scheduleErrorKeys = ['clock_in_time', 'clock_out_time', 'break_duration_minutes', 'work_duration_hours', 'late_after_time'];
        $roleErrorKeys = ['home_menu_key', 'menus', 'menus.*', 'email', 'password', 'role', 'employee_id'];
        $pph21ErrorKeys = ['enable_pph21', 'pph21_method'];
        $slipErrorKeys = [
            'slip_paper', 'slip_margin_top_mm', 'slip_margin_right_mm', 'slip_margin_bottom_mm', 'slip_margin_left_mm',
            'slip_fit_to_width', 'slip_font', 'slip_font_scale', 'slip_width_mm', 'slip_height_mm',
        ];
        if (request()->routeIs('work-schedule.*') || session('settings_tab') === 'jam-kerja' || $errors->hasAny($scheduleErrorKeys)) {
            $resolvedTab = 'jam-kerja';
        } elseif ($errors->hasAny($roleErrorKeys) || request()->routeIs('settings.roles.*') || request()->routeIs('settings.users.*') || session('settings_tab') === 'hak-akses') {
            $resolvedTab = 'hak-akses';
        } elseif ($errors->hasAny($pph21ErrorKeys) || session('settings_tab') === 'pph21') {
            $resolvedTab = 'pph21';
        } elseif ($errors->hasAny($slipErrorKeys) || session('settings_tab') === 'slip') {
            $resolvedTab = 'slip';
        } elseif ($errors->any() && ! request()->isMethod('get')) {
            $resolvedTab = session('settings_tab', 'identitas');
        } else {
            $resolvedTab = request('tab', $activeTab ?? 'perangkat');
        }
    @endphp

    <div class="h-[calc(100vh-8rem)] flex flex-col"
         x-data="{ activeTab: '{{ $resolvedTab }}' }">
        <div class="flex-1 flex flex-col min-h-0 px-4 sm:px-6 lg:px-8 py-4 space-y-3">
            <div class="bg-white shadow-sm rounded-lg overflow-hidden flex-1 flex flex-col min-h-0">
                {{-- Top Tabs --}}
                <nav class="shrink-0 border-b border-gray-200 px-4 bg-white">
                    <div class="flex gap-1 -mb-px overflow-x-auto">
                        <button type="button"
                                @click="activeTab = 'perangkat'"
                                :class="activeTab === 'perangkat'
                                    ? 'border-[#f7340d] text-[#f7340d]'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                class="inline-flex items-center gap-2 whitespace-nowrap px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                            <svg class="w-[16px] h-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                            </svg>
                            Perangkat
                        </button>
                        <button type="button"
                                @click="activeTab = 'parameter'"
                                :class="activeTab === 'parameter'
                                    ? 'border-[#f7340d] text-[#f7340d]'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                class="inline-flex items-center gap-2 whitespace-nowrap px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                            <svg class="w-[16px] h-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                            </svg>
                            Parameter
                        </button>
                        <button type="button"
                                @click="activeTab = 'jam-kerja'"
                                :class="activeTab === 'jam-kerja'
                                    ? 'border-[#f7340d] text-[#f7340d]'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                class="inline-flex items-center gap-2 whitespace-nowrap px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                            <svg class="w-[16px] h-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Jam Kerja
                        </button>
                        <button type="button"
                                @click="activeTab = 'identitas'"
                                :class="activeTab === 'identitas'
                                    ? 'border-[#f7340d] text-[#f7340d]'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                class="inline-flex items-center gap-2 whitespace-nowrap px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                            <svg class="w-[16px] h-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            Identitas Usaha
                        </button>
                        <button type="button"
                                @click="activeTab = 'hak-akses'"
                                :class="activeTab === 'hak-akses'
                                    ? 'border-[#f7340d] text-[#f7340d]'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                class="inline-flex items-center gap-2 whitespace-nowrap px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                            <svg class="w-[16px] h-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            Hak Akses
                        </button>
                        <button type="button"
                                @click="activeTab = 'pph21'"
                                :class="activeTab === 'pph21'
                                    ? 'border-[#f7340d] text-[#f7340d]'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                class="inline-flex items-center gap-2 whitespace-nowrap px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                            <svg class="w-[16px] h-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                            </svg>
                            PPh 21
                        </button>
                        <button type="button"
                                @click="activeTab = 'slip'"
                                :class="activeTab === 'slip'
                                    ? 'border-[#f7340d] text-[#f7340d]'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                class="inline-flex items-center gap-2 whitespace-nowrap px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                            <svg class="w-[16px] h-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            Cetak Slip
                        </button>
                        <a href="{{ route('tools.database') }}"
                           @class([
                               'no-underline inline-flex items-center gap-2 whitespace-nowrap px-4 py-3 text-sm font-medium border-b-2 transition-colors',
                               request()->routeIs('tools.database*', 'tools.google-drive*')
                                   ? 'border-[#f7340d] text-[#f7340d]'
                                   : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                           ])>
                            <svg class="w-[16px] h-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                            </svg>
                            Database
                        </a>
                    </div>
                </nav>

                {{-- Main Content --}}
                <div class="flex-1 min-h-0 overflow-hidden flex flex-col">
                    {{-- Tab: Perangkat --}}
                    <div x-show="activeTab === 'perangkat'" x-cloak class="flex-1 overflow-y-auto p-6">
                        <div class="mb-5">
                            <h3 class="text-base font-semibold text-gray-900">Perangkat ESP32</h3>
                            <p class="text-sm text-gray-500 mt-0.5">Kelola perangkat absensi yang terhubung.</p>
                        </div>

                        @if ($devices->isEmpty())
                            <div class="rounded-lg border border-dashed border-gray-300 px-6 py-12 text-center">
                                <p class="text-sm text-gray-500">Belum ada device terdaftar.</p>
                            </div>
                        @else
                            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                                @foreach ($devices as $entry)
                                    @php
                                        $device = $entry['device'];
                                    @endphp
                                    <div class="border border-gray-200 rounded-lg p-4 hover:border-gray-300 transition">
                                        <div class="flex items-start gap-3">
                                            <span class="mt-1.5 h-3 w-3 shrink-0 rounded-full {{ $entry['isOnline'] ? 'bg-green-500' : 'bg-red-500' }}"></span>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div class="min-w-0">
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
                                                    </div>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium shrink-0 {{ $entry['isOnline'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                                        {{ $entry['isOnline'] ? 'ONLINE' : 'OFFLINE' }}
                                                    </span>
                                                </div>

                                                <div class="mt-3">
                                                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                                                        <span>Kapasitas sensor</span>
                                                        <span>{{ $entry['usedSlots'] }} / {{ $entry['capacity'] ?? '?' }}</span>
                                                    </div>
                                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                                        <div class="bg-indigo-600 h-2 rounded-full" style="width: {{ $entry['percent'] }}%"></div>
                                                    </div>
                                                </div>

                                                <div class="mt-3 flex items-center gap-1">
                                                    <a href="{{ route('settings.devices.wifi.portal', $device) }}"
                                                       class="inline-flex items-center justify-center w-9 h-9 rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50 hover:text-indigo-600"
                                                       title="Buka portal konfigurasi device{{ $device->last_ip ? ' ('.$device->last_ip.')' : ' — IP belum diketahui' }}">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                                                        </svg>
                                                    </a>
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

                    {{-- Tab: Parameter --}}
                    @include('settings._parameter-manager')

                    {{-- Tab: Jam Kerja --}}
                    <div x-show="activeTab === 'jam-kerja'" x-cloak class="flex-1 overflow-y-auto p-6">
                        @include('settings._work-schedules')
                    </div>

                    {{-- Tab: Identitas Usaha --}}
                    <div x-show="activeTab === 'identitas'" x-cloak class="flex-1 overflow-y-auto p-6">
                        <div class="mb-5">
                            <h3 class="text-base font-semibold text-gray-900">Identitas Usaha</h3>
                            <p class="text-sm text-gray-500 mt-0.5">Informasi perusahaan yang akan ditampilkan pada dokumen dan laporan.</p>
                        </div>

                        <form method="POST" action="{{ route('settings.company.update') }}" class="space-y-5 max-w-5xl">
                            @csrf
                            @method('PUT')

                            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                                <div class="md:col-span-2 xl:col-span-3">
                                    <x-input-label for="company_name" value="Nama Perusahaan" />
                                    <x-text-input id="company_name" name="company_name" type="text" class="mt-1 block w-full"
                                        :value="old('company_name', $company->company_name)" placeholder="PT Contoh Sukses Abadi" />
                                    <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
                                </div>

                                <div class="md:col-span-2 xl:col-span-3">
                                    <x-input-label for="trade_name" value="Nama Dagang / Brand" />
                                    <x-text-input id="trade_name" name="trade_name" type="text" class="mt-1 block w-full"
                                        :value="old('trade_name', $company->trade_name)" placeholder="Opsional" />
                                    <x-input-error :messages="$errors->get('trade_name')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="npwp" value="NPWP" />
                                    <x-text-input id="npwp" name="npwp" type="text" maxlength="25" class="mt-1 block w-full"
                                        :value="old('npwp', $company->npwp)" placeholder="00.000.000.0-000.000" />
                                    <x-input-error :messages="$errors->get('npwp')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="nib" value="NIB" />
                                    <x-text-input id="nib" name="nib" type="text" maxlength="30" class="mt-1 block w-full"
                                        :value="old('nib', $company->nib)" placeholder="Nomor Induk Berusaha" />
                                    <x-input-error :messages="$errors->get('nib')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="owner_name" value="Nama Pemilik / Penanggung Jawab" />
                                    <x-text-input id="owner_name" name="owner_name" type="text" class="mt-1 block w-full"
                                        :value="old('owner_name', $company->owner_name)" />
                                    <x-input-error :messages="$errors->get('owner_name')" class="mt-2" />
                                </div>

                                <div class="md:col-span-2 xl:col-span-3">
                                    <x-input-label for="display_timezone" value="Timezone Tampilan" />
                                    <select id="display_timezone" name="display_timezone"
                                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                                        @foreach ($timezoneOptions as $tzValue => $tzLabel)
                                            <option value="{{ $tzValue }}" @selected(old('display_timezone', $company->display_timezone ?: 'Asia/Jakarta') === $tzValue)>
                                                {{ $tzLabel }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-xs text-gray-500">
                                        Absensi disimpan dalam UTC. Pilihan ini mengatur konversi jam yang dilihat di laporan, dashboard, dan jam kerja (mis. WIB).
                                    </p>
                                    <x-input-error :messages="$errors->get('display_timezone')" class="mt-2" />
                                </div>

                                <div class="md:col-span-2 xl:col-span-3">
                                    <x-input-label for="address" value="Alamat" />
                                    <textarea id="address" name="address" rows="2"
                                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"
                                        placeholder="Jl. Contoh No. 123">{{ old('address', $company->address) }}</textarea>
                                    <x-input-error :messages="$errors->get('address')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="city" value="Kota / Kabupaten" />
                                    <x-text-input id="city" name="city" type="text" class="mt-1 block w-full"
                                        :value="old('city', $company->city)" />
                                    <x-input-error :messages="$errors->get('city')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="province" value="Provinsi" />
                                    <x-text-input id="province" name="province" type="text" class="mt-1 block w-full"
                                        :value="old('province', $company->province)" />
                                    <x-input-error :messages="$errors->get('province')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="postal_code" value="Kode Pos" />
                                    <x-text-input id="postal_code" name="postal_code" type="text" maxlength="10" class="mt-1 block w-full"
                                        :value="old('postal_code', $company->postal_code)" />
                                    <x-input-error :messages="$errors->get('postal_code')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="phone" value="Telepon" />
                                    <x-text-input id="phone" name="phone" type="text" maxlength="30" class="mt-1 block w-full"
                                        :value="old('phone', $company->phone)" />
                                    <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="email" value="Email" />
                                    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                                        :value="old('email', $company->email)" />
                                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="website" value="Website" />
                                    <x-text-input id="website" name="website" type="text" class="mt-1 block w-full"
                                        :value="old('website', $company->website)" placeholder="https://" />
                                    <x-input-error :messages="$errors->get('website')" class="mt-2" />
                                </div>
                            </div>

                            <div class="flex justify-end pt-2 border-t border-gray-100">
                                <x-primary-button class="mt-4">Simpan Identitas</x-primary-button>
                            </div>
                        </form>
                    </div>

                    {{-- Tab: Hak Akses --}}
                    <div x-show="activeTab === 'hak-akses'" x-cloak class="flex-1 overflow-y-auto p-6">
                        @include('settings._role-access')
                    </div>

                    {{-- Tab: PPh 21 --}}
                    @include('settings._pph21')

                    @include('settings._slip-print')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
