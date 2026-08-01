<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Support\DeviceLcdConfig;
use Illuminate\Http\Request;

class DeviceSettingsController extends Controller
{
    public function edit(Device $device)
    {
        $device->lcd_config = DeviceLcdConfig::mergeWithDefaults($device->lcd_config);

        return view('settings.devices.edit', [
            'device' => $device,
            'modes' => DeviceLcdConfig::MODES,
        ]);
    }

    public function update(Request $request, Device $device)
    {
        $validated = $request->validate(DeviceLcdConfig::validationRules());

        $device->update([
            'name' => $validated['name'],
            'lcd_config' => DeviceLcdConfig::mergeWithDefaults($request->input('lcd_config')),
        ]);

        return redirect()
            ->route('settings.index')
            ->with('status', "Pengaturan device \"{$device->name}\" berhasil disimpan.");
    }
}
