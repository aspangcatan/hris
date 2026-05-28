<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_cancellations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->text('reason');
            $table->string('status', 20)->default('Pending');
            $table->timestamps();
        });

        Schema::create('leave_cancellation_dates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('leave_cancellation_id');
            $table->unsignedBigInteger('leave_date_applied_id');
            $table->date('date_applied');
            $table->unsignedInteger('hours');
            $table->timestamps();

            $table->foreign('leave_cancellation_id')
                  ->references('id')->on('leave_cancellations')
                  ->onDelete('cascade');
        });

        Schema::create('leave_cancellation_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('leave_cancellation_id');
            $table->unsignedBigInteger('approver_id');
            $table->enum('role', ['SH', 'DH', 'HR']);
            $table->unsignedTinyInteger('order_no');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('leave_cancellation_id')
                  ->references('id')->on('leave_cancellations')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_cancellation_approvals');
        Schema::dropIfExists('leave_cancellation_dates');
        Schema::dropIfExists('leave_cancellations');
    }
};
