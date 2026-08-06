<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 50)->unique();
            $table->string('home_menu_key', 100)->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('role_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('menu_key', 100);
            $table->timestamps();

            $table->unique(['role_id', 'menu_key']);
        });

        Schema::create('menu_catalog_keys', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->timestamp('created_at')->useCurrent();
        });

        $now = now();

        $adminId = DB::table('roles')->insertGetId([
            'name' => 'Admin',
            'slug' => 'admin',
            'home_menu_key' => 'dashboard',
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $employeeId = DB::table('roles')->insertGetId([
            'name' => 'Karyawan',
            'slug' => 'employee',
            'home_menu_key' => 'employee.dashboard',
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $menuItems = collect(config('menus.items', []));

        foreach ($menuItems as $item) {
            DB::table('menu_catalog_keys')->insertOrIgnore([
                'key' => $item['key'],
                'created_at' => $now,
            ]);

            $defaults = $item['defaults'] ?? [];
            if (in_array('admin', $defaults, true)) {
                DB::table('role_menus')->insert([
                    'role_id' => $adminId,
                    'menu_key' => $item['key'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            if (in_array('employee', $defaults, true)) {
                DB::table('role_menus')->insert([
                    'role_id' => $employeeId,
                    'menu_key' => $item['key'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Pastikan users.role yang kosong / null jadi admin
        DB::table('users')
            ->where(function ($query) {
                $query->whereNull('role')->orWhere('role', '');
            })
            ->update(['role' => 'admin']);
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_catalog_keys');
        Schema::dropIfExists('role_menus');
        Schema::dropIfExists('roles');
    }
};
