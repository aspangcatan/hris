<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Adds the HR_COMPLIANCE role used by the leave HR compliance-check
     * step - see
     * docs/superpowers/specs/2026-07-21-leave-hr-compliance-step-design.md.
     * It is inserted ahead of SH only for applications that have
     * attachments; applications without attachments keep the existing
     * SH -> DH -> HR chain untouched.
     */
    public function up()
    {
        DB::statement("ALTER TABLE leave_application_approvals MODIFY role ENUM('HR_COMPLIANCE', 'SH', 'DH', 'HR') NOT NULL");
    }

    public function down()
    {
        DB::statement("DELETE FROM leave_application_approvals WHERE role = 'HR_COMPLIANCE'");
        DB::statement("ALTER TABLE leave_application_approvals MODIFY role ENUM('SH', 'DH', 'HR') NOT NULL");
    }
};
