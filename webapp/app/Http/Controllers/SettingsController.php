<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\FingerprintTemplate;
use Illuminate\Support\Carbon;

class SettingsController extends Controller
{
    private const ONLINE_THRESHOLD_SECONDS = 120;

    public function index()
    {
        $devices = Device::orderBy('name')->get()->map(function (Device $device) {
            $isOnline = $device->last_seen_at
                && $device->last_seen_at->greaterThan(now()->subSeconds(self::ONLINE_THRESHOLD_SECONDS));

            $usedSlots = FingerprintTemplate::where('device_id', $device->id)->count();
            $capacity = $device->fingerprint_capacity;
            $percent = $capacity ? min(100, round($usedSlots / $capacity * 100)) : 0;

            return [
                'device' => $device,
                'isOnline' => $isOnline,
                'usedSlots' => $usedSlots,
                'capacity' => $capacity,
                'percent' => $percent,
            ];
        });

        return view('settings.index', [
            'devices' => $devices,
        ]);
    }
}
