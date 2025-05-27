<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmployeeDeductionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('employee_deductions', function (Blueprint $table) {
            $table->id();
            $table->integer('userid');
            $table->decimal('gsis_ps',12,2);
            $table->decimal('gsis_gs',12,2);
            $table->decimal('gsis_ec',12,2);
            $table->decimal('hdmf',12,2);
            $table->decimal('phic',12,2);
            $table->decimal('pagibig_cal',12,2);
            $table->decimal('pagibig_mpl',12,2);
            $table->decimal('pagibig_mp2',12,2);
            $table->decimal('pagibig_mp2_2nd',12,2);
            $table->decimal('pagibig_mp2_3rd',12,2);
            $table->decimal('gsis_consoloan',12,2);
            $table->decimal('gsis_emergency_loan',12,2);
            $table->decimal('gsis_educational_loan',12,2);
            $table->decimal('gsis_policy_loan',12,2);
            $table->decimal('gsis_opt_life_loan',12,2);
            $table->decimal('gsis_gfal',12,2);
            $table->decimal('gsis_mpl',12,2);
            $table->decimal('gsis_cpl',12,2);
            $table->decimal('gsis_mpl_lite',12,2);
            $table->decimal('cfi_coop',12,2);
            $table->decimal('first_valley_bank',12,2);
            $table->decimal('tdhea',12,2);
            $table->decimal('dbp_loan',12,2);
            $table->decimal('ucpb_loan',12,2);
            $table->decimal('meds_payable',12,2);
            $table->decimal('sss_deduction',12,2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('employee_deductions');
    }
}
