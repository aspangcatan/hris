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
        Schema::create('queue_referrals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('referring_hospital');
            $table->string('patient');
            $table->string('age')->nullable();
            $table->string('gender')->nullable();
            $table->string('department')->nullable();
            $table->integer('queued_by');
            $table->string('remarks')->nullable();
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
        Schema::dropIfExists('queue_referrals');
    }
};
