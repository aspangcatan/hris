<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDayConversionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('day_conversions', function (Blueprint $table) {
            $table->id();
            $table->integer('days_present', 5, 3);
            $table->decimal('days_on_leave_without_pay', 5, 3);
            $table->decimal('leave_credits_earned', 6, 3);
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
        Schema::dropIfExists('day_conversions');
    }
}
