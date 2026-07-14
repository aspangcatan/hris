<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets an employee be given a different percentage rate for a
     * percentage-based global component (e.g. 30% Hazard Pay instead of the
     * catalog's 25%), while the amount keeps recomputing live off their
     * basic salary - see
     * docs/superpowers/specs/2026-07-14-per-employee-rate-override-design.md.
     * Only meaningful when the component's calculation_type = 'percentage';
     * ignored for fixed components. Existing rows default to null, which
     * computes identically to today (catalog rate applies).
     */
    public function up()
    {
        Schema::table('employee_payroll_components', function (Blueprint $table) {
            $table->decimal('rate_percent_override', 8, 3)->nullable()->after('amount_override');
        });
    }

    public function down()
    {
        Schema::table('employee_payroll_components', function (Blueprint $table) {
            $table->dropColumn('rate_percent_override');
        });
    }
};
