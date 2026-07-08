<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DTRController::printDTR() and DTRServices have read schedule->break_out/
     * break_in for break-time deduction since at least commit e9880ed, but the
     * columns were never actually applied to this database - printDTR's
     * schedule query throws "Unknown column" for any employee with a
     * schedule assigned. Adding them now so that code path is reachable.
     */
    public function up()
    {
        Schema::table('schedule_master_lists', function (Blueprint $table) {
            $table->time('break_out')->nullable()->after('time_out_latest');
            $table->time('break_in')->nullable()->after('break_out');
        });
    }

    public function down()
    {
        Schema::table('schedule_master_lists', function (Blueprint $table) {
            $table->dropColumn(['break_out', 'break_in']);
        });
    }
};
