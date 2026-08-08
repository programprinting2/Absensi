<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PayrollSettingsController extends Controller
{
    public function edit()
    {
        return redirect()->route('payroll.index', ['create' => 1]);
    }

    public function update(Request $request)
    {
        return redirect()->route('payroll.index', ['create' => 1]);
    }
}
