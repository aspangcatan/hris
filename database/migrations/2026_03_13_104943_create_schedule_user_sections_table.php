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
        if (Schema::hasTable('schedule_user_sections')) { return; }
        Schema::create('schedule_user_sections', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index('schedule_user_sections_user_id_idx');
            $table->unsignedBigInteger('section_id')->index('schedule_user_sections_section_id_idx');
            $table->timestamps();

            $table->unique(['user_id', 'section_id'], 'schedule_user_sections_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('schedule_user_sections');
    }
};

