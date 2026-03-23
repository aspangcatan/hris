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
        if (!Schema::hasTable('payroll_components')) {
            Schema::create('payroll_components', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('code', 100)->unique();
                $table->string('name');
                $table->enum('type', ['earning', 'deduction'])->index();
                $table->boolean('taxable')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('employee_payroll_components')) {
            Schema::create('employee_payroll_components', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('payroll_component_id')->index();
                $table->integer('userid')->index();
                $table->decimal('amount', 12)->default(0);
                $table->boolean('is_active')->default(true);
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['payroll_component_id', 'userid'], 'employee_component_unique');
            });
        }

        if (!Schema::hasTable('payroll_employee_components')) {
            Schema::create('payroll_employee_components', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('payroll_employee_id')->index();
                $table->unsignedBigInteger('payroll_component_id')->index();
                $table->string('code', 100);
                $table->string('name');
                $table->enum('type', ['earning', 'deduction']);
                $table->boolean('taxable')->default(false);
                $table->decimal('amount', 12)->default(0);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payroll_employee_components');
        Schema::dropIfExists('employee_payroll_components');
        Schema::dropIfExists('payroll_components');
    }
};


