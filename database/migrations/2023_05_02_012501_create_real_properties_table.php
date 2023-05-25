<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRealPropertiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('real_properties', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('description');
            $table->string('kind');
            $table->string('exact_location');
            $table->string('assessed_value');
            $table->string('current_fair');
            $table->year('acquisition_year');
            $table->string('acquisition_mode');
            $table->string('acquisition_cost');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('real_properties');
    }
}
