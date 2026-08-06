<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parameters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique('name');
        });

        Schema::create('parameter_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('parameter_id')->constrained('parameters')->cascadeOnDelete();
            $table->string('name');
            $table->string('value')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['parameter_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parameter_details');
        Schema::dropIfExists('parameters');
    }
};
