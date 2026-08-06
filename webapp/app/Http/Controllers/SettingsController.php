<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Device;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\AppTimezone;
use App\Support\MenuRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    private const ONLINE_THRESHOLD_SECONDS = 120;

    public function index()
    {
        MenuRegistry::syncNewMenusToRoles();

        $devices = Device::query()
            ->withCount('fingerprintTemplates')
            ->orderBy('name')
            ->get()
            ->map(function (Device $device) {
                $isOnline = $device->last_seen_at
                    && $device->last_seen_at->greaterThan(now()->subSeconds(self::ONLINE_THRESHOLD_SECONDS));

                $usedSlots = $device->fingerprint_templates_count;
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

        $roles = Role::query()->with('menus')->orderByDesc('is_system')->orderBy('name')->get();
        $selectedRoleId = (int) request('role', $roles->first()?->id);
        $selectedRole = $roles->firstWhere('id', $selectedRoleId) ?? $roles->first();

        $accessUsers = User::query()
            ->with('employee:id,full_name,employee_code')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'employee_id', 'created_at']);

        $staffRoles = $roles->where('slug', '!=', 'employee')->values();

        return view('settings.index', [
            'devices' => $devices,
            'company' => CompanySetting::active(),
            'schedule' => WorkSchedule::active() ?? new WorkSchedule(['name' => 'Jadwal Default']),
            'schedules' => WorkSchedule::query()->orderByDesc('is_active')->orderBy('name')->get(),
            'timezoneOptions' => AppTimezone::options(),
            'activeTab' => session('settings_tab', request('tab', 'perangkat')),
            'roles' => $roles,
            'staffRoles' => $staffRoles,
            'selectedRole' => $selectedRole,
            'menuItems' => MenuRegistry::items(),
            'accessUsers' => $accessUsers,
        ]);
    }

    public function updateCompany(Request $request)
    {
        $data = $request->validate([
            'company_name' => ['nullable', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'npwp' => ['nullable', 'string', 'max:25'],
            'nib' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'display_timezone' => ['required', 'string', 'max:64', Rule::in(array_keys(AppTimezone::options()))],
        ]);

        CompanySetting::active()->update($data);

        return redirect()
            ->route('settings.index', ['tab' => 'identitas'])
            ->with('status', 'Identitas usaha berhasil disimpan.')
            ->with('settings_tab', 'identitas');
    }
}
