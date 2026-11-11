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
        Schema::create('leave_types', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->string('description', 45);
            $table->string('code', 45);
            $table->integer('monthly_earned');
            $table->string('status', 45)->nullable();
            $table->integer('days_prior')->nullable();
            $table->integer('commutation')->nullable();
            $table->decimal('default_credit', 10, 3)->nullable();
            $table->integer('monetize')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('leave_types');
    }
};
