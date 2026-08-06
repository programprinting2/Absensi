<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_allowances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('allowance_type_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_allowances');
    }
};
