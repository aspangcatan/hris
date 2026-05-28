<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('leave_balance_snapshots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('year');
            $table->tinyInteger('quarter');
            $table->integer('division_id')->nullable();
            $table->integer('section_id')->nullable();
            $table->string('division_name')->nullable();
            $table->string('section_name')->nullable();
            $table->integer('generated_by');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('leave_balance_snapshots');
    }
};
