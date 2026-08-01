<?php

namespace App\Http\Controllers;

use App\Models\WorkSchedule;
use Illuminate\Http\Request;

class WorkScheduleController extends Controller
{
    public function edit()
    {
        $schedule = WorkSchedule::active() ?? new WorkSchedule(['name' => 'Jadwal Default']);

        return view('work-schedule.edit', compact('schedule'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'clock_in_time' => ['required', 'date_format:H:i,H:i:s'],
            'clock_out_time' => ['required', 'date_format:H:i,H:i:s'],
            'break_duration_minutes' => ['required', 'integer', 'min:0', 'max:480'],
        ]);

        // Beberapa browser mengirim waktu dengan detik (HH:MM:SS) untuk input type="time".
        // Normalisasi ke HH:MM supaya konsisten dengan yang ditampilkan di form.
        $data['clock_in_time'] = substr($data['clock_in_time'], 0, 5);
        $data['clock_out_time'] = substr($data['clock_out_time'], 0, 5);

        $schedule = WorkSchedule::active();

        if ($schedule) {
            $schedule->update($data);
        } else {
            WorkSchedule::create($data + ['is_active' => true]);
        }

        return redirect()->route('work-schedule.edit')->with('status', 'Jadwal kerja berhasil disimpan.');
    }
}
