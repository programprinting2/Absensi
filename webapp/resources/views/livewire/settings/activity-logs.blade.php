<?php

use App\Models\ActivityLog;
use App\Services\ActivityLogger;
use App\Support\AppTimezone;
use App\Support\Toast;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $level = 'all';

    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingLevel(): void
    {
        $this->resetPage();
    }

    public function clearLogs(): void
    {
        $level = $this->level !== 'all' ? $this->level : null;
        $deleted = ActivityLogger::clear($level);

        ActivityLogger::warning(
            $level
                ? "Log aktivitas level {$level} dibersihkan ({$deleted} baris)."
                : "Semua log aktivitas dibersihkan ({$deleted} baris).",
            'activity_logs.clear',
            ['deleted' => $deleted, 'level' => $level],
        );

        Toast::success(
            $deleted > 0
                ? "Berhasil menghapus {$deleted} log."
                : 'Tidak ada log yang dihapus.',
            $this,
        );

        $this->resetPage();
    }

    public function with(): array
    {
        $query = ActivityLog::query()->orderByDesc('created_at')->orderByDesc('id');

        if ($this->level !== 'all') {
            $query->where('level', $this->level);
        }

        if (filled($this->search)) {
            $term = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('description', 'ilike', $term)
                    ->orWhere('user_name', 'ilike', $term)
                    ->orWhere('action', 'ilike', $term)
                    ->orWhere('ip_address', 'ilike', $term);
            });
        }

        return [
            'logs' => $query->paginate(30),
            'counts' => [
                'all' => ActivityLog::query()->count(),
                'normal' => ActivityLog::query()->where('level', ActivityLog::LEVEL_NORMAL)->count(),
                'medium' => ActivityLog::query()->where('level', ActivityLog::LEVEL_MEDIUM)->count(),
                'warning' => ActivityLog::query()->where('level', ActivityLog::LEVEL_WARNING)->count(),
            ],
        ];
    }
}; ?>

<div class="flex flex-col h-full min-h-0">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-4 shrink-0">
        <div>
            <h3 class="text-base font-semibold text-gray-900">Log Aktivitas</h3>
            <p class="text-sm text-gray-500 mt-0.5">Rekaman aktivitas sistem (semacam CCTV) beserta level, keterangan, tanggal &amp; waktu.</p>
        </div>
        <button
            type="button"
            wire:click="clearLogs"
            wire:confirm="{{ $level === 'all' ? 'Hapus SEMUA log aktivitas? Tindakan ini tidak bisa dibatalkan.' : 'Hapus semua log level '.ucfirst($level).'? Tindakan ini tidak bisa dibatalkan.' }}"
            class="inline-flex items-center px-3 py-2 rounded-md text-sm font-semibold text-red-700 bg-white border border-red-200 hover:bg-red-50"
        >
            Clear Log{{ $level !== 'all' ? ' ('.ucfirst($level).')' : '' }}
        </button>
    </div>

    <div class="flex flex-wrap items-center gap-2 mb-4 shrink-0">
        @foreach (['all' => 'Semua', 'normal' => 'Normal', 'medium' => 'Medium', 'warning' => 'Warning'] as $key => $label)
            <button
                type="button"
                wire:click="$set('level', '{{ $key }}')"
                @class([
                    'px-3 py-1.5 text-sm rounded-md border transition',
                    $level === $key ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
                ])
            >
                {{ $label }}
                <span class="opacity-70">({{ number_format($counts[$key] ?? 0) }})</span>
            </button>
        @endforeach

        <div class="ml-auto w-full sm:w-64">
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Cari keterangan / user / aksi…"
                class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
        </div>
    </div>

    <div class="flex-1 min-h-0 overflow-auto border border-gray-200 rounded-lg bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 sticky top-0 z-10">
                <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-3 whitespace-nowrap">Tanggal &amp; Waktu</th>
                    <th class="px-4 py-3">Level</th>
                    <th class="px-4 py-3">User</th>
                    <th class="px-4 py-3">Keterangan</th>
                    <th class="px-4 py-3">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($logs as $log)
                    @php
                        $badge = match ($log->level) {
                            'warning' => 'bg-red-50 text-red-700 border-red-100',
                            'medium' => 'bg-amber-50 text-amber-800 border-amber-100',
                            default => 'bg-gray-50 text-gray-700 border-gray-100',
                        };
                        $when = $log->created_at
                            ? $log->created_at->timezone(AppTimezone::display())->locale('id')
                            : null;
                    @endphp
                    <tr wire:key="alog-{{ $log->id }}" class="hover:bg-gray-50/80 align-top">
                        <td class="px-4 py-3 whitespace-nowrap text-gray-700 tabular-nums">
                            @if ($when)
                                <div class="font-medium">{{ $when->translatedFormat('j M Y') }}</div>
                                <div class="text-xs text-gray-500">{{ $when->format('H:i:s') }}</div>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border {{ $badge }}">
                                {{ $log->levelLabel() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-gray-700">
                            {{ $log->user_name ?: '—' }}
                        </td>
                        <td class="px-4 py-3 text-gray-800">
                            <p>{{ $log->description }}</p>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500 tabular-nums">
                            {{ $log->ip_address ?: '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-gray-500">
                            Belum ada log aktivitas{{ filled($search) || $level !== 'all' ? ' untuk filter ini' : '' }}.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3 shrink-0">
        {{ $logs->links() }}
    </div>
</div>
