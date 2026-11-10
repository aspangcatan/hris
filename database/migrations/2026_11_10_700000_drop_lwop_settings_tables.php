<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LWOP Settings was removed (client decision 2026-11-10): earned credits are
 * read from CSC Table III by filed and approved LWOP days only, so no weekend
 * or rule settings are needed. The migrations that created these tables were
 * removed from the repository before reaching production; this drops what
 * they created where they did run. On a database without them it does nothing.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('cutoff_summaries', 'lwop_rule_set_id')) {
            Schema::table('cutoff_summaries', function (Blueprint $table) {
                $table->dropColumn('lwop_rule_set_id');
            });
        }
        Schema::dropIfExists('lwop_weekend_cases');
        Schema::dropIfExists('lwop_rest_day_cases');
        Schema::dropIfExists('lwop_rule_sets');
    }

    public function down()
    {
        // Nothing to restore: the feature was removed.
    }
};
