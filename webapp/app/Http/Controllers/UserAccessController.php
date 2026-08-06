<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserAccessController extends Controller
{
    public function store(Request $request)
    {
        $roleSlugs = Role::query()
            ->where('slug', '!=', User::ROLE_EMPLOYEE)
            ->pluck('slug')
            ->all();

        $validator = validator($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'role' => ['required', 'string', Rule::in($roleSlugs)],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('settings.index', ['tab' => 'hak-akses'])
                ->withErrors($validator)
                ->withInput()
                ->with('settings_tab', 'hak-akses');
        }

        $data = $validator->validated();

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'employee_id' => null,
            'email_verified_at' => now(),
        ]);

        return redirect()
            ->route('settings.index', ['tab' => 'hak-akses'])
            ->with('status', "User \"{$data['email']}\" berhasil dibuat.")
            ->with('settings_tab', 'hak-akses');
    }

    public function update(Request $request, User $user)
    {
        $roleSlugs = Role::query()->pluck('slug')->all();

        $validator = validator($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', 'string', Rule::in($roleSlugs)],
            'password' => ['nullable', 'string', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('settings.index', ['tab' => 'hak-akses'])
                ->withErrors($validator)
                ->withInput()
                ->with('settings_tab', 'hak-akses')
                ->with('editing_user_id', $user->id);
        }

        $data = $validator->validated();

        if (
            $user->id === $request->user()->id
            && $user->isAdmin()
            && $data['role'] !== User::ROLE_ADMIN
        ) {
            $otherAdmins = User::query()
                ->where('role', User::ROLE_ADMIN)
                ->where('id', '!=', $user->id)
                ->exists();

            if (! $otherAdmins) {
                return redirect()
                    ->route('settings.index', ['tab' => 'hak-akses'])
                    ->with('error', 'Tidak bisa mengubah role: Anda admin terakhir.')
                    ->with('settings_tab', 'hak-akses')
                    ->with('editing_user_id', $user->id);
            }
        }

        // Role Karyawan hanya untuk akun yang sudah ditautkan lewat Edit Karyawan → Akses Portal.
        if ($data['role'] === User::ROLE_EMPLOYEE) {
            if (blank($user->employee_id)) {
                return redirect()
                    ->route('settings.index', ['tab' => 'hak-akses'])
                    ->withErrors(['role' => 'Akun Karyawan dibuat dari Karyawan → Edit → Akses Portal.'])
                    ->withInput()
                    ->with('settings_tab', 'hak-akses')
                    ->with('editing_user_id', $user->id);
            }
            $data['employee_id'] = $user->employee_id;
        } else {
            $data['employee_id'] = null;
        }

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->role = $data['role'];
        $user->employee_id = $data['employee_id'];

        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }

        $user->save();

        return redirect()
            ->route('settings.index', ['tab' => 'hak-akses'])
            ->with('status', "User \"{$user->email}\" diperbarui.")
            ->with('settings_tab', 'hak-akses');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return redirect()
                ->route('settings.index', ['tab' => 'hak-akses'])
                ->with('error', 'Tidak bisa menghapus akun sendiri.')
                ->with('settings_tab', 'hak-akses');
        }

        if ($user->isAdmin()) {
            $otherAdmins = User::query()
                ->where('role', User::ROLE_ADMIN)
                ->where('id', '!=', $user->id)
                ->exists();

            if (! $otherAdmins) {
                return redirect()
                    ->route('settings.index', ['tab' => 'hak-akses'])
                    ->with('error', 'Tidak bisa menghapus admin terakhir.')
                    ->with('settings_tab', 'hak-akses');
            }
        }

        $email = $user->email;
        $user->delete();

        return redirect()
            ->route('settings.index', ['tab' => 'hak-akses'])
            ->with('status', "User \"{$email}\" dihapus.")
            ->with('settings_tab', 'hak-akses');
    }
}
