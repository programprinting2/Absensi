<?php

namespace App\Http\Controllers;

use App\Models\WorkSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkScheduleController extends Controller
{
    public function edit()
    {
        return redirect()->route('settings.index', ['tab' => 'jam-kerja']);
    }

    public function store(Request $request)
    {
        $validator = validator($request->all(), $this->rules());

        if ($validator->fails()) {
            return redirect()
                ->route('settings.index', ['tab' => 'jam-kerja'])
                ->withErrors($validator)
                ->withInput()
                ->with('settings_tab', 'jam-kerja');
        }

        $data = $this->normalize($validator->validated());

        $hasActive = WorkSchedule::query()->where('is_active', true)->exists();
        $data['is_active'] = ! $hasActive;

        WorkSchedule::create($data);

        return redirect()
            ->route('settings.index', ['tab' => 'jam-kerja'])
            ->with('status', "Profil jam kerja \"{$data['name']}\" berhasil dibuat.")
            ->with('settings_tab', 'jam-kerja');
    }

    public function update(Request $request, WorkSchedule $schedule)
    {
        $validator = validator($request->all(), $this->rules());

        if ($validator->fails()) {
            return redirect()
                ->route('settings.index', ['tab' => 'jam-kerja'])
                ->withErrors($validator)
                ->withInput()
                ->with('settings_tab', 'jam-kerja')
                ->with('editing_schedule_id', $schedule->id);
        }

        $data = $this->normalize($validator->validated());
        $schedule->update($data);

        return redirect()
            ->route('settings.index', ['tab' => 'jam-kerja'])
            ->with('status', "Profil \"{$schedule->name}\" berhasil diperbarui.")
            ->with('settings_tab', 'jam-kerja');
    }

    public function activate(WorkSchedule $schedule)
    {
        DB::transaction(function () use ($schedule) {
            WorkSchedule::query()
                ->where('is_active', true)
                ->where('id', '!=', $schedule->id)
                ->update(['is_active' => false]);

            $schedule->update(['is_active' => true]);
        });

        return redirect()
            ->route('settings.index', ['tab' => 'jam-kerja'])
            ->with('status', "Profil \"{$schedule->name}\" diaktifkan sebagai acuan perhitungan.")
            ->with('settings_tab', 'jam-kerja');
    }

    public function destroy(WorkSchedule $schedule)
    {
        if ($schedule->is_active) {
            return redirect()
                ->route('settings.index', ['tab' => 'jam-kerja'])
                ->with('error', 'Tidak bisa menghapus profil yang sedang aktif. Aktifkan profil lain dulu.')
                ->with('settings_tab', 'jam-kerja');
        }

        if (WorkSchedule::query()->count() <= 1) {
            return redirect()
                ->route('settings.index', ['tab' => 'jam-kerja'])
                ->with('error', 'Minimal harus ada satu profil jam kerja.')
                ->with('settings_tab', 'jam-kerja');
        }

        $name = $schedule->name;
        $schedule->delete();

        return redirect()
            ->route('settings.index', ['tab' => 'jam-kerja'])
            ->with('status', "Profil \"{$name}\" dihapus.")
            ->with('settings_tab', 'jam-kerja');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'clock_in_time' => ['required', 'date_format:H:i,H:i:s'],
            'clock_out_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'break_duration_minutes' => ['required', 'integer', 'min:0', 'max:480'],
            'work_duration_hours' => ['required', 'numeric', 'min:1', 'max:24'],
            'late_after_time' => ['required', 'date_format:H:i,H:i:s'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $data['clock_in_time'] = substr($data['clock_in_time'], 0, 5);
        $data['late_after_time'] = substr($data['late_after_time'], 0, 5);
        $data['work_duration_minutes'] = (int) round(((float) $data['work_duration_hours']) * 60);
        $data['clock_out_time'] = $this->calcClockOut(
            $data['clock_in_time'],
            $data['work_duration_minutes'],
            (int) $data['break_duration_minutes'],
        );
        unset($data['work_duration_hours']);

        return $data;
    }

    private function calcClockOut(string $clockIn, int $workMinutes, int $breakMinutes): string
    {
        [$h, $m] = array_map('intval', explode(':', $clockIn));
        $total = ($h * 60) + $m + $workMinutes + $breakMinutes;
        $total = (($total % 1440) + 1440) % 1440;

        return sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    }
}
