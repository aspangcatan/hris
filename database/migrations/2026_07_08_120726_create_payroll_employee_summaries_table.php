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
        Schema::create('payroll_employee_summaries', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('payroll_period_id')->index('payroll_employee_summaries_period_id_idx');
            $table->integer('employee_id')->index('payroll_employee_summaries_employee_id_idx');
            $table->unsignedBigInteger('cutoff_summary_id')->index('payroll_employee_summaries_cutoff_id_idx');
            $table->decimal('basic_salary', 12)->default(0);
            $table->decimal('allowances_total', 12)->default(0);
            $table->decimal('other_earnings_total', 12)->default(0);
            $table->decimal('gross_pay', 12)->default(0);
            $table->decimal('total_deductions', 12)->default(0);
            $table->decimal('net_pay', 12)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id'], 'payroll_employee_summaries_period_employee_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payroll_employee_summaries');
    }
};
