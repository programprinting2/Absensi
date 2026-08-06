@php
    $hasLogs = filled($row['clock_in'] ?? null)
        || filled($row['break_start'] ?? null)
        || filled($row['break_end'] ?? null)
        || filled($row['clock_out'] ?? null);
@endphp
<tr wire:key="row-{{ $row['employee']->id }}-{{ $row['date'] }}" class="hover:bg-gray-50 {{ $separatorClass ?? '' }} {{ ($row['status'] ?? '') === 'Tidak Masuk' ? 'bg-red-50/40' : '' }}">
    <td class="px-6 py-3 whitespace-nowrap text-gray-700">{{ $row['date_label'] }}</td>
    @if (! empty($showEmployeeColumn))
        <td class="px-6 py-3 whitespace-nowrap font-medium text-gray-900">{{ $row['employee']->full_name }}</td>
    @endif
    @foreach ([
        ['clock_in', $row['clock_in'] ?? null, !empty($row['is_late'])],
        ['break_start', $row['break_start'] ?? null, false],
        ['break_end', $row['break_end'] ?? null, false],
    ] as [$field, $value, $flagged])
        @php $cellKey = $row['employee']->id.'|'.$row['date'].'|'.$field; @endphp
        <td class="px-6 py-3 whitespace-nowrap">
            @if ($editingCell === $cellKey)
                <input
                    type="text"
                    inputmode="numeric"
                    autocomplete="off"
                    maxlength="5"
                    placeholder="08:00"
                    title="Format 24 jam, contoh: 08:00 atau 17:00"
                    wire:model.live="editTimeValue"
                    wire:keydown.enter.prevent="saveCellTime"
                    wire:keydown.escape.prevent="cancelEditCell"
                    wire:blur="saveCellTime"
                    x-init="$nextTick(() => { $el.focus(); $el.select(); })"
                    @input="
                        let d = $el.value.replace(/\D/g, '').slice(0, 4);
                        $el.value = d.length >= 3 ? d.slice(0, 2) + ':' + d.slice(2) : d;
                        $wire.set('editTimeValue', $el.value);
                    "
                    class="w-[7.5rem] rounded-md border-gray-300 text-sm font-mono tabular-nums shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            @else
                <button
                    type="button"
                    wire:click="startEditCell('{{ $row['employee']->id }}', '{{ $row['date'] }}', '{{ $field }}', '{{ $value }}')"
                    class="inline-flex items-center rounded px-1.5 py-0.5 -mx-1.5 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    title="Klik untuk edit jam"
                >
                    <span @class(['font-medium' => $flagged, 'text-red-600' => $flagged, 'text-gray-700' => ! $flagged])>
                        {{ $value ?? '-' }}
                    </span>
                </button>
            @endif
        </td>
    @endforeach
    @php
        $outField = 'clock_out';
        $outValue = $row['clock_out'] ?? null;
        $outFlagged = !empty($row['is_early_out']) || !empty($row['is_short_work']);
        $outCellKey = $row['employee']->id.'|'.$row['date'].'|'.$outField;
    @endphp
    <td class="px-6 py-3 whitespace-nowrap">
        @if ($editingCell === $outCellKey)
            <input
                type="text"
                inputmode="numeric"
                autocomplete="off"
                maxlength="5"
                placeholder="08:00"
                title="Format 24 jam, contoh: 08:00 atau 17:00"
                wire:model.live="editTimeValue"
                wire:keydown.enter.prevent="saveCellTime"
                wire:keydown.escape.prevent="cancelEditCell"
                wire:blur="saveCellTime"
                x-init="$nextTick(() => { $el.focus(); $el.select(); })"
                @input="
                    let d = $el.value.replace(/\D/g, '').slice(0, 4);
                    $el.value = d.length >= 3 ? d.slice(0, 2) + ':' + d.slice(2) : d;
                    $wire.set('editTimeValue', $el.value);
                "
                class="w-[7.5rem] rounded-md border-gray-300 text-sm font-mono tabular-nums shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
        @else
            <button
                type="button"
                wire:click="startEditCell('{{ $row['employee']->id }}', '{{ $row['date'] }}', '{{ $outField }}', '{{ $outValue }}')"
                class="inline-flex items-center rounded px-1.5 py-0.5 -mx-1.5 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                title="Klik untuk edit jam"
            >
                <span @class(['font-medium' => $outFlagged, 'text-red-600' => $outFlagged, 'text-gray-700' => ! $outFlagged])>
                    {{ $outValue ?? '-' }}
                </span>
            </button>
        @endif
    </td>
    <td class="px-6 py-3 align-top">
        <x-attendance-status :parts="$row['status_parts'] ?? []" :fallback-status="$row['status']" />
    </td>
    <td class="px-6 py-3 whitespace-nowrap text-right">
        @if ($hasLogs)
            <button
                type="button"
                wire:click="deleteRow('{{ $row['employee']->id }}', '{{ $row['date'] }}')"
                wire:confirm="Hapus semua absensi {{ $row['employee']->full_name }} pada {{ $row['date_label'] }}?"
                class="inline-flex items-center justify-center p-1.5 rounded-md text-red-600 hover:bg-red-50 hover:text-red-700 transition"
                title="Hapus baris"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                </svg>
            </button>
        @else
            <span class="inline-flex p-1.5 text-gray-300" title="Tidak ada log untuk dihapus">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                </svg>
            </span>
        @endif
    </td>
</tr>
