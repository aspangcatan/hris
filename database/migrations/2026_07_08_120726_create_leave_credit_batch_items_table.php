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
        Schema::create('leave_credit_batch_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('batch_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('designation_name')->nullable();
            $table->string('division_name')->nullable();
            $table->string('section_name')->nullable();
            $table->string('employee_type')->nullable();
            $table->unsignedBigInteger('leave_type_id');
            $table->string('leave_type_code');
            $table->decimal('credits_added', 10, 3);
            $table->decimal('balance_after', 10, 3);
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
        Schema::dropIfExists('leave_credit_batch_items');
    }
};
