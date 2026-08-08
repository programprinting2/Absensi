<?php

namespace App\Http\Controllers;

use App\Events\EmployeeListUpdated;
use App\Models\DeviceCommand;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function create()
    {
        return view('employees.create', ['nextEmployeeCode' => $this->nextEmployeeCode()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
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
        ]);

        $employee = new Employee(array_merge($data, [
            'employee_code' => $this->nextEmployeeCode(),
            'is_active' => true,
            'pin_salt' => bin2hex(random_bytes(8)),
        ]));
        $employee->save();

        EmployeeListUpdated::dispatch();

        return redirect()->route('employees.index')->with('status', 'Karyawan berhasil ditambahkan.');
    }

    public function edit(string $id)
    {
        $employee = Employee::with(['fingerprintTemplates.device', 'portalUser'])->findOrFail($id);

        return view('employees.edit', compact('employee'));
    }

    public function update(Request $request, string $id)
    {
        $employee = Employee::findOrFail($id);

        $data = $request->validate([
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
            'pin' => ['nullable', 'digits_between:4,6', 'confirmed'],
            'is_active' => ['required', 'boolean'],
        ]);

        $employee->fill(collect($data)->except(['pin', 'pin_confirmation', 'is_active'])->toArray());
        $employee->is_active = $data['is_active'];

        if (! empty($data['pin'])) {
            if (empty($employee->pin_salt)) {
                $employee->pin_salt = bin2hex(random_bytes(8));
            }
            $employee->pin_hash = $this->hashPin($data['pin'], $employee->pin_salt);
        }

        $employee->save();

        EmployeeListUpdated::dispatch();

        return redirect()->route('employees.index')->with('status', 'Data karyawan berhasil diperbarui.');
    }

    public function updatePortal(Request $request, string $id)
    {
        $employee = Employee::with('portalUser')->findOrFail($id);
        $portalUser = $employee->portalUser;

        $data = $request->validate([
            'portal_enabled' => ['nullable', 'boolean'],
            'portal_email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($portalUser?->id),
            ],
            'portal_password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $enabled = $request->boolean('portal_enabled');

        if (! $enabled) {
            $portalUser?->delete();

            return redirect()
                ->route('employees.edit', $employee)
                ->with('status', 'Akses portal karyawan dinonaktifkan.');
        }

        if (blank($data['portal_email'] ?? null)) {
            return redirect()
                ->route('employees.edit', $employee)
                ->withErrors(['portal_email' => 'Email wajib diisi untuk mengaktifkan portal.'])
                ->withInput();
        }

        if (! $portalUser && blank($data['portal_password'] ?? null)) {
            return redirect()
                ->route('employees.edit', $employee)
                ->withErrors(['portal_password' => 'Password wajib diisi saat membuat akun portal.'])
                ->withInput();
        }

        if ($portalUser) {
            $portalUser->name = $employee->full_name;
            $portalUser->email = $data['portal_email'];
            $portalUser->role = User::ROLE_EMPLOYEE;
            $portalUser->employee_id = $employee->id;
            if (filled($data['portal_password'] ?? null)) {
                $portalUser->password = $data['portal_password'];
            }
            $portalUser->save();
        } else {
            $portal = new User;
            $portal->forceFill([
                'name' => $employee->full_name,
                'email' => $data['portal_email'],
                'password' => $data['portal_password'],
                'role' => User::ROLE_EMPLOYEE,
                'employee_id' => $employee->id,
                'email_verified_at' => now(),
            ]);
            $portal->save();
        }

        return redirect()
            ->route('employees.edit', $employee)
            ->with('status', 'Akses portal karyawan berhasil disimpan.');
    }

    public function destroy(Request $request, string $id)
    {
        $employee = Employee::with('fingerprintTemplates')->findOrFail($id);

        // attendance_logs.employee_id tidak punya ON DELETE CASCADE di skema,
        // jadi riwayat absensinya harus dihapus dulu sebelum karyawannya.
        // fingerprint_templates ikut CASCADE, tapi template fisik di modul
        // sensor perlu perintah delete_fingerprint ke masing-masing device.
        DB::transaction(function () use ($employee, $request) {
            foreach ($employee->fingerprintTemplates as $template) {
                DeviceCommand::create([
                    'device_id' => $template->device_id,
                    'command_type' => 'delete_fingerprint',
                    'payload' => ['fingerprint_slot_id' => $template->fingerprint_slot_id],
                    'status' => 'pending',
                    'created_by' => $request->user()?->email,
                ]);
            }

            $employee->attendanceLogs()->delete();
            $employee->portalUser?->delete();
            $employee->delete();
        });

        EmployeeListUpdated::dispatch();

        return redirect()->route('employees.index')->with(
            'status',
            'Karyawan, riwayat absensi, dan perintah hapus sidik jari di sensor berhasil diproses.'
        );
    }

    private function hashPin(string $pin, string $salt): string
    {
        return hash_hmac('sha256', $pin, $salt);
    }

    private function nextEmployeeCode(): int
    {
        return (int) (Employee::max('employee_code') ?? 0) + 1;
    }
}
