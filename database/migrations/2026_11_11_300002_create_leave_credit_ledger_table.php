<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only history of every leave balance change. leave_credits stays as the
 * convenient current balance and must reconcile against this ledger;
 * credit_earned_logs stays as the one-posting-per-month guard.
 *
 * effective_date is the date the change applies to (e.g. the leave date);
 * transaction_date is when it was recorded.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('leave_credit_ledger', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->integer('employee_id');
            $table->integer('leave_type_id');
            $table->unsignedBigInteger('cutoff_id')->nullable();
            $table->string('transaction_type', 30);
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('beginning_balance', 10, 3)->default(0);
            $table->decimal('earned_credits', 10, 3)->default(0);
            $table->decimal('consumed_credits', 10, 3)->default(0);
            $table->decimal('adjustments', 10, 3)->default(0);
            $table->decimal('ending_balance', 10, 3)->default(0);
            $table->string('computation_reference')->nullable();
            $table->date('effective_date');
            $table->dateTime('transaction_date');
            $table->integer('finalized_by')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['source_type', 'source_id', 'leave_type_id', 'transaction_type'], 'leave_ledger_source_unique');
            $table->index(['employee_id', 'leave_type_id', 'effective_date'], 'leave_ledger_employee_date_index');
            $table->foreign('cutoff_id', 'leave_ledger_cutoff_fk')->references('id')->on('cutoff_summaries_master');
        });
    }

    public function down()
    {
        Schema::dropIfExists('leave_credit_ledger');
    }
};
