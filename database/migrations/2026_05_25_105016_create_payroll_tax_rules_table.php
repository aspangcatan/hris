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
        Schema::create('payroll_tax_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('purpose', 150)->index('hris_payroll_tax_rules_purpose_index');
            $table->decimal('min_annual', 12)->default(0);
            $table->decimal('max_annual', 12)->nullable();
            $table->decimal('rate_percent', 5)->default(0);
            $table->enum('tax_mode', ['flat', 'progressive'])->default('flat');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('remarks')->nullable();
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
        Schema::dropIfExists('payroll_tax_rules');
    }
};
