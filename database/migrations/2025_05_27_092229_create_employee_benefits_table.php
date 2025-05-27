<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmployeeBenefitsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('employee_benefits', function (Blueprint $table) {
            $table->id();
            $table->integer('userid');
            $table->decimal('pera',12,2);
            $table->decimal('rata',12,2);
            $table->decimal('subsistence',12,2);
            $table->decimal('laundry',12,2);
            $table->decimal('hazard',12,2);
            $table->decimal('longevity',12,2);
            $table->decimal('cellphone_allowance',12,2);
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
}
