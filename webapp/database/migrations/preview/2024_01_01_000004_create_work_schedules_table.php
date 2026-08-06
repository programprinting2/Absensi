<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->time('clock_in_time');
            $table->time('clock_out_time');
            $table->integer('break_duration_minutes')->default(60);
            $table->integer('work_duration_minutes')->default(480);
            $table->time('late_after_time')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};
