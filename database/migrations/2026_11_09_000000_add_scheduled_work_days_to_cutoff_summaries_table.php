<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The employee's own scheduled work days in the cutoff (non-OFF, non-holiday
 * plotted dates; weekdays minus holidays when nothing is plotted). Used by the
 * earned-credits computation so CWW/shift employees with perfect attendance
 * earn the full 1.250. Payroll keeps using total_working_days.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('cutoff_summaries', function (Blueprint $table) {
            $table->integer('scheduled_work_days')->default(0)->after('total_working_days');
        });
    }

    public function down()
    {
        Schema::table('cutoff_summaries', function (Blueprint $table) {
            $table->dropColumn('scheduled_work_days');
        });
    }
};
