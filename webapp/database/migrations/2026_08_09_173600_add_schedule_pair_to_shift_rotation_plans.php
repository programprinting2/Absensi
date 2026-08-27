<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_rotation_plans', function (Blueprint $table) {
            $table->foreignUuid('schedule_a_id')->nullable()->after('phase_count')->constrained('work_schedules')->nullOnDelete();
            $table->foreignUuid('schedule_b_id')->nullable()->after('schedule_a_id')->constrained('work_schedules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shift_rotation_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('schedule_a_id');
            $table->dropConstrainedForeignId('schedule_b_id');
        });
    }
};
