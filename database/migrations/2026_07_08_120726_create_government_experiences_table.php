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
        Schema::create('government_experiences', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->integer('user_id');
            $table->date('working_from');
            $table->date('working_to');
            $table->string('position');
            $table->string('agency');
            $table->decimal('monthly_salary', 10);
            $table->string('salary_grade')->nullable();
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
        Schema::dropIfExists('government_experiences');
    }
};
