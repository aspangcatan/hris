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
        Schema::create('payroll_employees', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('payroll_id')->index('payroll_employees_payroll_id_foreign');
            $table->integer('employee_id');
            $table->integer('division');
            $table->integer('section');
            $table->decimal('basic_salary', 12)->default(0);
            $table->decimal('other_earnings', 12)->default(0);
            $table->decimal('late_absent', 12)->nullable();
            $table->decimal('gross_pay', 12)->default(0);
            $table->decimal('other_deductions', 12)->default(0);
            $table->decimal('deductions', 12)->default(0);
            $table->decimal('net_pay', 12)->default(0);
            $table->timestamps();
            $table->decimal('first_half_10th', 12)->nullable()->default(0);
            $table->decimal('second_half_25th', 12)->nullable()->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payroll_employees');
    }
};
