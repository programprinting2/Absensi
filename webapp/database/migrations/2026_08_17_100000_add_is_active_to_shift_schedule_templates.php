<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_schedule_templates', function (Blueprint $table) {
            $table->boolean('is_active')->default(false)->after('is_default');
        });

        // Template pola berulang (is_default) juga dianggap template aktif.
        DB::table('shift_schedule_templates')
            ->where('is_default', true)
            ->update(['is_active' => true]);
    }

    public function down(): void
    {
        Schema::table('shift_schedule_templates', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
