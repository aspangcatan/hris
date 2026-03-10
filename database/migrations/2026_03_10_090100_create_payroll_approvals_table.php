<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePayrollApprovalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('payroll_approvals')) {
            return;
        }

        Schema::create('payroll_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payroll_id');
            $table->unsignedBigInteger('approver_id');
            $table->enum('role', ['SH', 'DH', 'HR']);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->unsignedTinyInteger('order_no');
            $table->timestamps();

            $table->foreign('payroll_id')->references('id')->on('payrolls')->onDelete('cascade');
            $table->index(['payroll_id', 'role']);
            $table->index(['status', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payroll_approvals');
    }
}
