<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_entry_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payroll_entry_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20);
            $table->string('label', 100);
            $table->decimal('amount', 15, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_entry_details');
    }
};
