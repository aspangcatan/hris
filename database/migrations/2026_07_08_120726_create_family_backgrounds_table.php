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
        Schema::create('family_backgrounds', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->integer('user_id');
            $table->string('spouse_fname')->nullable();
            $table->string('spouse_mname')->nullable();
            $table->string('spouse_lname')->nullable();
            $table->string('spouse_ext_name')->nullable();
            $table->string('spouse_occupation')->nullable();
            $table->string('spouse_employer')->nullable();
            $table->string('spouse_business_address')->nullable();
            $table->string('spouse_contact_no')->nullable();
            $table->string('father_fname')->nullable();
            $table->string('father_mname')->nullable();
            $table->string('father_lname')->nullable();
            $table->string('father_ext_name')->nullable();
            $table->string('mother_fname')->nullable();
            $table->string('mother_mname')->nullable();
            $table->string('mother_lname')->nullable();
            $table->string('mother_ext_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('family_backgrounds');
    }
};
