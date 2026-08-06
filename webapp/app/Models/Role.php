<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'home_menu_key',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function menus(): HasMany
    {
        return $this->hasMany(RoleMenu::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role', 'slug');
    }

    public function menuKeys(): array
    {
        return $this->menus()->pluck('menu_key')->all();
    }

    public function syncMenus(array $menuKeys): void
    {
        $menuKeys = array_values(array_unique(array_filter($menuKeys)));

        $this->menus()->whereNotIn('menu_key', $menuKeys)->delete();

        $existing = $this->menus()->pluck('menu_key')->all();
        $toInsert = array_diff($menuKeys, $existing);

        foreach ($toInsert as $key) {
            $this->menus()->create(['menu_key' => $key]);
        }
    }
}
