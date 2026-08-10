<?php

use App\Models\Device;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\ParameterService;
use App\Services\ShiftResolver;
use App\Support\AppTimezone;
use App\Support\Toast;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public bool $showModal = false;
    public ?string $editingEmployeeId = null;

    /** Password hasil reset (sekali tampil). */
    public ?string $resetPasswordPlain = null;

    public string $full_name = '';
    public string $nik = '';
    public string $phone = '';
    public string $address = '';
    public string $position = '';
    public string $department = '';
    public string $join_date = '';
    public string $npwp = '';
    public string $bpjs_kes = '';
    public string $bpjs_tk = '';
    public string $bank_name = '';
    public string $bank_account = '';
    public string $bank_holder = '';
    public string $ptkp_status = 'TK/0';
    public bool $is_active = true;
    public string $pin = '';
    public string $pin_confirmation = '';

    public function openCreate(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->editingEmployeeId = null;
        $this->showModal = true;
        $this->dispatch('employee-modal-opened');
    }

    public function openEdit(string $id): void
    {
        $this->resetValidation();
        $employee = Employee::findOrFail($id);
        $this->editingEmployeeId = $id;
        $this->full_name = $employee->full_name ?? '';
        $this->nik = $employee->nik ?? '';
        $this->phone = $employee->phone ?? '';
        $this->address = $employee->address ?? '';
        $this->position = $employee->position ?? '';
        $this->department = $employee->department ?? '';
        $this->join_date = $employee->join_date?->format('Y-m-d') ?? '';
        $this->npwp = $employee->npwp ?? '';
        $this->bpjs_kes = $employee->bpjs_kes ?? '';
        $this->bpjs_tk = $employee->bpjs_tk ?? '';
        $this->bank_name = $employee->bank_name ?? '';
        $this->bank_account = $employee->bank_account ?? '';
        $this->bank_holder = $employee->bank_holder ?? '';
        $this->ptkp_status = $employee->ptkp_status ?? 'TK/0';
        $this->is_active = (bool) $employee->is_active;
        $this->pin = '';
        $this->pin_confirmation = '';
        $this->showModal = true;
        $this->dispatch('employee-modal-opened');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetPasswordPlain = null;
        $this->resetValidation();
    }

    public function save(): void
    {
        $rules = [
            'full_name' => ['required', 'string', 'max:255'],
            'nik' => ['nullable', 'string', 'max:16'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'position' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'join_date' => ['nullable', 'date'],
            'npwp' => ['nullable', 'string', 'max:25'],
            'bpjs_kes' => ['nullable', 'string', 'max:20'],
            'bpjs_tk' => ['nullable', 'string', 'max:20'],
            'bank_name' => ['nullable', 'string', 'max:50'],
            'bank_account' => ['nullable', 'string', 'max:30'],
            'bank_holder' => ['nullable', 'string', 'max:100'],
            'ptkp_status' => ['nullable', 'string', 'max:10'],
        ];

        if ($this->editingEmployeeId) {
            $rules['pin'] = ['nullable', 'digits_between:4,6', 'confirmed'];
            $rules['is_active'] = ['required', 'boolean'];
        }

        $data = $this->validate($rules);

        foreach ([
            'nik', 'phone', 'address', 'position', 'department', 'join_date',
            'npwp', 'bpjs_kes', 'bpjs_tk', 'bank_name', 'bank_account', 'bank_holder', 'ptkp_status', 'pin',
        ] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        if ($this->editingEmployeeId) {
            $employee = Employee::findOrFail($this->editingEmployeeId);
            $employee->fill(collect($data)->except(['pin', 'pin_confirmation', 'is_active'])->toArray());
            $employee->is_active = $data['is_active'];

            if (! empty($data['pin'])) {
                if (empty($employee->pin_salt)) {
                    $employee->pin_salt = bin2hex(random_bytes(8));
                }
                $employee->pin_hash = hash_hmac('sha256', $data['pin'], $employee->pin_salt);
            }

            $employee->save();
            Toast::success('Data karyawan berhasil diperbarui.', $this);
        } else {
            $nextCode = (int) (Employee::max('employee_code') ?? 0) + 1;
            $employee = new Employee(array_merge(
                collect($data)->except(['pin', 'pin_confirmation'])->toArray(),
                [
                    'employee_code' => $nextCode,
                    'is_active' => true,
                    'pin_salt' => bin2hex(random_bytes(8)),
                ]
            ));
            $employee->save();

            // Karyawan baru mulai di jadwal default; penempatan khusus lewat menu Shift Kerja.
            $defaultSchedule = WorkSchedule::active() ?? WorkSchedule::query()->orderBy('created_at')->first();
            if ($defaultSchedule) {
                app(ShiftResolver::class)->assign(
                    $employee,
                    $defaultSchedule,
                    $data['join_date'] ?? AppTimezone::nowDisplay()->toDateString(),
                );
            }

            Toast::success('Karyawan berhasil ditambahkan.', $this);
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function deleteEmployee(): void
    {
        if (! $this->editingEmployeeId) return;

        $employee = Employee::with('fingerprintTemplates')->findOrFail($this->editingEmployeeId);

        \Illuminate\Support\Facades\DB::transaction(function () use ($employee) {
            foreach ($employee->fingerprintTemplates as $template) {
                \App\Models\DeviceCommand::create([
                    'device_id' => $template->device_id,
                    'command_type' => 'delete_fingerprint',
                    'payload' => ['fingerprint_slot_id' => $template->fingerprint_slot_id],
                    'status' => 'pending',
                    'created_by' => auth()->user()?->email,
                ]);
            }
            $employee->attendanceLogs()->delete();
            $employee->delete();
        });

        $this->showModal = false;
        $this->resetForm();
        Toast::success('Karyawan berhasil dihapus.', $this);
    }

    public function resetPassword(): void
    {
        if (! $this->editingEmployeeId) {
            return;
        }

        $this->resetPasswordPlain = null;

        $employee = Employee::with('portalUser')->findOrFail($this->editingEmployeeId);
        $user = $employee->portalUser;

        if (! $user) {
            Toast::error('Karyawan belum punya akun login. Buat lewat tab Akses Portal.', $this);

            return;
        }

        if ($user->employee_id !== $employee->id) {
            Toast::error('Akun portal tidak tertaut ke karyawan ini.', $this);

            return;
        }

        if ($user->isAdmin()) {
            Toast::error('Akun admin tidak bisa direset dari sini. Gunakan Settings → Hak Akses.', $this);

            return;
        }

        $plain = Str::password(12, symbols: false);
        $user->password = $plain;
        $user->save();

        $this->resetPasswordPlain = $plain;
        Toast::success('Password baru dibuat. Salin sekarang — tidak ditampilkan lagi setelah modal ditutup.', $this);
    }

    public function enrollFingerprint(string $deviceId): void
    {
        if (! $this->editingEmployeeId) return;

        $employee = Employee::findOrFail($this->editingEmployeeId);
        $device = Device::findOrFail($deviceId);

        $slotId = \App\Models\FingerprintTemplate::where('device_id', $device->id)->max('fingerprint_slot_id') ?? 0;
        $slotId++;

        \App\Models\DeviceCommand::create([
            'device_id' => $device->id,
            'command_type' => 'enroll_fingerprint',
            'payload' => [
                'employee_id' => $employee->id,
                'fingerprint_slot_id' => $slotId,
            ],
            'status' => 'pending',
            'created_by' => auth()->user()?->email,
        ]);

        Toast::success('Perintah pendaftaran sidik jari dikirim ke sensor.', $this);
    }

    public function deleteFingerprint(string $templateId): void
    {
        $template = \App\Models\FingerprintTemplate::findOrFail($templateId);

        \App\Models\DeviceCommand::create([
            'device_id' => $template->device_id,
            'command_type' => 'delete_fingerprint',
            'payload' => ['fingerprint_slot_id' => $template->fingerprint_slot_id],
            'status' => 'pending',
            'created_by' => auth()->user()?->email,
        ]);

        $template->delete();
        Toast::success('Sidik jari dihapus.', $this);
    }

    private function resetForm(): void
    {
        $this->full_name = '';
        $this->nik = '';
        $this->phone = '';
        $this->address = '';
        $this->position = '';
        $this->department = '';
        $this->join_date = '';
        $this->npwp = '';
        $this->bpjs_kes = '';
        $this->bpjs_tk = '';
        $this->bank_name = '';
        $this->bank_account = '';
        $this->bank_holder = '';
        $this->ptkp_status = 'TK/0';
        $this->is_active = true;
        $this->pin = '';
        $this->pin_confirmation = '';
        $this->editingEmployeeId = null;
        $this->resetPasswordPlain = null;
        $this->resetValidation();
    }

    public function with(): array
    {
        $groups = ParameterService::optionGroups(['JABATAN', 'DEPARTEMEN', 'STATUS PTKP', 'BANK']);

        $employees = Employee::query()
            ->with(['portalUser:id,employee_id,name,email'])
            ->withCount('fingerprintTemplates')
            ->orderBy('employee_code', 'desc')
            ->paginate(20);

        // Fallback: email akun sistem yang namanya sama (jika portal belum ter-link employee_id).
        $userEmailsByName = User::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get(['name', 'email'])
            ->mapWithKeys(function (User $user) {
                $key = mb_strtolower(trim((string) $user->name));

                return $key !== '' ? [$key => $user->email] : [];
            });

        $data = [
            'employees' => $employees,
            'userEmailsByName' => $userEmailsByName,
            'jabatanOptions' => $groups['JABATAN']['options'],
            'departemenOptions' => $groups['DEPARTEMEN']['options'],
            'ptkpOptions' => $groups['STATUS PTKP']['options'],
            'bankOptions' => $groups['BANK']['options'],
            'jabatanParameterId' => $groups['JABATAN']['id'],
            'departemenParameterId' => $groups['DEPARTEMEN']['id'],
            'ptkpParameterId' => $groups['STATUS PTKP']['id'],
            'bankParameterId' => $groups['BANK']['id'],
        ];

        if ($this->showModal && $this->editingEmployeeId) {
            $data['editingEmployee'] = Employee::with([
                'portalUser:id,employee_id,name,email',
                'fingerprintTemplates' => fn ($q) => $q->with('device:id,name'),
                'salaries',
                'activeSalary',
            ])->find($this->editingEmployeeId);
            $data['devices'] = Device::where('is_active', true)->get(['id', 'name']);
            $data['salaryHistory'] = $data['editingEmployee']
                ? $data['editingEmployee']->salaryHistoryTimeline()
                : [];
        } else {
            $data['editingEmployee'] = null;
            $data['devices'] = collect();
            $data['salaryHistory'] = [];
        }

        return $data;
    }
}; ?>

<div
    @flash-status.window="$dispatch('app-toast', { type: 'success', message: $event.detail.message })"
    @salary-saved.window="
        if ($event.detail?.message) {
            $dispatch('app-toast', { type: 'success', message: $event.detail.message });
        }
        if (!$wire.showModal) { $wire.$refresh(); }
    "
>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Manajemen Karyawan</h2>
    </x-slot>

    <div class="h-[calc(100vh-8rem)] flex flex-col">
        <div class="flex-1 flex flex-col min-h-0 px-4 sm:px-6 lg:px-8 py-4 space-y-4">
            <div class="flex justify-end shrink-0">
                <button type="button" wire:click="openCreate"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    Tambah Karyawan
                </button>
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden flex-1 flex flex-col min-h-0">
                <div class="overflow-auto flex-1">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 sticky top-0 z-10">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-6 py-3">ID</th>
                                <th class="px-6 py-3">Nama</th>
                                <th class="px-6 py-3">Jabatan</th>
                                <th class="px-6 py-3">Departemen</th>
                                <th class="px-6 py-3">Sidik Jari</th>
                                <th class="px-6 py-3">PIN</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse ($employees as $employee)
                                <tr wire:key="employee-{{ $employee->id }}" class="hover:bg-gray-50">
                                    <td class="px-6 py-3 whitespace-nowrap text-gray-700">{{ $employee->employee_code }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap">
                                        @php
                                            $accountEmail = $employee->portalUser?->email
                                                ?: ($userEmailsByName[mb_strtolower(trim($employee->full_name))] ?? null);
                                        @endphp
                                        <div class="font-medium text-gray-900">{{ $employee->full_name }}</div>
                                        @if ($accountEmail)
                                            <div class="mt-0.5 text-xs text-gray-500">{{ $accountEmail }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap text-gray-500">{{ $employee->position ?? '-' }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap text-gray-500">{{ $employee->department ?? '-' }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap text-gray-500">
                                        {{ $employee->fingerprint_templates_count }} terdaftar
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap">
                                        @if ($employee->pin_hash)
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-600" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                            </svg>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap">
                                        @if ($employee->is_active)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Aktif</span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Nonaktif</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap text-right">
                                        <button type="button" wire:click="openEdit('{{ $employee->id }}')"
                                           class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                            Edit
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-6 text-center text-gray-500">
                                        Belum ada karyawan terdaftar.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="shrink-0">
                {{ $employees->links() }}
            </div>
        </div>
    </div>

    {{-- Modal selalu di DOM; tampil pakai $wire (satu arah) agar Alpine tidak menulis showModal=false saat remount --}}
    <div
        x-data="{
            activeTab: 'dasar',
            confirmDelete: false,
        }"
        x-on:employee-modal-opened.window="activeTab = 'dasar'; confirmDelete = false"
        x-show="$wire.showModal"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        style="display: none;"
        wire:ignore.self
        wire:key="employee-modal-shell"
    >
        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-gray-500/75 transition-opacity"></div>

        {{-- Dialog --}}
        <div class="flex min-h-full items-center justify-center p-4 sm:p-6 pointer-events-none">
            <div class="relative w-full max-w-3xl max-h-[90vh] bg-white rounded-xl shadow-2xl flex flex-col overflow-hidden pointer-events-auto">
            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-3.5 border-b border-gray-200 bg-gray-50 shrink-0">
                <h2 class="text-lg font-semibold text-gray-900">
                    {{ $editingEmployeeId ? 'Edit Karyawan' : 'Tambah Karyawan' }}
                </h2>
                <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="flex flex-1 min-h-0 overflow-hidden">
                {{-- Left Sidebar Tabs --}}
                <nav class="w-52 flex-shrink-0 bg-gray-50 border-r border-gray-200 py-3 overflow-y-auto">
                    <ul class="space-y-0.5 px-2.5">
                        <li>
                            <button @click="activeTab = 'dasar'" :class="activeTab === 'dasar' ? 'bg-orange-50 text-[#f7340d] border-l-2 border-[#f7340d]' : 'text-gray-600 hover:bg-gray-100 border-l-2 border-transparent'" class="w-full text-left px-3 py-2 text-sm font-medium rounded-r-md transition-colors flex items-center gap-2.5">
                                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                Data Dasar
                            </button>
                        </li>
                        <li>
                            <button @click="activeTab = 'kepegawaian'" :class="activeTab === 'kepegawaian' ? 'bg-orange-50 text-[#f7340d] border-l-2 border-[#f7340d]' : 'text-gray-600 hover:bg-gray-100 border-l-2 border-transparent'" class="w-full text-left px-3 py-2 text-sm font-medium rounded-r-md transition-colors flex items-center gap-2.5">
                                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                Kepegawaian
                            </button>
                        </li>
                        <li>
                            <button @click="activeTab = 'pajak'" :class="activeTab === 'pajak' ? 'bg-orange-50 text-[#f7340d] border-l-2 border-[#f7340d]' : 'text-gray-600 hover:bg-gray-100 border-l-2 border-transparent'" class="w-full text-left px-3 py-2 text-sm font-medium rounded-r-md transition-colors flex items-center gap-2.5">
                                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" /></svg>
                                Perpajakan & BPJS
                            </button>
                        </li>
                        <li>
                            <button @click="activeTab = 'bank'" :class="activeTab === 'bank' ? 'bg-orange-50 text-[#f7340d] border-l-2 border-[#f7340d]' : 'text-gray-600 hover:bg-gray-100 border-l-2 border-transparent'" class="w-full text-left px-3 py-2 text-sm font-medium rounded-r-md transition-colors flex items-center gap-2.5">
                                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                                Rekening Bank
                            </button>
                        </li>
                        @if ($editingEmployeeId)
                        <li class="pt-2 mt-2 border-t border-gray-200">
                            <button @click="activeTab = 'fingerprint'" :class="activeTab === 'fingerprint' ? 'bg-orange-50 text-[#f7340d] border-l-2 border-[#f7340d]' : 'text-gray-600 hover:bg-gray-100 border-l-2 border-transparent'" class="w-full text-left px-3 py-2 text-sm font-medium rounded-r-md transition-colors flex items-center gap-2.5">
                                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4" /></svg>
                                Sidik Jari
                            </button>
                        </li>
                        <li>
                            <button @click="activeTab = 'pin'" :class="activeTab === 'pin' ? 'bg-orange-50 text-[#f7340d] border-l-2 border-[#f7340d]' : 'text-gray-600 hover:bg-gray-100 border-l-2 border-transparent'" class="w-full text-left px-3 py-2 text-sm font-medium rounded-r-md transition-colors flex items-center gap-2.5">
                                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                Pengaturan PIN
                            </button>
                        </li>
                        <li>
                            <button @click="activeTab = 'gaji'" :class="activeTab === 'gaji' ? 'bg-orange-50 text-[#f7340d] border-l-2 border-[#f7340d]' : 'text-gray-600 hover:bg-gray-100 border-l-2 border-transparent'" class="w-full text-left px-3 py-2 text-sm font-medium rounded-r-md transition-colors flex items-center gap-2.5">
                                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                Pengaturan Gaji
                            </button>
                        </li>
                        <li class="pt-2 mt-2 border-t border-gray-200">
                            <button @click="activeTab = 'danger'" :class="activeTab === 'danger' ? 'bg-red-50 text-red-600 border-l-2 border-red-500' : 'text-gray-600 hover:bg-gray-100 border-l-2 border-transparent'" class="w-full text-left px-3 py-2 text-sm font-medium rounded-r-md transition-colors flex items-center gap-2.5">
                                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" /></svg>
                                Zona Berbahaya
                            </button>
                        </li>
                        @endif
                    </ul>
                </nav>

                {{-- Main Content --}}
                <div class="flex-1 overflow-y-auto">
                    <div class="p-5">
                        {{-- Tab: Data Dasar --}}
                        <div x-show="activeTab === 'dasar'" x-cloak>
                            <h3 class="text-base font-semibold text-gray-900 mb-5">Data Dasar</h3>
                            <div class="space-y-4">
                                @if ($editingEmployeeId && isset($editingEmployee))
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">ID Karyawan</label>
                                    <input type="text" class="mt-1 block w-full bg-gray-50 text-gray-500 border-gray-300 rounded-md shadow-sm text-sm" value="{{ $editingEmployee->employee_code }}" readonly disabled />
                                    <p class="mt-1 text-xs text-gray-400">Dibuat otomatis, tidak bisa diubah.</p>
                                </div>
                                @endif

                                <div>
                                    <label for="full_name" class="block text-sm font-medium text-gray-700">Nama Lengkap <span class="text-red-500">*</span></label>
                                    <input wire:model="full_name" id="full_name" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" required />
                                    @error('full_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="nik" class="block text-sm font-medium text-gray-700">NIK (KTP)</label>
                                        <input wire:model="nik" id="nik" type="text" maxlength="16" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                        @error('nik') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="phone" class="block text-sm font-medium text-gray-700">No. Telepon</label>
                                        <input wire:model="phone" id="phone" type="text" maxlength="20" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                        @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                                <div>
                                    <label for="address" class="block text-sm font-medium text-gray-700">Alamat</label>
                                    <textarea wire:model="address" id="address" rows="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"></textarea>
                                    @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>

                                @if ($editingEmployeeId)
                                <div class="flex items-center gap-2">
                                    <input wire:model="is_active" id="is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                    <label for="is_active" class="text-sm font-medium text-gray-700">Aktif</label>
                                </div>

                                <div class="pt-4 mt-2 border-t border-gray-100">
                                    <p class="text-sm font-medium text-gray-800">Akun login</p>
                                    @php
                                        $editAccountEmail = $editingEmployee?->portalUser?->email
                                            ?? ($userEmailsByName[mb_strtolower(trim($full_name))] ?? null);
                                    @endphp
                                    @if ($editAccountEmail)
                                        <p class="mt-0.5 text-xs text-gray-500">{{ $editAccountEmail }}</p>
                                    @else
                                        <p class="mt-0.5 text-xs text-gray-500">Reset membuat password acak baru (hanya untuk akun portal karyawan tertaut).</p>
                                    @endif
                                    @if ($resetPasswordPlain)
                                        <div class="mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2">
                                            <p class="text-xs font-medium text-amber-900">Password baru (salin sekarang):</p>
                                            <p class="mt-1 font-mono text-sm text-amber-950 select-all">{{ $resetPasswordPlain }}</p>
                                        </div>
                                    @endif
                                    <button
                                        type="button"
                                        wire:click="resetPassword"
                                        wire:confirm="Buat password acak baru untuk akun portal karyawan ini?"
                                        class="mt-3 inline-flex items-center px-3 py-2 rounded-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    >
                                        Reset Password
                                    </button>
                                </div>
                                @endif
                            </div>
                        </div>

                        {{-- Tab: Kepegawaian --}}
                        <div x-show="activeTab === 'kepegawaian'" x-cloak>
                            <h3 class="text-base font-semibold text-gray-900 mb-5">Data Kepegawaian</h3>
                            <div class="space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="position" class="block text-sm font-medium text-gray-700">Jabatan</label>
                                        <x-autocomplete name="position" id="position" :options="$jabatanOptions" :parameter-id="$jabatanParameterId" placeholder="Pilih atau tambah jabatan" />
                                        @error('position') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="department" class="block text-sm font-medium text-gray-700">Departemen</label>
                                        <x-autocomplete name="department" id="department" :options="$departemenOptions" :parameter-id="$departemenParameterId" placeholder="Pilih atau tambah departemen" />
                                        @error('department') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                                <div>
                                    <label for="join_date" class="block text-sm font-medium text-gray-700">Tanggal Bergabung</label>
                                    <input wire:model="join_date" id="join_date" type="date" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                    @error('join_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Tab: Perpajakan & BPJS --}}
                        <div x-show="activeTab === 'pajak'" x-cloak>
                            <h3 class="text-base font-semibold text-gray-900 mb-5">Perpajakan & BPJS</h3>
                            <div class="space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="npwp" class="block text-sm font-medium text-gray-700">NPWP</label>
                                        <input wire:model="npwp" id="npwp" type="text" maxlength="25" placeholder="00.000.000.0-000.000" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                        @error('npwp') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="ptkp_status" class="block text-sm font-medium text-gray-700">Status PTKP</label>
                                        <x-autocomplete name="ptkp_status" id="ptkp_status" :options="$ptkpOptions" :parameter-id="$ptkpParameterId" placeholder="Pilih atau tambah status PTKP" />
                                        @error('ptkp_status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="bpjs_kes" class="block text-sm font-medium text-gray-700">No. BPJS Kesehatan</label>
                                        <input wire:model="bpjs_kes" id="bpjs_kes" type="text" maxlength="20" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                        @error('bpjs_kes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="bpjs_tk" class="block text-sm font-medium text-gray-700">No. BPJS Ketenagakerjaan</label>
                                        <input wire:model="bpjs_tk" id="bpjs_tk" type="text" maxlength="20" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                        @error('bpjs_tk') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Tab: Rekening Bank --}}
                        <div x-show="activeTab === 'bank'" x-cloak>
                            <h3 class="text-base font-semibold text-gray-900 mb-5">Rekening Bank</h3>
                            <div class="space-y-4">
                                <div>
                                    <label for="bank_name" class="block text-sm font-medium text-gray-700">Nama Bank</label>
                                    <x-autocomplete name="bank_name" id="bank_name" :options="$bankOptions" :parameter-id="$bankParameterId" placeholder="Pilih atau tambah nama bank" />
                                    @error('bank_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="bank_account" class="block text-sm font-medium text-gray-700">Nomor Rekening</label>
                                        <input wire:model="bank_account" id="bank_account" type="text" maxlength="30" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                        @error('bank_account') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="bank_holder" class="block text-sm font-medium text-gray-700">Atas Nama</label>
                                        <input wire:model="bank_holder" id="bank_holder" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                        @error('bank_holder') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Tab: Sidik Jari (edit only) --}}
                        @if ($editingEmployeeId && isset($editingEmployee))
                        <div x-show="activeTab === 'fingerprint'" x-cloak>
                            <h3 class="text-base font-semibold text-gray-900 mb-5">Sidik Jari Terdaftar</h3>
                            <div class="space-y-2">
                                @forelse ($editingEmployee->fingerprintTemplates as $index => $template)
                                    <div class="flex items-center justify-between bg-gray-50 rounded-md px-4 py-3">
                                        <div class="text-sm text-gray-700">
                                            <span class="font-semibold text-gray-900">Sidik Jari #{{ $index + 1 }}</span>
                                            <span class="text-gray-400 block text-xs mt-0.5">
                                                {{ $template->device->name }} · slot #{{ $template->fingerprint_slot_id }}
                                                · terdaftar {{ $template->enrolled_at->format('d M Y H:i') }}
                                            </span>
                                        </div>
                                        <button wire:click="deleteFingerprint('{{ $template->id }}')" wire:confirm="Hapus sidik jari ini?" class="inline-flex items-center px-3 py-1.5 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-500 py-2">Belum ada sidik jari terdaftar.</p>
                                @endforelse
                            </div>

                            @if (isset($devices) && $devices->count())
                            <div class="mt-6" x-data="{ selectedDevice: '{{ $devices->first()?->id }}' }">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tambah Sidik Jari</label>
                                <div class="flex items-end gap-3">
                                    <select x-model="selectedDevice" class="block w-full rounded-md border-gray-300 text-sm">
                                        @foreach ($devices as $device)
                                            <option value="{{ $device->id }}">{{ $device->name }}</option>
                                        @endforeach
                                    </select>
                                    <button @click="$wire.enrollFingerprint(selectedDevice)" type="button" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition whitespace-nowrap">
                                        Tambah
                                    </button>
                                </div>
                            </div>
                            @endif
                        </div>

                        {{-- Tab: PIN (edit only) — x-if agar type=password tidak ikut di DOM saat tab lain (cegah autofill Chrome) --}}
                        <template x-if="activeTab === 'pin'">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900 mb-5">Pengaturan PIN</h3>
                                <div class="space-y-4">
                                    <p class="text-sm text-gray-500">
                                        Status PIN:
                                        @if ($editingEmployee->pin_hash)
                                            <span class="font-medium text-green-700">sudah diatur</span>
                                        @else
                                            <span class="font-medium text-gray-500">belum diatur</span>
                                        @endif
                                    </p>
                                    <div>
                                        <label for="employee_pin_new" class="block text-sm font-medium text-gray-700">PIN Baru (kosongkan jika tidak diubah)</label>
                                        <input wire:model="pin" id="employee_pin_new" type="password" inputmode="numeric" maxlength="6" autocomplete="new-password" data-lpignore="true" data-1p-ignore="true" data-form-type="other" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                        @error('pin') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="employee_pin_confirm" class="block text-sm font-medium text-gray-700">Konfirmasi PIN</label>
                                        <input wire:model="pin_confirmation" id="employee_pin_confirm" type="password" inputmode="numeric" maxlength="6" autocomplete="new-password" data-lpignore="true" data-1p-ignore="true" data-form-type="other" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Tab: Pengaturan Gaji (edit only) --}}
                        <div x-show="activeTab === 'gaji'" x-cloak>
                            <h3 class="text-base font-semibold text-gray-900 mb-2">Pengaturan Gaji</h3>
                            <p class="text-sm text-gray-500 mb-4">Kelola gaji pokok, tunjangan, dan potongan. Setiap perubahan gaji pokok tersimpan di riwayat.</p>

                            <div class="mb-5 rounded-lg border border-gray-200 p-4">
                                <h4 class="text-sm font-semibold text-gray-900 mb-3">Riwayat Gaji</h4>
                                @if (empty($salaryHistory))
                                    <p class="text-sm text-gray-500">Belum ada riwayat gaji. Buka pengaturan untuk mencatat gaji awal.</p>
                                @else
                                    <ol class="relative ms-3 border-s border-gray-200 space-y-4">
                                        @foreach ($salaryHistory as $item)
                                            <li class="ms-4">
                                                <span class="absolute -start-1.5 mt-1.5 h-3 w-3 rounded-full border border-white {{ $item['is_active'] ? 'bg-green-500' : ($item['change'] > 0 ? 'bg-blue-500' : ($item['change'] < 0 ? 'bg-amber-500' : 'bg-gray-400')) }}"></span>
                                                <div class="flex flex-wrap items-start justify-between gap-2">
                                                    <div>
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <p class="text-sm font-semibold text-gray-900">{{ $item['label'] }}</p>
                                                            @if ($item['is_active'])
                                                                <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-[11px] font-medium text-green-700">Aktif</span>
                                                            @endif
                                                        </div>
                                                        <p class="text-xs text-gray-500 mt-0.5">{{ $item['effective_date_label'] }}</p>
                                                        @if ($item['note'])
                                                            <p class="text-xs text-gray-400 mt-0.5">{{ $item['note'] }}</p>
                                                        @endif
                                                    </div>
                                                    <div class="text-right">
                                                        <p class="text-sm font-semibold text-gray-900">{{ $item['base_salary_label'] }}</p>
                                                        @if ($item['change_label'])
                                                            <p class="text-xs font-medium mt-0.5 {{ $item['change'] > 0 ? 'text-green-600' : ($item['change'] < 0 ? 'text-red-600' : 'text-gray-500') }}">
                                                                {{ $item['change_label'] }}
                                                            </p>
                                                        @endif
                                                    </div>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ol>
                                @endif
                            </div>

                            <button type="button"
                                    @click="window.dispatchEvent(new CustomEvent('open-employee-salary', { detail: { employeeId: @js($editingEmployeeId) } }))"
                                    class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                Buka Pengaturan Gaji
                            </button>
                        </div>

                        {{-- Tab: Zona Berbahaya (edit only) --}}
                        <div x-show="activeTab === 'danger'" x-cloak>
                            <h3 class="text-base font-semibold text-red-700 mb-2">Zona Berbahaya</h3>
                            <p class="text-sm text-gray-500 mb-4">
                                Menghapus karyawan juga akan menghapus seluruh riwayat absensi dan sidik jari terdaftar. Tindakan ini tidak bisa dibatalkan.
                            </p>

                            <template x-if="!confirmDelete">
                                <button @click="confirmDelete = true" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 transition">
                                    Hapus Karyawan
                                </button>
                            </template>
                            <template x-if="confirmDelete">
                                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                    <p class="text-sm text-red-800 font-medium mb-3">Apakah Anda yakin ingin menghapus karyawan ini?</p>
                                    <div class="flex gap-3">
                                        <button wire:click="deleteEmployee" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 transition">
                                            Ya, Hapus
                                        </button>
                                        <button @click="confirmDelete = false" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
                                            Batal
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-end gap-3 px-6 py-3.5 border-t border-gray-200 bg-gray-50 shrink-0">
                <button type="button" wire:click="closeModal" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 transition">
                    Batal
                </button>
                <button type="button" wire:click="save" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                    Simpan
                </button>
            </div>
            </div>
        </div>
    </div>

    {{-- Modal gaji: di luar modal karyawan, wire:ignore agar Livewire tidak merusak Alpine; z-[60] > z-50 --}}
    <div wire:ignore>
        <x-employee-salary-modal />
    </div>

    @if (session('open_salary_employee'))
        <script>
            document.addEventListener('alpine:initialized', () => {
                window.dispatchEvent(new CustomEvent('open-employee-salary', {
                    detail: { employeeId: @json(session('open_salary_employee')) },
                }));
            }, { once: true });
        </script>
    @endif
</div>
