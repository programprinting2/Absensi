<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_bons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->unsignedSmallInteger('installment_count');
            $table->decimal('installment_amount', 15, 2);
            $table->decimal('remaining_amount', 15, 2);
            $table->date('disbursed_at');
            $table->string('notes', 500)->nullable();
            $table->string('status', 20)->default('active'); // active|paid|cancelled
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('cash_bon_installments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cash_bon_id')->constrained('cash_bons')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->decimal('amount', 15, 2);
            $table->string('status', 20)->default('pending'); // pending|deducted|paid|cancelled
            $table->foreignUuid('payroll_entry_id')->nullable()->constrained('payroll_entries')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->unique(['cash_bon_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_bon_installments');
        Schema::dropIfExists('cash_bons');
    }
};
