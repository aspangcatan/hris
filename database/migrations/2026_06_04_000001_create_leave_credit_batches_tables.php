<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeaveCreditBatchesTables extends Migration
{
    public function up()
    {
        Schema::create('leave_credit_batches', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('created_by');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_credit_batch_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('batch_id');
            $table->unsignedBigInteger('user_id');
            $table->string('firstname');
            $table->string('lastname');
            $table->string('designation_name')->nullable();
            $table->string('division_name')->nullable();
            $table->string('section_name')->nullable();
            $table->string('employee_type')->nullable();
            $table->unsignedBigInteger('leave_type_id');
            $table->string('leave_type_code');
            $table->decimal('credits_added', 10, 3);
            $table->decimal('balance_after', 10, 3);
            $table->timestamps();

            $table->foreign('batch_id')
                ->references('id')->on('leave_credit_batches')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('leave_credit_batch_items');
        Schema::dropIfExists('leave_credit_batches');
    }
}
