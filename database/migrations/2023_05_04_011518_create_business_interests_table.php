<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBusinessInterestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('business_interests', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('enterprise');
            $table->string('address');
            $table->string('nature');
            $table->date('date_acquisition');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('business_interests');
    }
}
