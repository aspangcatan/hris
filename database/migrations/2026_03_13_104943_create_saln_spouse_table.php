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
        if (Schema::hasTable('saln_spouse')) { return; }
        Schema::create('saln_spouse', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_id');
            $table->string('position')->nullable();
            $table->string('agency')->nullable();
            $table->string('office')->nullable();
            $table->string('government_id_issued')->nullable();
            $table->string('government_id_no')->nullable();
            $table->date('date_issued')->nullable();
            $table->date('compliance_for')->nullable();
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
        Schema::dropIfExists('saln_spouse');
    }
};

