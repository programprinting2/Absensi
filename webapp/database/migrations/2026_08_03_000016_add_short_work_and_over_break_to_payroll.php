<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->decimal('short_work_penalty_per_hour', 15, 2)->default(0)->after('early_out_penalty_per_incident');
            $table->decimal('over_break_penalty_per_incident', 15, 2)->default(0)->after('short_work_penalty_per_hour');
        });

        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->decimal('short_work_hours', 8, 2)->default(0)->after('early_out_penalty');
            $table->decimal('short_work_penalty', 15, 2)->default(0)->after('short_work_hours');
            $table->integer('over_break_count')->default(0)->after('short_work_penalty');
            $table->decimal('over_break_penalty', 15, 2)->default(0)->after('over_break_count');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropColumn(['short_work_penalty_per_hour', 'over_break_penalty_per_incident']);
        });

        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->dropColumn([
                'short_work_hours',
                'short_work_penalty',
                'over_break_count',
                'over_break_penalty',
            ]);
        });
    }
};
