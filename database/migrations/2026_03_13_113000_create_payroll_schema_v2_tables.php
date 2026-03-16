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
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('payroll_type')->nullable();
            $table->string('rate_type')->nullable();
            $table->string('status')->default('draft');
            $table->integer('prepared_by')->nullable();
            $table->integer('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_employee_summaries', function (Blueprint $table) {
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

        Schema::create('payroll_compensation_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code')->unique('payroll_comp_types_code_unique');
            $table->string('name');
            $table->string('kind');
            $table->boolean('taxable')->default(false);
            $table->decimal('default_amount', 12)->nullable();
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('employee_compensation_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('employee_id')->index('employee_comp_settings_employee_id_idx');
            $table->unsignedBigInteger('compensation_type_id')->index('employee_comp_settings_type_id_idx');
            $table->decimal('amount', 12)->default(0);
            $table->boolean('active')->default(true);
            $table->date('effective_start')->nullable();
            $table->date('effective_end')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(
                ['employee_id', 'compensation_type_id', 'effective_start'],
                'employee_comp_settings_unique'
            );
        });

        Schema::create('payroll_employee_compensations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('payroll_employee_summary_id')
                ->index('payroll_employee_compensations_summary_id_idx');
            $table->unsignedBigInteger('compensation_type_id')
                ->index('payroll_employee_compensations_type_id_idx');
            $table->decimal('amount', 12)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(
                ['payroll_employee_summary_id', 'compensation_type_id'],
                'payroll_employee_compensations_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payroll_employee_compensations');
        Schema::dropIfExists('employee_compensation_settings');
        Schema::dropIfExists('payroll_compensation_types');
        Schema::dropIfExists('payroll_employee_summaries');
        Schema::dropIfExists('payroll_periods');
    }
};
