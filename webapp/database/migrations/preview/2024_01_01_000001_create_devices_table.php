<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('device_code')->unique();
            $table->string('name');
            $table->string('location')->nullable();
            $table->string('device_secret')->nullable();
            $table->string('firmware_version')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('fingerprint_capacity')->nullable();
            $table->json('lcd_config')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
