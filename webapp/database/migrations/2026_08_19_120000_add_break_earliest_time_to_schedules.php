<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->time('break_earliest_time')->nullable()->after('break_duration_minutes');
        });

        Schema::table('shift_day_settings', function (Blueprint $table) {
            $table->time('break_earliest_time')->nullable()->after('break_duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('shift_day_settings', function (Blueprint $table) {
            $table->dropColumn('break_earliest_time');
        });

        Schema::table('work_schedules', function (Blueprint $table) {
            $table->dropColumn('break_earliest_time');
        });
    }
};
