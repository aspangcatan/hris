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
        Schema::create('schedule_master_lists', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('label', 50);
            $table->string('code', 10);
            $table->string('type', 20)->default('FIXED');
            $table->time('time_in');
            $table->time('time_in_latest');
            $table->time('time_out');
            $table->time('time_out_latest');
            $table->time('break_in')->nullable();
            $table->time('break_out')->nullable();
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
        Schema::dropIfExists('schedule_master_lists');
    }
};
