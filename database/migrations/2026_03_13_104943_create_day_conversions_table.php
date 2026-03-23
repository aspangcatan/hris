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
        if (Schema::hasTable('day_conversions')) { return; }
        Schema::create('day_conversions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('days_present');
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
};

