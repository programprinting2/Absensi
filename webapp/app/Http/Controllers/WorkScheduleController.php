<?php

namespace App\Http\Controllers;

use App\Models\WorkSchedule;
use Illuminate\Http\Request;

/**
 * Endpoint legacy: CRUD shift dipindah ke menu Shift Kerja (Livewire).
 * Route tetap ada agar bookmark / form lama tidak 404.
 */
class WorkScheduleController extends Controller
{
    public function edit()
    {
        return redirect()->route('shifts.index', ['tab' => 'rules']);
    }

    public function store(Request $request)
    {
        return redirect()->route('shifts.index', ['tab' => 'rules']);
    }

    public function update(Request $request, WorkSchedule $schedule)
    {
        return redirect()->route('shifts.index', ['tab' => 'rules']);
    }

    public function destroy(WorkSchedule $schedule)
    {
        return redirect()->route('shifts.index', ['tab' => 'rules']);
    }
}
