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
        if (Schema::hasTable('leave_application_logs')) { return; }
        Schema::create('leave_application_logs', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('leave_application_id');
            $table->string('origin', 45);
            $table->string('destination', 45);
            $table->string('remarks', 45);
            $table->dateTime('date_action_taken')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('leave_application_logs');
    }
};

