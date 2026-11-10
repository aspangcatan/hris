<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agency (HR) weekend rule as an LWOP Settings option, beside CSC Sec. 33:
 *   rest_day_rule = long_runs  (default, unchanged) | after_lwop
 *   after_lwop: rest days right after a filed whole-day LWOP count as LWOP
 *   (Fri LWOP = 3 days, Mon LWOP = 1, Fri + Mon = 4) - LWOP days and earned
 *   credits only, never pay; unexcused absences never pull rest days in.
 *   after_lwop_includes_holidays: holidays right after the LWOP count too.
 * Existing rule sets keep long_runs, so nothing changes until HR adds a dated
 * rule set that uses after_lwop.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('lwop_rule_sets', function (Blueprint $table) {
            $table->string('rest_day_rule', 20)->default('long_runs')->after('count_rest_days_in_long_runs');
            $table->boolean('after_lwop_includes_holidays')->default(true)->after('rest_day_rule');
        });
    }

    public function down()
    {
        Schema::table('lwop_rule_sets', function (Blueprint $table) {
            $table->dropColumn(['rest_day_rule', 'after_lwop_includes_holidays']);
        });
    }
};
