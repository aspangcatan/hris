<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSalnDeclarantTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('saln_declarant', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('position');
            $table->string('agency');
            $table->string('office');
            $table->string('government_id_issued');
            $table->string('government_id_no');
            $table->date('date_issued');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('saln_declarant');
    }
}
