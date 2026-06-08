<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('overtime_request_approvals', function (Blueprint $table) {
            // Drop the non-unique compound index, replace with unique
            $table->dropIndex(['overtime_request_id', 'role']);
            $table->unique(['overtime_request_id', 'role'], 'ot_approvals_request_role_unique');
        });
    }

    public function down()
    {
        Schema::table('overtime_request_approvals', function (Blueprint $table) {
            $table->dropUnique('ot_approvals_request_role_unique');
            $table->index(['overtime_request_id', 'role']);
        });
    }
};
