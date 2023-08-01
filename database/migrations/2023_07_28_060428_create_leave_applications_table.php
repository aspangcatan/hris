<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeaveApplicationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('leave_applications', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('leave_type',150);
            $table->string('detail',150);
            $table->string('extra',150)->nullable();
            $table->text('date_applied');
            $table->string('commutation',50);
            $table->string('status',30);
            $table->integer('section_id');
            $table->dateTime('date_section_approved')->nullable();
            $table->integer('division_id');
            $table->dateTime('date_mcc_approved')->nullable();
            $table->integer('hr_id');
            $table->dateTime('date_hr_approved')->nullable();
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
        Schema::dropIfExists('leave_applications');
    }
}
