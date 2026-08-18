<?php

use App\Http\Controllers\Api\DeviceAttendanceEvaluateController;
use App\Http\Controllers\Api\DeviceHeartbeatController;
use App\Http\Controllers\Api\DeviceRestController;
use Illuminate\Support\Facades\Route;

Route::post('device/heartbeat', DeviceHeartbeatController::class);
Route::post('device/evaluate-attendance', DeviceAttendanceEvaluateController::class);

// PostgREST-compatible shim — ESP32 mode Laravel lokal (Server URL = Laravel, API Key kosong).
Route::match(['get', 'post', 'patch'], 'rest/v1/{table}', [DeviceRestController::class, 'handle']);
