<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_day_reasons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('work_date');
            $table->string('clock_in_reason', 500)->nullable();
            $table->string('break_start_reason', 500)->nullable();
            $table->string('break_end_reason', 500)->nullable();
            $table->string('clock_out_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_day_reasons');
    }
};
