<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateScheduleUserSectionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('hris')->create('schedule_user_sections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('section_id');
            $table->timestamps();

            $table->unique(['user_id', 'section_id'], 'schedule_user_sections_unique');
            $table->index('user_id', 'schedule_user_sections_user_id_idx');
            $table->index('section_id', 'schedule_user_sections_section_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('hris')->dropIfExists('schedule_user_sections');
    }
}
