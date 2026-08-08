<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_day_reasons', function (Blueprint $table) {
            $table->text('day_reason')->nullable()->after('clock_out_reason');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_day_reasons', function (Blueprint $table) {
            $table->dropColumn('day_reason');
        });
    }
};
