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
        Schema::create('payrolls', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cutoff_id')->nullable()->index();
            $table->string('title');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('cutoff_start_date')->nullable();
            $table->date('cutoff_end_date')->nullable();
            $table->string('cutoff_title')->nullable();
            $table->string('payroll_type', 45)->nullable();
            $table->string('rate_type', 45)->nullable();
            $table->text('remarks')->nullable();
            $table->string('tax_purpose', 150)->nullable();
            $table->string('status', 50)->nullable();
            $table->integer('prepared_by');
            $table->integer('reviewed_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('released_at')->nullable();
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
        Schema::dropIfExists('payrolls');
    }
};
