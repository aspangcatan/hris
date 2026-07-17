<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persists assignatory signatures per leave application - see
     * docs/superpowers/specs/2026-07-17-leave-assignatory-signatures-design.md.
     *
     * applicant_signature is captured at filing and re-captured on every
     * edit while the application is still pending SH approval. signature on
     * leave_application_approvals is only ever set for the SH and DH rows
     * when their status transitions to approved - HR never signs.
     */
    public function up()
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->longText('applicant_signature')->nullable()->after('commutation');
        });

        Schema::table('leave_application_approvals', function (Blueprint $table) {
            $table->longText('signature')->nullable()->after('remarks');
        });
    }

    public function down()
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropColumn('applicant_signature');
        });

        Schema::table('leave_application_approvals', function (Blueprint $table) {
            $table->dropColumn('signature');
        });
    }
};
