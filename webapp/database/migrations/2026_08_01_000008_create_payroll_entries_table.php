<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('base_salary', 15, 2)->default(0);
            $table->decimal('total_allowances', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->decimal('overtime_amount', 15, 2)->default(0);
            $table->integer('late_count')->default(0);
            $table->decimal('late_penalty', 15, 2)->default(0);
            $table->integer('absent_days')->default(0);
            $table->decimal('absent_penalty', 15, 2)->default(0);
            $table->integer('early_out_count')->default(0);
            $table->decimal('early_out_penalty', 15, 2)->default(0);
            $table->decimal('pph21_amount', 15, 2)->default(0);
            $table->decimal('gross_salary', 15, 2)->default(0);
            $table->decimal('net_salary', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_adjusted')->default(false);

            $table->unique(['payroll_period_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_entries');
    }
};
