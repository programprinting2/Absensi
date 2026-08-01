<?php

namespace App\Http\Controllers;

use App\Events\EmployeeListUpdated;
use App\Models\DeviceCommand;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        ]);

        $employee = new Employee([
            'employee_code' => $this->nextEmployeeCode(),
            'full_name' => $data['full_name'],
            'is_active' => true,
            'pin_salt' => bin2hex(random_bytes(8)),
        ]);
        $employee->save();

        EmployeeListUpdated::dispatch();

        return redirect()->route('employees.index')->with('status', 'Karyawan berhasil ditambahkan.');
    }

    public function edit(string $id)
    {
        $employee = Employee::with('fingerprintTemplates.device')->findOrFail($id);

        return view('employees.edit', compact('employee'));
    }

    public function update(Request $request, string $id)
    {
        $employee = Employee::findOrFail($id);

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'pin' => ['nullable', 'digits_between:4,6', 'confirmed'],
            'is_active' => ['required', 'boolean'],
        ]);

        $employee->full_name = $data['full_name'];
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
