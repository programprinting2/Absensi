<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\AttendanceEvaluateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DeviceAttendanceEvaluateController extends Controller
{
  public function __invoke(Request $request, AttendanceEvaluateService $evaluate): JsonResponse
  {
    $data = $request->validate([
      'device_code' => ['required', 'string', 'max:32'],
      'employee_id' => ['required', 'string', 'uuid'],
      'attendance_type' => ['required', 'string', 'in:clock_in,break_start,break_end,clock_out'],
      'event_time' => ['required', 'date'],
      'break_start_time' => ['nullable', 'date'],
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

    $eventTime = Carbon::parse($data['event_time'])->utc();
    $breakStart = isset($data['break_start_time'])
      ? Carbon::parse($data['break_start_time'])->utc()
      : null;

    try {
      $result = $evaluate->evaluate(
        $data['employee_id'],
        $data['attendance_type'],
        $eventTime,
        $breakStart,
        $device->lcd_config,
      );
    } catch (\InvalidArgumentException $e) {
      return response()->json([
        'ok' => false,
        'error' => $e->getMessage(),
      ], 422);
    }

    return response()->json([
      'ok' => true,
      ...$result,
    ]);
  }
}
