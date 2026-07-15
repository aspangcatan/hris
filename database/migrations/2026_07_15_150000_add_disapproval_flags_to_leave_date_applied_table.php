<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets an approver disapprove specific dates within a leave application
     * instead of only the whole application - see
     * docs/superpowers/specs/2026-07-15-per-date-leave-disapproval-design.md.
     *
     * sh_flagged_disapprove/sh_disapproval_remark record the Supervisor's
     * suggestion and are never cleared by DH - they're what a DH "undo"
     * reverts back to. final_disapproved/dh_disapproval_remark are the
     * operative fields: only the Division Head sets them, and they drive
     * both credit restoration and the read-only "Disapproved" badge shown
     * elsewhere in the app.
     */
    public function up()
    {
        Schema::table('leave_date_applied', function (Blueprint $table) {
            $table->boolean('sh_flagged_disapprove')->default(false)->after('credits_deducted');
            $table->string('sh_disapproval_remark', 255)->nullable()->after('sh_flagged_disapprove');
            $table->boolean('final_disapproved')->default(false)->after('sh_disapproval_remark');
            $table->string('dh_disapproval_remark', 255)->nullable()->after('final_disapproved');
        });
    }

    public function down()
    {
        Schema::table('leave_date_applied', function (Blueprint $table) {
            $table->dropColumn(['sh_flagged_disapprove', 'sh_disapproval_remark', 'final_disapproved', 'dh_disapproval_remark']);
        });
    }
};
