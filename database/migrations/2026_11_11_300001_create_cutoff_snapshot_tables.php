<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cutoff snapshot architecture, Phase 2: period-specific copies of employee,
 * leave, balance, pay and rule data. Values are copied, not referenced, because
 * the source rows can change or be deleted; source ids are for tracing only.
 *
 * employee_id has no foreign key: employees live in the tdh_user schema.
 */
return new class extends Migration
{
    private const MASTER = 'cutoff_summaries_master';

    public function up()
    {
        Schema::create('cutoff_employee_snapshots', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cutoff_id');
            $table->integer('employee_id');
            $table->string('employee_name');
            $table->string('employment_type', 50)->nullable();
            $table->string('employment_status', 50)->nullable();
            $table->integer('designation_id')->nullable();
            $table->string('position')->nullable();
            $table->integer('salary_grade')->nullable();
            $table->integer('salary_step')->nullable();
            $table->decimal('monthly_salary', 12, 2)->nullable();
            $table->date('salary_effective_date')->nullable();
            $table->string('salary_source', 30)->nullable();
            $table->integer('division_id')->nullable();
            $table->string('division_name')->nullable();
            $table->integer('section_id')->nullable();
            $table->string('section_name')->nullable();
            $table->date('appointment_date')->nullable();
            $table->date('separation_date')->nullable();
            $table->boolean('payroll_eligible')->default(false);
            $table->boolean('credit_eligible')->default(false);
            $table->string('exclusion_reason')->nullable();
            $table->timestamps();

            $table->unique(['cutoff_id', 'employee_id'], 'cutoff_emp_snap_unique');
            $table->index('employee_id', 'cutoff_emp_snap_employee_index');
            $table->foreign('cutoff_id', 'cutoff_emp_snap_cutoff_fk')->references('id')->on(self::MASTER);
        });

        Schema::create('cutoff_leave_snapshots', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cutoff_id');
            $table->integer('employee_id');
            $table->unsignedBigInteger('leave_application_id');
            $table->integer('leave_date_applied_id');
            $table->integer('leave_type_id')->nullable();
            $table->string('leave_type_code', 45)->nullable();
            $table->string('leave_type_name', 100)->nullable();
            $table->date('application_start_date')->nullable();
            $table->date('application_end_date')->nullable();
            $table->decimal('application_total_days', 6, 3)->nullable();
            $table->date('leave_date');
            $table->integer('hours')->default(0);
            $table->decimal('approved_days', 6, 3)->default(0);
            $table->decimal('paid_days', 6, 3)->default(0);
            $table->decimal('unpaid_days', 6, 3)->default(0);
            $table->boolean('is_lwop')->default(false);
            $table->string('approval_status', 30)->nullable();
            $table->string('date_status', 20)->nullable();
            $table->boolean('final_disapproved')->default(false);
            $table->boolean('charged_to_leave_credit')->default(false);
            $table->decimal('credits_deducted', 10, 3)->default(0);
            $table->integer('fallback_leave_type_id')->nullable();
            $table->decimal('fallback_credits', 10, 3)->default(0);
            $table->timestamps();

            $table->unique(['cutoff_id', 'leave_date_applied_id'], 'cutoff_leave_snap_unique');
            $table->index(['cutoff_id', 'employee_id', 'leave_date'], 'cutoff_leave_snap_emp_date_index');
            $table->foreign('cutoff_id', 'cutoff_leave_snap_cutoff_fk')->references('id')->on(self::MASTER);
        });

        Schema::create('cutoff_leave_balance_snapshots', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cutoff_id');
            $table->integer('employee_id');
            $table->integer('leave_type_id');
            $table->string('leave_type_code', 45)->nullable();
            $table->decimal('beginning_balance', 10, 3)->default(0);
            $table->decimal('leave_earned', 10, 3)->default(0);
            $table->decimal('leave_used', 10, 3)->default(0);
            $table->decimal('adjustments', 10, 3)->default(0);
            $table->decimal('ending_balance', 10, 3)->default(0);
            $table->string('opening_source', 30)->nullable();
            $table->timestamps();

            $table->unique(['cutoff_id', 'employee_id', 'leave_type_id'], 'cutoff_bal_snap_unique');
            $table->index(['employee_id', 'leave_type_id'], 'cutoff_bal_snap_employee_index');
            $table->foreign('cutoff_id', 'cutoff_bal_snap_cutoff_fk')->references('id')->on(self::MASTER);
        });

        // balance_before / balance_after stay null until a loan ledger exists.
        Schema::create('cutoff_payroll_components', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cutoff_id');
            $table->integer('employee_id');
            $table->unsignedBigInteger('payroll_component_id')->nullable();
            $table->string('code', 100);
            $table->string('name');
            $table->enum('type', ['earning', 'deduction']);
            $table->boolean('taxable')->default(false);
            $table->string('calculation_type', 20)->nullable();
            $table->string('base_type', 20)->nullable();
            $table->string('base_component_code', 100)->nullable();
            $table->decimal('rate_percent', 8, 3)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('computation_basis')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->decimal('balance_before', 12, 2)->nullable();
            $table->decimal('balance_after', 12, 2)->nullable();
            $table->unsignedBigInteger('source_reference_id')->nullable();
            $table->timestamps();

            $table->unique(['cutoff_id', 'employee_id', 'code'], 'cutoff_pay_comp_unique');
            $table->index('employee_id', 'cutoff_pay_comp_employee_index');
            $table->foreign('cutoff_id', 'cutoff_pay_comp_cutoff_fk')->references('id')->on(self::MASTER);
        });

        // Rule versions used by the cutoff: Table III, holidays, shifts, tax, pay divisor.
        Schema::create('cutoff_config_snapshots', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cutoff_id');
            $table->string('config_key', 50);
            $table->longText('payload');
            $table->char('payload_hash', 64);
            $table->timestamp('created_at')->nullable();

            $table->unique(['cutoff_id', 'config_key'], 'cutoff_config_snap_unique');
            $table->foreign('cutoff_id', 'cutoff_config_snap_cutoff_fk')->references('id')->on(self::MASTER);
        });
    }

    public function down()
    {
        Schema::dropIfExists('cutoff_config_snapshots');
        Schema::dropIfExists('cutoff_payroll_components');
        Schema::dropIfExists('cutoff_leave_balance_snapshots');
        Schema::dropIfExists('cutoff_leave_snapshots');
        Schema::dropIfExists('cutoff_employee_snapshots');
    }
};
