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
        Schema::create('leave_balance_snapshot_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('snapshot_id')->index('leave_balance_snapshot_details_snapshot_id_foreign');
            $table->integer('user_id');
            $table->integer('leave_type_id');
            $table->string('fname');
            $table->string('mname')->nullable();
            $table->string('lname');
            $table->string('division_name')->nullable();
            $table->string('section_name')->nullable();
            $table->string('leave_type_name');
            $table->decimal('based_credit', 10, 3)->default(0);
            $table->decimal('used_credit', 10, 3)->default(0);
            $table->decimal('balance', 10, 3)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('leave_balance_snapshot_details');
    }
};
