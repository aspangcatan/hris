<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePassSipsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pass_slips', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // use unsignedBigInteger for foreign keys
            $table->date('slip_date');
            $table->time('time_out');
            $table->time('time_in')->nullable(); // allow null until the employee returns
            $table->enum('type', ['official', 'personal']);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('approver_id')->nullable(); // make nullable since not approved yet
            $table->dateTime('approved_at')->nullable(); // also nullable
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
        Schema::dropIfExists('pass_slips');
    }
}
