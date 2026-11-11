<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Late changes to a finalized or closed period (e.g. a leave approved or
 * cancelled afterwards). The past cutoff is never edited: the change is
 * recorded against it and applied in a later cutoff.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('cutoff_adjustments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->integer('employee_id');
            $table->unsignedBigInteger('source_cutoff_id');
            $table->unsignedBigInteger('target_cutoff_id')->nullable();
            $table->string('adjustment_type', 30);
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->date('adjustment_date');
            $table->decimal('days', 6, 3)->default(0);
            $table->decimal('amount', 12, 2)->nullable();
            $table->decimal('credits', 10, 3)->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('applied_by')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status'], 'cutoff_adj_employee_status_index');
            $table->index('source_cutoff_id', 'cutoff_adj_source_index');
            $table->index('target_cutoff_id', 'cutoff_adj_target_index');
            $table->foreign('source_cutoff_id', 'cutoff_adj_source_fk')->references('id')->on('cutoff_summaries_master');
            $table->foreign('target_cutoff_id', 'cutoff_adj_target_fk')->references('id')->on('cutoff_summaries_master');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cutoff_adjustments');
    }
};
