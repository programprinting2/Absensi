@props([
    'apiBase',
    'storeKey',
    'placeholder' => 'Pilih atau tambah...',
    'createDefaults' => [],
])

@php
    $defaults = $createDefaults;
@endphp

<div
    x-data="masterTypeAutocomplete({
        selectedId: '',
        options: [],
        apiBase: @js($apiBase),
        storeKey: @js($storeKey),
        csrf: @js(csrf_token()),
        createDefaults: {{ \Illuminate\Support\Js::from($defaults) }},
        canCrud: true,
    })"
    x-modelable="selectedId"
    {{ $attributes->class(['relative']) }}
    @mousedown.outside="closeDropdown($event)"
>
    <div class="relative">
        <input
            type="text"
            x-ref="input"
            x-model="query"
            @focus="openDropdown()"
            @click="openDropdown()"
            @input="onInput()"
            @keydown="onKeydown($event)"
            autocomplete="off"
            autocorrect="off"
            autocapitalize="off"
            spellcheck="false"
            data-lpignore="true"
            data-1p-ignore="true"
            data-form-type="other"
            placeholder="{{ $placeholder }}"
            class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm pr-16"
        />
        <div class="absolute inset-y-0 right-0 flex items-center pr-1">
            <button type="button" x-show="query.length" x-cloak @mousedown.prevent="clearQuery()"
                    class="p-1.5 text-gray-400 hover:text-gray-600" title="Hapus">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            <button type="button" @mousedown.prevent="openDropdown()"
                    class="p-1.5 text-gray-400 hover:text-gray-600" title="Cari">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
            </button>
        </div>
    </div>

    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            data-ac-menu
            :style="menuStyle"
            class="rounded-md border border-gray-200 bg-white shadow-lg overflow-hidden"
        >
            <div x-show="filtered.length > 0" class="max-h-60 overflow-auto py-1">
                <template x-for="(option, index) in filtered" :key="(option.id || option.value) + '-' + index">
                    <div class="px-2 py-1">
                        <div x-show="editingId !== option.id"
                             class="flex items-center gap-2 rounded-md border border-gray-200 bg-white px-2 py-2 hover:border-gray-300"
                             :class="highlighted === index ? 'ring-1 ring-indigo-300 border-indigo-300' : ''">
                            <button type="button"
                                    @mousedown.prevent="selectOption(option)"
                                    class="flex-1 text-left text-sm font-medium text-gray-800 truncate"
                                    x-text="option.label || option.name"></button>

                            <div class="flex items-center gap-1.5 shrink-0" x-show="canCrud && option.id">
                                <button type="button"
                                        @mousedown.prevent="startEdit(option)"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-md border border-indigo-200 text-indigo-600 hover:bg-indigo-50"
                                        title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <button type="button"
                                        @mousedown.prevent="deleteOption(option)"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-md border border-gray-200 text-gray-500 hover:bg-red-50 hover:text-red-600 hover:border-red-200"
                                        title="Hapus">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>

                        <div x-show="editingId === option.id" x-cloak
                             class="flex items-center gap-2 rounded-md border border-indigo-200 bg-white px-2 py-2"
                             @mousedown.stop>
                            <input type="text"
                                   x-model="editValue"
                                   @keydown.enter.prevent="saveEdit(option)"
                                   @keydown.escape.prevent="cancelEdit()"
                                   class="flex-1 text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 py-1.5" />
                            <button type="button" @mousedown.prevent="saveEdit(option)"
                                    class="px-2.5 py-1.5 text-xs font-semibold rounded-md bg-gray-800 text-white hover:bg-gray-700">Simpan</button>
                            <button type="button" @mousedown.prevent="cancelEdit()"
                                    class="px-2.5 py-1.5 text-xs font-semibold rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50">Batal</button>
                        </div>
                    </div>
                </template>
            </div>

            <div x-show="filtered.length === 0" class="px-3 py-4 text-center">
                <p class="text-sm text-gray-400 mb-2">Tidak ada data ditemukan</p>
                <button type="button"
                        x-show="canCrud && query.trim().length > 0"
                        x-cloak
                        @mousedown.prevent="createOption()"
                        class="inline-flex items-center gap-1 text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                    + tambahkan data
                </button>
                <p x-show="!query.trim().length" x-cloak class="text-xs text-gray-400">Ketik untuk mencari atau menambah data.</p>
            </div>
        </div>
    </template>
</div>
