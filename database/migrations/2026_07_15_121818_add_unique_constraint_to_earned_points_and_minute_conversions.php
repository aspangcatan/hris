<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUniqueConstraintToEarnedPointsAndMinuteConversions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('monthly_earned_points', function (Blueprint $table) {
            $table->unique('present', 'monthly_earned_points_present_unique');
        });

        Schema::table('minute_conversions', function (Blueprint $table) {
            $table->unique('minute', 'minute_conversions_minute_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('monthly_earned_points', function (Blueprint $table) {
            $table->dropUnique('monthly_earned_points_present_unique');
        });

        Schema::table('minute_conversions', function (Blueprint $table) {
            $table->dropUnique('minute_conversions_minute_unique');
        });
    }
}
