<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeaveApplicationApprovalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('leave_application_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('leave_application_id');
            $table->unsignedBigInteger('approver_id');

            // Role of approver: section head, division head, HR
            $table->enum('role', ['section_head', 'division_head', 'hr']);

            // Approval status
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->text('remarks')->nullable();
            $table->timestamp('approved_at')->nullable();

            // Order for step wizard display
            $table->unsignedTinyInteger('order_no');

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
        Schema::dropIfExists('leave_application_approvals');
    }
}
