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
        if (Schema::hasTable('payroll_employees')) { return; }
        Schema::create('payroll_employees', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('payroll_id')->index('payroll_employees_payroll_id_foreign');
            $table->integer('employee_id');
            $table->integer('division');
            $table->integer('section');
            $table->decimal('basic_salary', 12)->default(0);
            $table->decimal('tardiness_deductions', 12)->nullable();
            $table->decimal('pera', 12)->default(0);
            $table->decimal('rata', 12)->default(0);
            $table->decimal('subsistence', 12)->default(0);
            $table->decimal('laundry', 12)->default(0);
            $table->decimal('hazard', 12)->default(0);
            $table->decimal('longevity', 12)->default(0);
            $table->decimal('cellphone_allowance', 12)->default(0);
            $table->decimal('late_absent', 12)->nullable();
            $table->decimal('gross_pay', 12)->default(0);
            $table->decimal('gsis_ps', 12)->default(0);
            $table->decimal('gsis_gs', 12)->default(0);
            $table->decimal('gsis_ec', 12)->default(0);
            $table->decimal('hdmf', 12)->default(0);
            $table->decimal('phic', 12)->default(0);
            $table->decimal('pagibig_cal', 12)->default(0);
            $table->decimal('pagibig_mpl', 12)->default(0);
            $table->decimal('pagibig_mp2', 12)->default(0);
            $table->decimal('pagibig_mp2_2nd', 12)->default(0);
            $table->decimal('pagibig_mp2_3rd', 12)->default(0);
            $table->decimal('gsis_consoloan', 12)->default(0);
            $table->decimal('gsis_emergency_loan', 12)->default(0);
            $table->decimal('gsis_educational_loan', 12)->default(0);
            $table->decimal('gsis_policy_loan', 12)->default(0);
            $table->decimal('gsis_opt_life_loan', 12)->default(0);
            $table->decimal('gsis_gfal', 12)->default(0);
            $table->decimal('gsis_mpl', 12)->default(0);
            $table->decimal('gsis_cpl', 12)->default(0);
            $table->decimal('gsis_mpl_lite', 12)->default(0);
            $table->decimal('cfi_coop', 12)->default(0);
            $table->decimal('first_valley_bank', 12)->default(0);
            $table->decimal('tdhea', 12)->default(0);
            $table->decimal('dbp_loan', 12)->default(0);
            $table->decimal('ucpb_loan', 12)->default(0);
            $table->decimal('meds_payable', 12)->default(0);
            $table->decimal('sss_deduction', 12)->default(0);
            $table->decimal('deductions', 12)->default(0);
            $table->decimal('net_pay', 12)->default(0);
            $table->decimal('salary_adjustment', 12)->nullable();
            $table->decimal('hazard_adjustment', 12)->nullable();
            $table->decimal('pera_adjustment', 12)->nullable();
            $table->decimal('subsistence_adjustment', 12)->nullable();
            $table->decimal('laundry_adjustment', 12)->nullable();
            $table->timestamps();
            $table->decimal('withholding_tax', 12)->nullable()->default(0);
            $table->decimal('withholding_tax_adjustment', 12)->nullable()->default(0);
            $table->decimal('phic_contri_adj', 12)->nullable()->default(0);
            $table->decimal('phic_refund_from_eo', 12)->nullable()->default(0);
            $table->decimal('sss_adjustment', 12)->nullable()->default(0);
            $table->decimal('gsis_ps_lwop_2pct', 12)->nullable()->default(0);
            $table->decimal('gsis_gs_lwop_2pct', 12)->nullable()->default(0);
            $table->decimal('gsis_plreg_refund', 12)->nullable()->default(0);
            $table->decimal('gsis_mpl_refund', 12)->nullable()->default(0);
            $table->decimal('gsis_emergency_refund', 12)->nullable()->default(0);
            $table->decimal('gsis_consoloan_refund', 12)->nullable()->default(0);
            $table->decimal('subsistence_deduction_from_leave', 12)->nullable()->default(0);
            $table->decimal('second_half_adjustment_resigned_employee', 12)->nullable()->default(0);
            $table->decimal('first_half_10th', 12)->nullable()->default(0);
            $table->decimal('second_half_25th', 12)->nullable()->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payroll_employees');
    }
};

