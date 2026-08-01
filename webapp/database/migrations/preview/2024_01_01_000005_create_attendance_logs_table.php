<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('device_id');
            $table->uuid('employee_id');
            $table->string('attendance_type');
            $table->string('method');
            $table->timestamp('event_time');
            $table->timestamp('synced_at')->useCurrent();
            $table->boolean('is_offline_capture')->default(false);
            $table->string('client_uuid')->unique();
            $table->text('raw_notes')->nullable();

            $table->index(['employee_id', 'event_time']);
            $table->index(['device_id', 'event_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
