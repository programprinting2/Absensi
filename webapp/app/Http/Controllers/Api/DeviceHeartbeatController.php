<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceHeartbeatController extends Controller
{
    /**
     * Heartbeat dari ESP32 — update last_seen_at di database aktif Laravel.
     * Terpisah dari jalur data absensi/enroll (PostgREST/Supabase).
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_code' => ['required', 'string', 'max:32'],
            'fingerprint_capacity' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'local_ip' => ['nullable', 'ip'],
        ]);

        $device = Device::query()
            ->where('device_code', $data['device_code'])
            ->first();

        if (! $device) {
            return response()->json([
                'ok' => false,
                'error' => 'device_not_found',
            ], 404);
        }

        $updates = ['last_seen_at' => now()->utc()];

        $capacity = $data['fingerprint_capacity'] ?? null;
        if ($capacity !== null && $capacity > 0) {
            $updates['fingerprint_capacity'] = $capacity;
        }

        $localIp = $data['local_ip'] ?? null;
        if (filled($localIp)) {
            $updates['last_ip'] = $localIp;
        }

        $device->update($updates);

        return response()->json(['ok' => true]);
    }
}
