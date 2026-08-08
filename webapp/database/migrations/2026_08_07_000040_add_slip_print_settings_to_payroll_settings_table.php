<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->string('slip_paper', 32)->default('thermal_15x10')->after('pph21_method');
            $table->decimal('slip_margin_top_mm', 5, 1)->default(3)->after('slip_paper');
            $table->decimal('slip_margin_right_mm', 5, 1)->default(3)->after('slip_margin_top_mm');
            $table->decimal('slip_margin_bottom_mm', 5, 1)->default(3)->after('slip_margin_right_mm');
            $table->decimal('slip_margin_left_mm', 5, 1)->default(3)->after('slip_margin_bottom_mm');
            $table->boolean('slip_fit_to_width')->default(true)->after('slip_margin_left_mm');
            $table->string('slip_font', 32)->default('helvetica')->after('slip_fit_to_width');
            $table->unsignedSmallInteger('slip_font_scale')->default(100)->after('slip_font');
            $table->decimal('slip_width_mm', 6, 1)->nullable()->after('slip_font_scale');
            $table->decimal('slip_height_mm', 6, 1)->nullable()->after('slip_width_mm');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropColumn([
                'slip_paper',
                'slip_margin_top_mm',
                'slip_margin_right_mm',
                'slip_margin_bottom_mm',
                'slip_margin_left_mm',
                'slip_fit_to_width',
                'slip_font',
                'slip_font_scale',
                'slip_width_mm',
                'slip_height_mm',
            ]);
        });
    }
};
