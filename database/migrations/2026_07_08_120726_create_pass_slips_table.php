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
        Schema::create('pass_slips', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->integer('user_id')->nullable();
            $table->date('slip_date')->nullable();
            $table->time('time_out')->nullable();
            $table->time('time_in')->nullable();
            $table->enum('type', ['official', 'personal'])->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->nullable()->default('pending');
            $table->integer('approver_id')->nullable();
            $table->dateTime('approved_at')->nullable();
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
        Schema::dropIfExists('pass_slips');
    }
};
