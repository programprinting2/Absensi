<?php

namespace App\Http\Controllers;

use App\Models\PayrollSetting;
use Illuminate\Http\Request;

class PayrollSettingsController extends Controller
{
    public function edit()
    {
        $settings = PayrollSetting::active();

        return view('payroll.settings', compact('settings'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'cutoff_start_day' => ['required', 'integer', 'min:1', 'max:31'],
            'cutoff_end_day' => ['required', 'integer', 'min:1', 'max:31'],
            'late_penalty_per_incident' => ['required', 'numeric', 'min:0'],
            'absent_penalty_per_day' => ['required', 'numeric', 'min:0'],
            'early_out_penalty_per_incident' => ['required', 'numeric', 'min:0'],
            'short_work_penalty_per_hour' => ['required', 'numeric', 'min:0'],
            'over_break_penalty_per_incident' => ['required', 'numeric', 'min:0'],
            'overtime_rate_per_hour' => ['required', 'numeric', 'min:0'],
            'enable_pph21' => ['sometimes', 'boolean'],
            'pph21_method' => ['required_if:enable_pph21,true', 'nullable', 'in:gross,nett,gross_up'],
        ]);

        $data['enable_pph21'] = $request->boolean('enable_pph21');
        $data['pph21_method'] = $data['pph21_method'] ?? 'gross';

        PayrollSetting::active()->update($data);

        return redirect()->route('payroll.settings')->with('status', 'Pengaturan payroll berhasil disimpan.');
    }
}
