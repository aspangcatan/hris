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
        if (Schema::hasTable('eligibilities')) { return; }
        Schema::create('eligibilities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_id');
            $table->string('career_service');
            $table->text('rating')->nullable();
            $table->date('examination_date')->nullable();
            $table->date('examination_end_date')->nullable();
            $table->string('examination_place')->nullable();
            $table->string('license_no')->nullable();
            $table->date('license_expiry')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('eligibilities');
    }
};

