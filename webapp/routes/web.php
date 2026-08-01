<?php

use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FingerprintEnrollController;
use App\Http\Controllers\DeviceWifiController;
use App\Http\Controllers\DeviceSettingsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\WorkScheduleController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::redirect('/', '/login');

Volt::route('dashboard', 'pages.dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth', 'verified'])->group(function () {
    Volt::route('employees', 'pages.employees.index')->name('employees.index');
    Route::get('employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
    Route::put('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');

    Route::post('employees/{employee}/enroll-fingerprint', [FingerprintEnrollController::class, 'store'])
        ->name('employees.enroll-fingerprint');
    Route::delete('employees/{employee}/fingerprint-templates/{template}', [FingerprintEnrollController::class, 'destroy'])
        ->name('employees.fingerprint-templates.destroy');
    Route::get('enroll-commands/{command}/status', [FingerprintEnrollController::class, 'status'])
        ->name('enroll-commands.status');

    Route::get('work-schedule', [WorkScheduleController::class, 'edit'])->name('work-schedule.edit');
    Route::put('work-schedule', [WorkScheduleController::class, 'update'])->name('work-schedule.update');

    Volt::route('reports/attendance', 'pages.reports.attendance')->name('reports.attendance');

    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('settings/devices/{device}/edit', [DeviceSettingsController::class, 'edit'])->name('settings.devices.edit');
    Route::put('settings/devices/{device}', [DeviceSettingsController::class, 'update'])->name('settings.devices.update');
    Route::get('settings/devices/{device}/wifi', [DeviceWifiController::class, 'show'])->name('settings.devices.wifi');
    Route::post('settings/devices/{device}/wifi', [DeviceWifiController::class, 'start'])->name('settings.devices.wifi.start');
});

require __DIR__.'/auth.php';
