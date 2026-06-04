<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeaveApplicationAttachmentsTable extends Migration
{
    public function up()
    {
        Schema::create('leave_application_attachments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('leave_application_id');
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->timestamps();

            $table->foreign('leave_application_id')
                ->references('id')->on('leave_applications')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('leave_application_attachments');
    }
}
