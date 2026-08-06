<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allowance_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->boolean('is_fixed')->default(true);
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allowance_types');
    }
};
