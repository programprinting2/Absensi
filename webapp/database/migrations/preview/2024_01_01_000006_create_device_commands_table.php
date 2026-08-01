<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_commands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('device_id');
            $table->string('command_type');
            $table->json('payload')->nullable();
            $table->string('status')->default('pending');
            $table->json('result')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_commands');
    }
};
