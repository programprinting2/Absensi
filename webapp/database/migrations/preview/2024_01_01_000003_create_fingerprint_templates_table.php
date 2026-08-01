<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fingerprint_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->uuid('device_id');
            $table->integer('fingerprint_slot_id');
            $table->timestamp('enrolled_at')->useCurrent();

            $table->unique(['device_id', 'fingerprint_slot_id']);
            // Satu karyawan boleh punya banyak sidik jari di device yang sama.
            $table->index(['device_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fingerprint_templates');
    }
};
