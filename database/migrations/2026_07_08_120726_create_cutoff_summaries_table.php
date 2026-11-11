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
        Schema::create('cutoff_summaries', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cutoff_id')->nullable()->index('hris_cutoff_summaries_cutoff_id_index');
            $table->integer('employee_id')->index();
            $table->date('period_start');
            $table->date('period_end');
            $table->integer('total_working_days')->default(0);
            $table->integer('days_present')->default(0);
            $table->integer('days_absent')->default(0);
            $table->integer('late_minutes')->default(0);
            $table->integer('undertime_minutes')->default(0);
            $table->integer('approved_overtime_minutes')->default(0);
            $table->integer('approved_pass_slips_minutes')->default(0);
            $table->integer('approved_leave_days')->default(0);
            $table->integer('hospital_order_days')->default(0);
            $table->integer('generated_by')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->integer('locked_by')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'cutoff_id'], 'cutoff_summaries_employee_cutoff_unique');
            $table->index(['period_start', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cutoff_summaries');
    }
};
