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
        Schema::create('related_work', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('work_id');
            $table->string('duration');
            $table->string('position');
            $table->string('office_unit');
            $table->string('supervisor');
            $table->string('agency');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('related_work');
    }
};
