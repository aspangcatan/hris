<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('payroll_approvals')) { return; }
        Schema::create('payroll_approvals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('payroll_id');
            $table->string('role', 50);
            $table->unsignedInteger('approver_id')->nullable();
            $table->unsignedInteger('order_no')->nullable();
            $table->string('status', 50)->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->index('payroll_id');
            $table->index(['payroll_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('payroll_approvals');
    }
};
