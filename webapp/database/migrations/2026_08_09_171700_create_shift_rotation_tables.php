<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_teams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('shift_team_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shift_team_id')->constrained('shift_teams')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique('employee_id');
            $table->index('shift_team_id');
        });

        Schema::create('shift_rotation_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->date('start_date');
            $table->unsignedSmallInteger('phase_work_days')->default(6);
            $table->unsignedTinyInteger('phase_count')->default(2);
            $table->boolean('is_active')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('shift_rotation_slots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->constrained('shift_rotation_plans')->cascadeOnDelete();
            $table->unsignedTinyInteger('phase_index');
            $table->foreignUuid('shift_team_id')->constrained('shift_teams')->restrictOnDelete();
            $table->foreignUuid('work_schedule_id')->constrained('work_schedules')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['plan_id', 'phase_index', 'shift_team_id']);
            $table->index(['plan_id', 'phase_index']);
        });

        Schema::create('shift_day_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('work_date');
            $table->foreignUuid('work_schedule_id')->constrained('work_schedules')->restrictOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['employee_id', 'work_date']);
            $table->index('work_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_day_overrides');
        Schema::dropIfExists('shift_rotation_slots');
        Schema::dropIfExists('shift_rotation_plans');
        Schema::dropIfExists('shift_team_members');
        Schema::dropIfExists('shift_teams');
    }
};
