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
        Schema::create('payroll_employee_components', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('payroll_employee_id')->index();
            $table->unsignedBigInteger('payroll_component_id')->nullable()->index();
            $table->string('code', 100);
            $table->string('name');
            $table->enum('type', ['earning', 'deduction']);
            $table->boolean('taxable')->default(false);
            $table->decimal('amount', 12)->default(0);
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
        Schema::dropIfExists('payroll_employee_components');
    }
};
