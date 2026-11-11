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
        Schema::create('leave_cancellation_dates', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('leave_cancellation_id')->index('leave_cancellation_dates_leave_cancellation_id_foreign');
            $table->unsignedBigInteger('leave_date_applied_id');
            $table->date('date_applied');
            $table->unsignedInteger('hours');
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
        Schema::dropIfExists('leave_cancellation_dates');
    }
};
