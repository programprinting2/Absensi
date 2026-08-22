<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_swap_requests', function (Blueprint $table) {
            $table->string('request_type', 16)->default('move')->after('employee_id');
            $table->foreignUuid('counterparty_employee_id')->nullable()->after('request_type')
                ->constrained('employees')->nullOnDelete();
            $table->string('peer_status', 24)->nullable()->after('status');
            $table->timestamp('peer_reviewed_at')->nullable()->after('peer_status');

            $table->index(['counterparty_employee_id', 'peer_status']);
            $table->index('request_type');
        });
    }

    public function down(): void
    {
        Schema::table('shift_swap_requests', function (Blueprint $table) {
            $table->dropForeign(['counterparty_employee_id']);
            $table->dropIndex(['counterparty_employee_id', 'peer_status']);
            $table->dropIndex(['request_type']);
            $table->dropColumn([
                'request_type',
                'counterparty_employee_id',
                'peer_status',
                'peer_reviewed_at',
            ]);
        });
    }
};
