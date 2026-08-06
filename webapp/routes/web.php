<?php

use App\Http\Controllers\AllowanceTypeController;
use App\Http\Controllers\CashBonController;
use App\Http\Controllers\DeductionTypeController;
use App\Http\Controllers\DeviceSettingsController;
use App\Http\Controllers\DeviceWifiController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeSalaryController;
use App\Http\Controllers\FingerprintEnrollController;
use App\Http\Controllers\ParameterController;
use App\Http\Controllers\PayrollSettingsController;
use App\Http\Controllers\PaySlipController;
use App\Http\Controllers\RoleAccessController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserAccessController;
use App\Http\Controllers\WorkScheduleController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::redirect('/', '/login');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth', 'verified', 'menu'])->group(function () {
    Volt::route('dashboard', 'pages.dashboard')->name('dashboard');
    Volt::route('my/attendance', 'pages.employee.dashboard')->name('employee.dashboard');

    Volt::route('employees', 'pages.employees.index')->name('employees.index');
    Route::get('employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
    Route::put('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::put('employees/{employee}/portal', [EmployeeController::class, 'updatePortal'])->name('employees.portal.update');
    Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');

    Route::post('employees/{employee}/enroll-fingerprint', [FingerprintEnrollController::class, 'store'])
        ->name('employees.enroll-fingerprint');
    Route::delete('employees/{employee}/fingerprint-templates/{template}', [FingerprintEnrollController::class, 'destroy'])
        ->name('employees.fingerprint-templates.destroy');
    Route::get('enroll-commands/{command}/status', [FingerprintEnrollController::class, 'status'])
        ->name('enroll-commands.status');

    Route::get('work-schedule', [WorkScheduleController::class, 'edit'])->name('work-schedule.edit');
    Route::post('work-schedule', [WorkScheduleController::class, 'store'])->name('work-schedule.store');
    Route::put('work-schedule/{schedule}', [WorkScheduleController::class, 'update'])->name('work-schedule.update');
    Route::post('work-schedule/{schedule}/activate', [WorkScheduleController::class, 'activate'])->name('work-schedule.activate');
    Route::delete('work-schedule/{schedule}', [WorkScheduleController::class, 'destroy'])->name('work-schedule.destroy');

    Volt::route('attendance', 'pages.attendance.index')->name('attendance.index');
    Volt::route('reports/attendance', 'pages.reports.attendance')->name('reports.attendance');
    Volt::route('cash-bons', 'pages.cash-bons.index')->name('cash-bons.index');

    Route::get('employees/{employee}/salary', [EmployeeSalaryController::class, 'edit'])->name('employees.salary');
    Route::get('employees/{employee}/salary/payload', [EmployeeSalaryController::class, 'payload'])->name('employees.salary.payload');
    Route::put('employees/{employee}/salary', [EmployeeSalaryController::class, 'update'])->name('employees.salary.update');

    Route::get('employees/{employee}/cash-bons/payload', [CashBonController::class, 'payload'])->name('employees.cash-bons.payload');
    Route::post('employees/{employee}/cash-bons', [CashBonController::class, 'store'])->name('employees.cash-bons.store');
    Route::delete('employees/{employee}/cash-bons/{cashBon}', [CashBonController::class, 'destroy'])->name('employees.cash-bons.destroy');

    Route::get('payroll/settings', [PayrollSettingsController::class, 'edit'])->name('payroll.settings');
    Route::put('payroll/settings', [PayrollSettingsController::class, 'update'])->name('payroll.settings.update');
    Route::resource('payroll/allowance-types', AllowanceTypeController::class)->except(['show'])->names('payroll.allowance-types');
    Route::resource('payroll/deduction-types', DeductionTypeController::class)->except(['show'])->names('payroll.deduction-types');

    Volt::route('payroll', 'pages.payroll.index')->name('payroll.index');
    Volt::route('payroll/{period}', 'pages.payroll.show')->name('payroll.show');
    Volt::route('payroll/{period}/entry/{entry}', 'pages.payroll.entry')->name('payroll.entry');
    Route::get('payroll/{period}/entry/{entry}/slip', [PaySlipController::class, 'download'])->name('payroll.slip');

    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings/company', [SettingsController::class, 'updateCompany'])->name('settings.company.update');
    Route::post('settings/roles', [RoleAccessController::class, 'store'])->name('settings.roles.store');
    Route::put('settings/roles/{role}', [RoleAccessController::class, 'update'])->name('settings.roles.update');
    Route::delete('settings/roles/{role}', [RoleAccessController::class, 'destroy'])->name('settings.roles.destroy');

    Route::post('settings/users', [UserAccessController::class, 'store'])->name('settings.users.store');
    Route::put('settings/users/{user}', [UserAccessController::class, 'update'])->name('settings.users.update');
    Route::delete('settings/users/{user}', [UserAccessController::class, 'destroy'])->name('settings.users.destroy');

    Route::get('settings/devices/{device}/edit', [DeviceSettingsController::class, 'edit'])->name('settings.devices.edit');
    Route::put('settings/devices/{device}', [DeviceSettingsController::class, 'update'])->name('settings.devices.update');
    Route::get('settings/devices/{device}/wifi', [DeviceWifiController::class, 'show'])->name('settings.devices.wifi');
    Route::post('settings/devices/{device}/wifi', [DeviceWifiController::class, 'start'])->name('settings.devices.wifi.start');

    Route::get('settings/parameters', [ParameterController::class, 'index'])->name('settings.parameters.index');
    Route::post('settings/parameters', [ParameterController::class, 'store'])->name('settings.parameters.store');
    Route::put('settings/parameters/{parameter}', [ParameterController::class, 'update'])->name('settings.parameters.update');
    Route::delete('settings/parameters/{parameter}', [ParameterController::class, 'destroy'])->name('settings.parameters.destroy');
    Route::get('settings/parameters/{parameter}/details', [ParameterController::class, 'details'])->name('settings.parameters.details');
    Route::post('settings/parameters/{parameter}/details', [ParameterController::class, 'storeDetail'])->name('settings.parameters.details.store');
    Route::put('settings/parameters/{parameter}/details/{detail}', [ParameterController::class, 'updateDetail'])->name('settings.parameters.details.update');
    Route::delete('settings/parameters/{parameter}/details/{detail}', [ParameterController::class, 'destroyDetail'])->name('settings.parameters.details.destroy');
});

require __DIR__.'/auth.php';
