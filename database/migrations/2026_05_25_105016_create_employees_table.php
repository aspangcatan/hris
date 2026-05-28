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
        Schema::create('employees', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_id')->unique('user_id_UNIQUE');
            $table->date('birthdate')->nullable();
            $table->string('birthplace')->nullable();
            $table->string('sex')->nullable();
            $table->string('civil_status')->nullable();
            $table->decimal('height', 10)->nullable();
            $table->decimal('weight')->nullable();
            $table->string('blood_type')->nullable();
            $table->string('gsis_id_no')->nullable();
            $table->string('umid_id_no')->nullable();
            $table->string('pagibig_id_no')->nullable();
            $table->string('philhealth_no')->nullable();
            $table->string('sss_no')->nullable();
            $table->string('philsys_no')->nullable();
            $table->string('tin_no')->nullable();
            $table->string('employee_no')->nullable();
            $table->string('citizenship')->nullable();
            $table->string('citizenship_type')->nullable();
            $table->string('citizenship_country')->nullable();
            $table->string('telephone_no')->nullable();
            $table->string('mobile_no')->nullable();
            $table->string('email_address')->nullable();
            $table->date('date_hired')->nullable();
            $table->date('date_resigned')->nullable();
            $table->string('employee_type')->nullable();
            $table->string('atm_type')->nullable();
            $table->string('issued_id')->nullable();
            $table->string('issued_id_no')->nullable();
            $table->string('issued_date')->nullable();
            $table->string('emergency_name')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->string('emergency_address')->nullable();
            $table->string('id_status')->nullable();
            $table->string('signature_status')->nullable();
            $table->date('date_submitted')->nullable();
            $table->timestamp('date_updated')->useCurrentOnUpdate()->nullable();
            $table->timestamp('date_created')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('employees');
    }
};
