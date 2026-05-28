<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // group_id lets one approval chain cover all leave types in a batch filing
        if (!Schema::hasColumn('leave_application_approvals', 'group_id')) {
            Schema::table('leave_application_approvals', function (Blueprint $table) {
                $table->string('group_id', 36)->nullable()->after('leave_application_id')->index();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('leave_application_approvals', 'group_id')) {
            Schema::table('leave_application_approvals', function (Blueprint $table) {
                $table->dropColumn('group_id');
            });
        }
    }
};
