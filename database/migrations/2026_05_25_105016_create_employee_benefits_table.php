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
        Schema::create('employee_benefits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('userid');
            $table->decimal('pera', 12);
            $table->decimal('rata', 12);
            $table->decimal('subsistence', 12);
            $table->decimal('laundry', 12);
            $table->decimal('longevity', 12);
            $table->decimal('cellphone_allowance', 12);
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
        Schema::dropIfExists('employee_benefits');
    }
};
