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
        Schema::create('cutoff_summaries_master', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->string('title', 150)->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->integer('generated_by')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['period_start', 'period_end'], 'cutoff_summaries_master_period_index');
            $table->unique(['period_start', 'period_end', 'title'], 'cutoff_summaries_master_period_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cutoff_summaries_master');
    }
};
