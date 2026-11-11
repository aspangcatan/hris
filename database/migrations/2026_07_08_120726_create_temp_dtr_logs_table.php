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
        Schema::create('temp_dtr_logs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->integer('user_id');
            $table->integer('month');
            $table->integer('year');
            $table->integer('absent');
            $table->decimal('late', 10);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('temp_dtr_logs');
    }
};
