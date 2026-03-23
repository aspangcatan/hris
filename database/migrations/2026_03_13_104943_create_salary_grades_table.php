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
        if (Schema::hasTable('salary_grades')) { return; }
        Schema::create('salary_grades', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('salary_grade');
            $table->integer('increment');
            $table->string('unique_key', 10)->unique();
            $table->decimal('rate', 10);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('salary_grades');
    }
};

