<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets a payroll_components row be defined as a percentage of Basic
     * Salary or of another component's resolved amount, instead of only a
     * flat default_amount - see
     * docs/superpowers/specs/2026-07-10-computed-payroll-components-design.md.
     * Every existing row defaults to calculation_type = 'fixed', which
     * computes identically to today's behavior.
     */
    public function up()
    {
        Schema::table('payroll_components', function (Blueprint $table) {
            $table->enum('calculation_type', ['fixed', 'percentage'])->default('fixed')->after('default_amount');
            $table->enum('base_type', ['basic_salary', 'component'])->nullable()->after('calculation_type');
            $table->string('base_component_code', 100)->nullable()->after('base_type');
            $table->decimal('rate_percent', 8, 3)->nullable()->after('base_component_code');
        });
    }

    public function down()
    {
        Schema::table('payroll_components', function (Blueprint $table) {
            $table->dropColumn(['calculation_type', 'base_type', 'base_component_code', 'rate_percent']);
        });
    }
};
