<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE payroll_approvals CHANGE role label VARCHAR(255) NOT NULL");
    }

    public function down()
    {
        DB::statement("ALTER TABLE payroll_approvals CHANGE label role ENUM('SH','DH','HR') NOT NULL");
    }
};
