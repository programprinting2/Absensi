<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_leave_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('entitlement_days')->default(12);
            $table->unsignedSmallInteger('used_days')->default(0);
            $table->unsignedSmallInteger('expired_days')->default(0);
            $table->unsignedSmallInteger('cashed_days')->default(0);
            $table->decimal('cash_amount', 15, 2)->default(0);
            $table->string('status', 20)->default('open'); // open|closed
            $table->string('notes', 500)->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['employee_id', 'year']);
            $table->index(['year', 'status']);
        });

        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('annual_leave_days')->default(12)->after('joint_leave_days');
            $table->unsignedSmallInteger('leave_cash_day_divisor')->default(25)->after('annual_leave_days');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropColumn(['annual_leave_days', 'leave_cash_day_divisor']);
        });

        Schema::dropIfExists('employee_leave_balances');
    }
};
