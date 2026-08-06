<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_entry_details', function (Blueprint $table) {
            $table->uuid('reference_id')->nullable()->after('amount');
            $table->string('reference_type', 50)->nullable()->after('reference_id');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_entry_details', function (Blueprint $table) {
            $table->dropColumn(['reference_id', 'reference_type']);
        });
    }
};
