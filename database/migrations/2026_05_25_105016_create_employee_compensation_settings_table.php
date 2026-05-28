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
        Schema::create('employee_compensation_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('employee_id')->index('employee_comp_settings_employee_id_idx');
            $table->unsignedBigInteger('compensation_type_id')->index('employee_comp_settings_type_id_idx');
            $table->decimal('amount', 12)->default(0);
            $table->boolean('active')->default(true);
            $table->date('effective_start')->nullable();
            $table->date('effective_end')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'compensation_type_id', 'effective_start'], 'employee_comp_settings_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('employee_compensation_settings');
    }
};
