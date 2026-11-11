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
        Schema::create('leave_date_applied', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->integer('leave_application_id');
            $table->unsignedBigInteger('leave_type_id')->nullable();
            $table->date('date_applied');
            $table->integer('hours');
            $table->enum('status', ['active', 'cancelled'])->default('active');
            $table->decimal('credits_deducted', 10, 3)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('leave_date_applied');
    }
};
