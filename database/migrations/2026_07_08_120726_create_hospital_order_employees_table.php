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
        Schema::create('hospital_order_employees', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('ho_id');
            $table->integer('userid');
            $table->date('filed_date')->nullable();
            $table->time('time_from')->nullable();
            $table->time('time_to')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('remarks', 500)->nullable();
            $table->integer('reviewed_by')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['ho_id', 'userid', 'filed_date', 'time_from', 'time_to'], 'ho_emp_file_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('hospital_order_employees');
    }
};
