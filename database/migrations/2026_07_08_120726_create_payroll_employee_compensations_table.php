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
        Schema::create('payroll_employee_compensations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('payroll_employee_summary_id')->index('payroll_employee_compensations_summary_id_idx');
            $table->unsignedBigInteger('compensation_type_id')->index('payroll_employee_compensations_type_id_idx');
            $table->decimal('amount', 12)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['payroll_employee_summary_id', 'compensation_type_id'], 'payroll_employee_compensations_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payroll_employee_compensations');
    }
};
