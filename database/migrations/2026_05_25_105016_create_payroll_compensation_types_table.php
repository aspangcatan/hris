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
        Schema::create('payroll_compensation_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 100)->unique('payroll_comp_types_code_unique');
            $table->string('name');
            $table->string('kind');
            $table->boolean('taxable')->default(false);
            $table->decimal('default_amount', 12)->nullable();
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
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
        Schema::dropIfExists('payroll_compensation_types');
    }
};
