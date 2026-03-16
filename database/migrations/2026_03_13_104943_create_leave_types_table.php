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
        Schema::create('leave_types', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('description', 45);
            $table->string('code', 45);
            $table->decimal('monthly_points_earned', 10, 3);
            $table->decimal('minute_deduction', 10, 3);
            $table->decimal('day_deduction', 10, 3);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('leave_types');
    }
};
