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
        if (Schema::hasTable('employee_benefits')) {
            Schema::drop('employee_benefits');
        }

        if (Schema::hasTable('employee_deductions')) {
            Schema::drop('employee_deductions');
        }

        Schema::table('payroll_employees', function (Blueprint $table) {
            $columns = [
                'tardiness_deductions',
                'pera',
                'rata',
                'subsistence',
                'laundry',
                'hazard',
                'longevity',
                'cellphone_allowance',
                'gsis_ps',
                'gsis_gs',
                'gsis_ec',
                'hdmf',
                'phic',
                'pagibig_cal',
                'pagibig_mpl',
                'pagibig_mp2',
                'pagibig_mp2_2nd',
                'pagibig_mp2_3rd',
                'gsis_consoloan',
                'gsis_emergency_loan',
                'gsis_educational_loan',
                'gsis_policy_loan',
                'gsis_opt_life_loan',
                'gsis_gfal',
                'gsis_mpl',
                'gsis_cpl',
                'gsis_mpl_lite',
                'cfi_coop',
                'first_valley_bank',
                'tdhea',
                'dbp_loan',
                'ucpb_loan',
                'meds_payable',
                'sss_deduction',
                'salary_adjustment',
                'hazard_adjustment',
                'pera_adjustment',
                'subsistence_adjustment',
                'laundry_adjustment',
                'withholding_tax',
                'withholding_tax_adjustment',
                'phic_contri_adj',
                'phic_refund_from_eo',
                'sss_adjustment',
                'gsis_ps_lwop_2pct',
                'gsis_gs_lwop_2pct',
                'gsis_plreg_refund',
                'gsis_mpl_refund',
                'gsis_emergency_refund',
                'gsis_consoloan_refund',
                'subsistence_deduction_from_leave',
                'second_half_adjustment_resigned_employee',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('payroll_employees', $column)) {
                    $table->dropColumn($column);
                }
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
        Schema::create('employee_benefits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('userid');
            $table->decimal('pera', 12);
            $table->decimal('rata', 12);
            $table->decimal('subsistence', 12);
            $table->decimal('laundry', 12);
            $table->decimal('longevity', 12);
            $table->decimal('cellphone_allowance', 12);
            $table->timestamps();
        });

        Schema::create('employee_deductions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('userid');
            $table->decimal('gsis_ps', 12);
            $table->decimal('gsis_gs', 12);
            $table->decimal('gsis_ec', 12);
            $table->decimal('hdmf', 12);
            $table->decimal('phic', 12);
            $table->decimal('pagibig_cal', 12);
            $table->decimal('pagibig_mpl', 12);
            $table->decimal('pagibig_mp2', 12);
            $table->decimal('pagibig_mp2_2nd', 12);
            $table->decimal('pagibig_mp2_3rd', 12);
            $table->decimal('gsis_consoloan', 12);
            $table->decimal('gsis_emergency_loan', 12);
            $table->decimal('gsis_educational_loan', 12);
            $table->decimal('gsis_policy_loan', 12);
            $table->decimal('gsis_opt_life_loan', 12);
            $table->decimal('gsis_gfal', 12);
            $table->decimal('gsis_mpl', 12);
            $table->decimal('gsis_cpl', 12);
            $table->decimal('gsis_mpl_lite', 12);
            $table->decimal('cfi_coop', 12);
            $table->decimal('first_valley_bank', 12);
            $table->decimal('tdhea', 12);
            $table->decimal('dbp_loan', 12);
            $table->decimal('ucpb_loan', 12);
            $table->decimal('meds_payable', 12);
            $table->decimal('sss_deduction', 12);
            $table->timestamps();
        });

        Schema::table('payroll_employees', function (Blueprint $table) {
            $columns = [
                'tardiness_deductions' => ['decimal', 12, true],
                'pera' => ['decimal', 12, false],
                'rata' => ['decimal', 12, false],
                'subsistence' => ['decimal', 12, false],
                'laundry' => ['decimal', 12, false],
                'hazard' => ['decimal', 12, false],
                'longevity' => ['decimal', 12, false],
                'cellphone_allowance' => ['decimal', 12, false],
                'gsis_ps' => ['decimal', 12, false],
                'gsis_gs' => ['decimal', 12, false],
                'gsis_ec' => ['decimal', 12, false],
                'hdmf' => ['decimal', 12, false],
                'phic' => ['decimal', 12, false],
                'pagibig_cal' => ['decimal', 12, false],
                'pagibig_mpl' => ['decimal', 12, false],
                'pagibig_mp2' => ['decimal', 12, false],
                'pagibig_mp2_2nd' => ['decimal', 12, false],
                'pagibig_mp2_3rd' => ['decimal', 12, false],
                'gsis_consoloan' => ['decimal', 12, false],
                'gsis_emergency_loan' => ['decimal', 12, false],
                'gsis_educational_loan' => ['decimal', 12, false],
                'gsis_policy_loan' => ['decimal', 12, false],
                'gsis_opt_life_loan' => ['decimal', 12, false],
                'gsis_gfal' => ['decimal', 12, false],
                'gsis_mpl' => ['decimal', 12, false],
                'gsis_cpl' => ['decimal', 12, false],
                'gsis_mpl_lite' => ['decimal', 12, false],
                'cfi_coop' => ['decimal', 12, false],
                'first_valley_bank' => ['decimal', 12, false],
                'tdhea' => ['decimal', 12, false],
                'dbp_loan' => ['decimal', 12, false],
                'ucpb_loan' => ['decimal', 12, false],
                'meds_payable' => ['decimal', 12, false],
                'sss_deduction' => ['decimal', 12, false],
                'salary_adjustment' => ['decimal', 12, true],
                'hazard_adjustment' => ['decimal', 12, true],
                'pera_adjustment' => ['decimal', 12, true],
                'subsistence_adjustment' => ['decimal', 12, true],
                'laundry_adjustment' => ['decimal', 12, true],
                'withholding_tax' => ['decimal', 12, true],
                'withholding_tax_adjustment' => ['decimal', 12, true],
                'phic_contri_adj' => ['decimal', 12, true],
                'phic_refund_from_eo' => ['decimal', 12, true],
                'sss_adjustment' => ['decimal', 12, true],
                'gsis_ps_lwop_2pct' => ['decimal', 12, true],
                'gsis_gs_lwop_2pct' => ['decimal', 12, true],
                'gsis_plreg_refund' => ['decimal', 12, true],
                'gsis_mpl_refund' => ['decimal', 12, true],
                'gsis_emergency_refund' => ['decimal', 12, true],
                'gsis_consoloan_refund' => ['decimal', 12, true],
                'subsistence_deduction_from_leave' => ['decimal', 12, true],
                'second_half_adjustment_resigned_employee' => ['decimal', 12, true],
            ];

            foreach ($columns as $column => $meta) {
                if (!Schema::hasColumn('payroll_employees', $column)) {
                    if ($meta[2]) {
                        $table->decimal($column, $meta[1])->nullable();
                    } else {
                        $table->decimal($column, $meta[1])->default(0);
                    }
                }
            }
        });
    }
};

