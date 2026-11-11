<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cutoff_summary_days', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cutoff_id');
            $table->integer('employee_id');
            $table->date('date');
            $table->integer('late_minutes')->default(0);
            $table->integer('undertime_minutes')->default(0);
            $table->boolean('is_absent')->default(false);
            $table->boolean('is_holiday')->default(false);
            $table->string('exemption_reason', 20)->nullable();
            $table->timestamps();

            $table->unique(['cutoff_id', 'employee_id', 'date'], 'cutoff_summary_days_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cutoff_summary_days');
    }
};
