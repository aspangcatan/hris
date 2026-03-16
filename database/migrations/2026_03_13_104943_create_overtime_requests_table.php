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
        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('userid');
            $table->date('ot_date');
            $table->time('time_from');
            $table->time('time_to');
            $table->string('reason', 500)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('remarks', 500)->nullable();
            $table->integer('reviewed_by')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['userid', 'ot_date', 'time_from', 'time_to'], 'ot_req_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('overtime_requests');
    }
};
