<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Support\MenuRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleAccessController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'home_menu_key' => ['nullable', 'string', Rule::in(MenuRegistry::items()->pluck('key')->all())],
            'menus' => ['nullable', 'array'],
            'menus.*' => ['string', Rule::in(MenuRegistry::items()->pluck('key')->all())],
        ]);

        $role = Role::query()->create([
            'name' => $data['name'],
            'slug' => MenuRegistry::makeSlug($data['name']),
            'home_menu_key' => $data['home_menu_key'] ?? null,
            'is_system' => false,
        ]);

        $role->syncMenus($data['menus'] ?? []);
        MenuRegistry::forgetRoleCache($role->id);

        return redirect()
            ->route('settings.index', ['tab' => 'hak-akses', 'role' => $role->id])
            ->with('status', "Role \"{$role->name}\" berhasil dibuat.")
            ->with('settings_tab', 'hak-akses');
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'home_menu_key' => ['nullable', 'string', Rule::in(MenuRegistry::items()->pluck('key')->all())],
            'menus' => ['nullable', 'array'],
            'menus.*' => ['string', Rule::in(MenuRegistry::items()->pluck('key')->all())],
        ]);

        $role->name = $data['name'];
        if (! $role->is_system) {
            $role->slug = $this->uniqueSlugKeepingSelf($data['name'], $role);
        }
        $role->home_menu_key = $data['home_menu_key'] ?? null;
        $role->save();

        $menus = $data['menus'] ?? [];

        // Role admin system wajib tetap punya settings agar tidak terkunci.
        if ($role->slug === 'admin' && ! in_array('settings', $menus, true)) {
            $menus[] = 'settings';
        }

        $role->syncMenus($menus);
        MenuRegistry::forgetRoleCache($role->id);

        return redirect()
            ->route('settings.index', ['tab' => 'hak-akses', 'role' => $role->id])
            ->with('status', "Hak akses \"{$role->name}\" berhasil disimpan.")
            ->with('settings_tab', 'hak-akses');
    }

    public function destroy(Role $role)
    {
        if ($role->is_system) {
            return redirect()
                ->route('settings.index', ['tab' => 'hak-akses'])
                ->with('error', 'Role sistem tidak bisa dihapus.')
                ->with('settings_tab', 'hak-akses');
        }

        if ($role->users()->exists()) {
            return redirect()
                ->route('settings.index', ['tab' => 'hak-akses', 'role' => $role->id])
                ->with('error', 'Role masih dipakai user. Pindahkan user dulu.')
                ->with('settings_tab', 'hak-akses');
        }

        $name = $role->name;
        $role->delete();
        MenuRegistry::forgetRoleCache();

        return redirect()
            ->route('settings.index', ['tab' => 'hak-akses'])
            ->with('status', "Role \"{$name}\" dihapus.")
            ->with('settings_tab', 'hak-akses');
    }

    private function uniqueSlugKeepingSelf(string $name, Role $role): string
    {
        $slug = \Illuminate\Support\Str::slug($name) ?: 'role';
        $base = $slug;
        $i = 1;

        while (
            Role::query()
                ->where('slug', $slug)
                ->where('id', '!=', $role->id)
                ->exists()
        ) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
