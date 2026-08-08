<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceCommand;
use Illuminate\Http\Request;

class DeviceWifiController extends Controller
{
    public function portal(Device $device)
    {
        if (filled($device->last_ip)) {
            return redirect()->away('http://'.$device->last_ip.'/');
        }

        return view('settings.devices.wifi', [
            'device' => $device,
            'apName' => config('absensi.wifi_ap_name'),
            'apPassword' => config('absensi.wifi_ap_password'),
            'portalUrl' => config('absensi.wifi_portal_url'),
            'needsApFallback' => true,
        ]);
    }

    public function show(Device $device)
    {
        return view('settings.devices.wifi', [
            'device' => $device,
            'apName' => config('absensi.wifi_ap_name'),
            'apPassword' => config('absensi.wifi_ap_password'),
            'portalUrl' => config('absensi.wifi_portal_url'),
            'needsApFallback' => ! filled($device->last_ip),
        ]);
    }

    public function start(Request $request, Device $device)
    {
        DeviceCommand::create([
            'device_id' => $device->id,
            'command_type' => 'start_wifi_portal',
            'payload' => [],
            'status' => 'pending',
            'created_by' => $request->user()?->email,
        ]);

        if (filled($device->last_ip)) {
            return redirect()->away('http://'.$device->last_ip.'/');
        }

        return view('settings.devices.wifi-portal', [
            'device' => $device,
            'apName' => config('absensi.wifi_ap_name'),
            'apPassword' => config('absensi.wifi_ap_password'),
            'portalUrl' => config('absensi.wifi_portal_url'),
        ]);
    }
}
