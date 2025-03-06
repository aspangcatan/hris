<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCreditEarnedLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('credit_earned_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('month');
            $table->year('year');
            $table->integer('total_late_minutes');
            $table->integer('total_undertime_minutes');
            $table->integer('leave_type_id');
            $table->decimal('earned_points',8,2);
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
        Schema::dropIfExists('credit_earned_logs');
    }
}
