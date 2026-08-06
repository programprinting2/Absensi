<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('nik', 16)->nullable()->after('full_name');
            $table->string('phone', 20)->nullable()->after('nik');
            $table->text('address')->nullable()->after('phone');
            $table->string('position', 100)->nullable()->after('address');
            $table->string('department', 100)->nullable()->after('position');
            $table->date('join_date')->nullable()->after('department');
            $table->string('npwp', 25)->nullable()->after('join_date');
            $table->string('bpjs_kes', 20)->nullable()->after('npwp');
            $table->string('bpjs_tk', 20)->nullable()->after('bpjs_kes');
            $table->string('bank_name', 50)->nullable()->after('bpjs_tk');
            $table->string('bank_account', 30)->nullable()->after('bank_name');
            $table->string('bank_holder', 100)->nullable()->after('bank_account');
            $table->string('ptkp_status', 10)->nullable()->default('TK/0')->after('bank_holder');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'nik', 'phone', 'address', 'position', 'department', 'join_date',
                'npwp', 'bpjs_kes', 'bpjs_tk', 'bank_name', 'bank_account',
                'bank_holder', 'ptkp_status',
            ]);
        });
    }
};
