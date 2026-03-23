<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('payroll_employees', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_employees', 'other_earnings')) {
                $table->decimal('other_earnings', 12)->default(0)->after('cellphone_allowance');
            }
            if (!Schema::hasColumn('payroll_employees', 'other_deductions')) {
                $table->decimal('other_deductions', 12)->default(0)->after('sss_deduction');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payroll_employees', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_employees', 'other_earnings')) {
                $table->dropColumn('other_earnings');
            }
            if (Schema::hasColumn('payroll_employees', 'other_deductions')) {
                $table->dropColumn('other_deductions');
            }
        });
    }
};
