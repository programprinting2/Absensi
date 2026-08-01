<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pengaturan Device</h2>
            <a href="{{ route('settings.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Kembali</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                <p class="text-sm text-gray-500 mb-6">
                    Device: <span class="font-medium text-gray-800">{{ $device->device_code }}</span>
                </p>

                <form method="POST" action="{{ route('settings.devices.update', $device) }}" class="space-y-8">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="name" value="Nama Device (LCD & dashboard)" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                                      value="{{ old('name', $device->name) }}" required maxlength="28" />
                        <p class="mt-1 text-xs text-gray-400">Tampil di header LCD dan list Settings.</p>
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    @foreach ($modes as $modeKey => $modeLabel)
                        @php
                            $modeConfig = $device->lcd_config['modes'][$modeKey] ?? [];
                            $isBreakStart = $modeKey === 'break_start';
                        @endphp

                        <fieldset class="border border-gray-200 rounded-lg p-4 space-y-4">
                            <legend class="px-2 text-sm font-semibold text-gray-800">Mode {{ $modeLabel }}</legend>

                            <div>
                                <x-input-label :for="'header_'.$modeKey" value="Pesan atas" />
                                <x-text-input :id="'header_'.$modeKey"
                                              :name="'lcd_config[modes]['.$modeKey.'][header]'"
                                              type="text" class="mt-1 block w-full"
                                              :value="old('lcd_config.modes.'.$modeKey.'.header', $modeConfig['header'] ?? '')"
                                              required maxlength="28" />
                                <x-input-error :messages="$errors->get('lcd_config.modes.'.$modeKey.'.header')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label :for="'ok_'.$modeKey" value="Indikator hijau (on time)" />
                                <x-text-input :id="'ok_'.$modeKey"
                                              :name="'lcd_config[modes]['.$modeKey.'][indicator_ok]'"
                                              type="text" class="mt-1 block w-full"
                                              :value="old('lcd_config.modes.'.$modeKey.'.indicator_ok', $modeConfig['indicator_ok'] ?? '')"
                                              required maxlength="28" />
                                <x-input-error :messages="$errors->get('lcd_config.modes.'.$modeKey.'.indicator_ok')" class="mt-2" />
                            </div>

                            @if ($isBreakStart)
                                <div>
                                    <x-input-label :for="'info_'.$modeKey" value="Prefix indikator kuning (info)" />
                                    <x-text-input :id="'info_'.$modeKey"
                                                  :name="'lcd_config[modes]['.$modeKey.'][indicator_info_prefix]'"
                                                  type="text" class="mt-1 block w-full"
                                                  :value="old('lcd_config.modes.'.$modeKey.'.indicator_info_prefix', $modeConfig['indicator_info_prefix'] ?? '')"
                                                  required maxlength="28" />
                                    <p class="mt-1 text-xs text-gray-400">Di LCD: "KEMBALI SEBELUM 10:00" — waktu dihitung otomatis.</p>
                                    <x-input-error :messages="$errors->get('lcd_config.modes.'.$modeKey.'.indicator_info_prefix')" class="mt-2" />
                                </div>
                            @else
                                <div>
                                    <x-input-label :for="'warn_'.$modeKey" value="Prefix indikator merah (peringatan)" />
                                    <x-text-input :id="'warn_'.$modeKey"
                                                  :name="'lcd_config[modes]['.$modeKey.'][indicator_warn_prefix]'"
                                                  type="text" class="mt-1 block w-full"
                                                  :value="old('lcd_config.modes.'.$modeKey.'.indicator_warn_prefix', $modeConfig['indicator_warn_prefix'] ?? '')"
                                                  required maxlength="28" />
                                    <p class="mt-1 text-xs text-gray-400">Di LCD: "TERLAMBAT 1 jam 15 menit" — durasi dihitung otomatis.</p>
                                    <x-input-error :messages="$errors->get('lcd_config.modes.'.$modeKey.'.indicator_warn_prefix')" class="mt-2" />
                                </div>
                            @endif
                        </fieldset>
                    @endforeach

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('settings.index') }}" class="text-sm text-gray-600 hover:text-gray-900 px-4 py-2">Batal</a>
                        <x-primary-button>Simpan</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
