<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->string('implementation_mode', 32)->default('default')->after('is_enabled');
            $table->json('implementation_weekdays')->nullable()->after('implementation_mode');
            $table->json('implementation_month_days')->nullable()->after('implementation_weekdays');
            $table->json('implementation_specific_dates')->nullable()->after('implementation_month_days');
        });
    }

    public function down(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->dropColumn([
                'implementation_mode',
                'implementation_weekdays',
                'implementation_month_days',
                'implementation_specific_dates',
            ]);
        });
    }
};
