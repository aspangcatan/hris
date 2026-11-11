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
        Schema::create('dtr_logs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->integer('userid');
            $table->string('status', 10);
            $table->integer('type')->nullable()->default(1);
            $table->string('device', 50)->nullable();
            $table->integer('is_online')->nullable()->default(1);
            $table->string('photo_path')->nullable();
            $table->string('location', 100)->nullable();
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
        Schema::dropIfExists('dtr_logs');
    }
};
