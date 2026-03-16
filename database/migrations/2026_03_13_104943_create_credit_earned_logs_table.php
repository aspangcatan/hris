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
        Schema::create('credit_earned_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_id');
            $table->integer('month');
            $table->year('year');
            $table->integer('total_present')->nullable();
            $table->integer('total_lateundertime_minutes');
            $table->integer('leave_type_id');
            $table->decimal('earned_points', 8, 3);
            $table->decimal('deducted_points', 8, 3)->nullable();
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
};
