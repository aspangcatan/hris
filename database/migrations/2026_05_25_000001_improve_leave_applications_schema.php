<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Make commutation nullable (service was storing '' as workaround)
        // Note: 'detail' column does not exist on this table; skip it.
        if (Schema::hasColumn('leave_applications', 'detail')) {
            DB::statement('ALTER TABLE leave_applications MODIFY detail VARCHAR(150) NULL');
        }
        if (Schema::hasColumn('leave_applications', 'commutation')) {
            DB::statement('ALTER TABLE leave_applications MODIFY commutation VARCHAR(50) NULL');
        }

        // Add group_id to link multiple leave-type applications filed in one batch
        if (!Schema::hasColumn('leave_applications', 'group_id')) {
            Schema::table('leave_applications', function (Blueprint $table) {
                $table->string('group_id', 36)->nullable()->after('leave_id')->index();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('leave_applications', 'detail')) {
            DB::statement("UPDATE leave_applications SET detail = '' WHERE detail IS NULL");
            DB::statement('ALTER TABLE leave_applications MODIFY detail VARCHAR(150) NOT NULL');
        }
        if (Schema::hasColumn('leave_applications', 'commutation')) {
            DB::statement("UPDATE leave_applications SET commutation = '' WHERE commutation IS NULL");
            DB::statement('ALTER TABLE leave_applications MODIFY commutation VARCHAR(50) NOT NULL');
        }

        if (Schema::hasColumn('leave_applications', 'group_id')) {
            Schema::table('leave_applications', function (Blueprint $table) {
                $table->dropColumn('group_id');
            });
        }
    }
};
