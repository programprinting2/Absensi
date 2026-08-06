<?php

namespace App\Support;

use App\Models\Role;
use App\Models\RoleMenu;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MenuRegistry
{
    public static function items(): Collection
    {
        return collect(config('menus.items', []))->values();
    }

    public static function iconPath(string $name): string
    {
        return (string) config("menus.icons.{$name}", config('menus.icons.home'));
    }

    public static function find(string $key): ?array
    {
        return self::items()->firstWhere('key', $key);
    }

    /**
     * Menu baru di config/menus.php otomatis masuk ke role default-nya.
     * Menu yang sudah pernah di-sync tidak di-grant ulang (supaya uncheck tetap dihormati).
     */
    public static function syncNewMenusToRoles(): void
    {
        $roles = Role::query()->get()->keyBy('slug');
        if ($roles->isEmpty()) {
            return;
        }

        $known = DB::table('menu_catalog_keys')->pluck('key')->all();
        $items = self::items();
        $currentKeys = $items->pluck('key')->all();
        $newKeys = array_values(array_diff($currentKeys, $known));

        if ($newKeys === []) {
            return;
        }

        foreach ($items->whereIn('key', $newKeys) as $item) {
            foreach ($item['defaults'] ?? [] as $slug) {
                $role = $roles->get($slug);
                if (! $role) {
                    continue;
                }

                RoleMenu::query()->firstOrCreate([
                    'role_id' => $role->id,
                    'menu_key' => $item['key'],
                ]);
            }

            DB::table('menu_catalog_keys')->insertOrIgnore([
                'key' => $item['key'],
                'created_at' => now(),
            ]);
        }

        self::forgetRoleCache();
    }

    public static function menuKeysForRole(?Role $role): array
    {
        if (! $role) {
            return [];
        }

        return Cache::remember(
            "menu_registry.role_keys.{$role->id}",
            60,
            fn () => $role->menus()->pluck('menu_key')->all()
        );
    }

    public static function forgetRoleCache(?int $roleId = null): void
    {
        if ($roleId) {
            Cache::forget("menu_registry.role_keys.{$roleId}");

            return;
        }

        foreach (Role::query()->pluck('id') as $id) {
            Cache::forget("menu_registry.role_keys.{$id}");
        }
    }

    public static function userCan(?User $user, string $menuKey): bool
    {
        if (! $user) {
            return false;
        }

        $role = $user->roleModel();
        if (! $role) {
            // fallback legacy: admin lihat semua kecuali employee dashboard
            if ($user->isAdmin()) {
                return $menuKey !== 'employee.dashboard';
            }

            return $menuKey === 'employee.dashboard';
        }

        return in_array($menuKey, self::menuKeysForRole($role), true);
    }

    public static function sidebarForUser(?User $user): Collection
    {
        if (! $user) {
            return collect();
        }

        self::syncNewMenusToRoles();

        return self::items()
            ->filter(fn (array $item) => ($item['sidebar'] ?? false) && self::userCan($user, $item['key']))
            ->values();
    }

    public static function menuKeyForRoute(?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }

        // Profile selalu bebas
        if ($routeName === 'profile' || Str::startsWith($routeName, 'profile.')) {
            return null;
        }

        foreach (self::items() as $item) {
            foreach ($item['patterns'] ?? [$item['route']] as $pattern) {
                if (Str::is($pattern, $routeName)) {
                    return $item['key'];
                }
            }
        }

        return null;
    }

    public static function homeRouteForUser(?User $user): string
    {
        if (! $user) {
            return 'login';
        }

        $role = $user->roleModel();
        if ($role?->home_menu_key) {
            $item = self::find($role->home_menu_key);
            if ($item && self::userCan($user, $item['key'])) {
                return $item['route'];
            }
        }

        $first = self::sidebarForUser($user)->first();

        return $first['route'] ?? 'profile';
    }

    public static function makeSlug(string $name): string
    {
        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = 'role';
        }

        $base = $slug;
        $i = 1;
        while (Role::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
