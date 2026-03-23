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
        Schema::table('payroll_components', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_components', 'is_global')) {
                $table->boolean('is_global')->default(false)->after('type');
            }
            if (!Schema::hasColumn('payroll_components', 'default_amount')) {
                $table->decimal('default_amount', 12)->default(0)->after('taxable');
            }
        });

        Schema::table('employee_payroll_components', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_payroll_components', 'is_excluded')) {
                $table->boolean('is_excluded')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('employee_payroll_components', 'amount_override')) {
                $table->decimal('amount_override', 12)->nullable()->after('amount');
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
        Schema::table('employee_payroll_components', function (Blueprint $table) {
            if (Schema::hasColumn('employee_payroll_components', 'is_excluded')) {
                $table->dropColumn('is_excluded');
            }
            if (Schema::hasColumn('employee_payroll_components', 'amount_override')) {
                $table->dropColumn('amount_override');
            }
        });

        Schema::table('payroll_components', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_components', 'is_global')) {
                $table->dropColumn('is_global');
            }
            if (Schema::hasColumn('payroll_components', 'default_amount')) {
                $table->dropColumn('default_amount');
            }
        });
    }
};
