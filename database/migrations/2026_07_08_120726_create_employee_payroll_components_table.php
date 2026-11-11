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
        Schema::create('employee_payroll_components', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('payroll_component_id')->index();
            $table->integer('userid')->index();
            $table->decimal('amount', 12)->default(0);
            $table->decimal('amount_override', 12)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_excluded')->default(false);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['payroll_component_id', 'userid'], 'employee_component_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('employee_payroll_components');
    }
};
