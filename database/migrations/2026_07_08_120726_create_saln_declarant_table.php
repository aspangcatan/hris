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
        Schema::create('saln_declarant', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_id');
            $table->string('position');
            $table->string('agency');
            $table->string('office');
            $table->string('government_id_issued');
            $table->string('government_id_no');
            $table->date('date_issued')->nullable();
            $table->date('compliance_for')->nullable();
            $table->string('compliance_type', 80)->nullable();
            $table->enum('filing_type', ['Joint Filing', 'Separate Filing', 'Not Applicable'])->default('Not Applicable');
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
        Schema::dropIfExists('saln_declarant');
    }
};
