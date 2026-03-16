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
        Schema::create('work_experiences', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_id');
            $table->date('working_from');
            $table->date('working_to');
            $table->string('position');
            $table->string('agency');
            $table->decimal('monthly_salary', 10)->nullable();
            $table->string('salary_grade')->nullable();
            $table->string('appointment_status');
            $table->integer('is_government');
            $table->integer('is_current_work');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('work_experiences');
    }
};
