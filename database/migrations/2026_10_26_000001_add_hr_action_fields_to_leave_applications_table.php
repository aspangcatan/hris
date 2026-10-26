<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // CSC Form 6 section 7, filled in by HR on acknowledgement:
        // 7.A "As of" date, 7.C days with pay / without pay / others (specify).
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->date('cert_as_of')->nullable()->after('is_lwop');
            $table->decimal('approved_days_with_pay', 6, 3)->nullable()->after('cert_as_of');
            $table->decimal('approved_days_without_pay', 6, 3)->nullable()->after('approved_days_with_pay');
            $table->string('approved_others', 255)->nullable()->after('approved_days_without_pay');
        });
    }

    public function down()
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropColumn(['cert_as_of', 'approved_days_with_pay', 'approved_days_without_pay', 'approved_others']);
        });
    }
};
