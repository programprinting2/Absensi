<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('cutoff_start_day')->default(1);
            $table->integer('cutoff_end_day')->default(31);
            $table->decimal('late_penalty_per_incident', 15, 2)->default(0);
            $table->decimal('absent_penalty_per_day', 15, 2)->default(0);
            $table->decimal('early_out_penalty_per_incident', 15, 2)->default(0);
            $table->decimal('overtime_rate_per_hour', 15, 2)->default(0);
            $table->boolean('enable_pph21')->default(false);
            $table->string('pph21_method', 20)->default('gross');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_settings');
    }
};
