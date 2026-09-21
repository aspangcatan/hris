<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE leave_application_approvals MODIFY status ENUM('pending','approved','rejected','acknowledged') NOT NULL DEFAULT 'pending'");
    }

    public function down()
    {
        DB::table('leave_application_approvals')->where('status', 'acknowledged')->update(['status' => 'approved']);
        DB::statement("ALTER TABLE leave_application_approvals MODIFY status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
    }
};
