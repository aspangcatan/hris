<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('overtime_request_approvals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('overtime_request_id');
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->enum('role', ['SH', 'DH', 'HR']);
            $table->unsignedTinyInteger('order_no');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('overtime_request_id');
            $table->index(['overtime_request_id', 'role']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('overtime_request_approvals');
    }
};
