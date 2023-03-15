<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServiceRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('service_records', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->date('date_from');
            $table->date('date_to');
            $table->integer('current_work');
            $table->string('designation');
            $table->string('appointment_status');
            $table->string('monthly_salary');
            $table->string('office_assignment');
            $table->string('leave_absence')->nullable();
            $table->string('separation')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('service_records');
    }
}
