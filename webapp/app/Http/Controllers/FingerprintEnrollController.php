<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\Employee;
use App\Models\FingerprintTemplate;
use Illuminate\Http\Request;

class FingerprintEnrollController extends Controller
{
    public function store(Request $request, string $employee)
    {
        $employee = Employee::findOrFail($employee);

        $data = $request->validate([
            'device_id' => ['required', 'uuid', 'exists:devices,id'],
        ]);

        $command = DeviceCommand::create([
            'device_id' => $data['device_id'],
            'command_type' => 'enroll_fingerprint',
            'payload' => ['employee_id' => $employee->id, 'employee_code' => $employee->employee_code],
            'status' => 'pending',
            'created_by' => $request->user()?->email,
        ]);

        return redirect()
            ->route('employees.edit', $employee->id)
            ->with('status', 'Perintah enroll sidik jari dikirim ke device. Silakan minta karyawan menempelkan jari di sensor.')
            ->with('enroll_command_id', $command->id);
    }

    public function destroy(Request $request, string $employee, string $template)
    {
        $employee = Employee::findOrFail($employee);
        $template = FingerprintTemplate::where('employee_id', $employee->id)->findOrFail($template);

        DeviceCommand::create([
            'device_id' => $template->device_id,
            'command_type' => 'delete_fingerprint',
            'payload' => ['fingerprint_slot_id' => $template->fingerprint_slot_id],
            'status' => 'pending',
            'created_by' => $request->user()?->email,
        ]);

        // Baris ini dihapus sekarang (bukan nunggu device konfirmasi) supaya
        // langsung hilang dari daftar; command di atas cuma buat bersihin
        // template fisik di flash sensor.
        $template->delete();

        return redirect()
            ->route('employees.edit', $employee->id)
            ->with('status', 'Sidik jari dihapus. Perintah hapus dari sensor sudah dikirim ke device.');
    }

    public function status(string $command)
    {
        $command = DeviceCommand::findOrFail($command);

        return response()->json([
            'status' => $command->status,
            'result' => $command->result,
        ]);
    }
}
