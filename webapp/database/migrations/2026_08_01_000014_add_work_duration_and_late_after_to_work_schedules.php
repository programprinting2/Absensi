<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->integer('work_duration_minutes')->default(480);
            $table->time('late_after_time')->nullable();
        });

        DB::table('work_schedules')->update(['work_duration_minutes' => 480]);
        DB::statement('UPDATE work_schedules SET late_after_time = clock_in_time WHERE late_after_time IS NULL');
    }

    public function down(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->dropColumn(['work_duration_minutes', 'late_after_time']);
        });
    }
};
