<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LWOP Settings, simplified (user decision 2026-11-10): one list of weekend
 * cases. Saturday and Sunday are OFF; whether each counts as LWOP (instead of
 * present) is decided by the status of the Friday before and the Monday after
 * - LWOP / present / absent / paid_leave. A combination with no case = the
 * weekend counts as present. Holidays always count as present.
 *
 * Replaces the earlier rule-set design. Its migrations (2026_11_10_200000,
 * 200001, 300000, 400000) were removed from the repository before reaching
 * production; this drops what they created where they did run (local).
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
        Schema::dropIfExists('lwop_rest_day_cases');
        Schema::dropIfExists('lwop_rule_sets');

        Schema::create('lwop_weekend_cases', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('friday_status', 20);
            $table->string('monday_status', 20);
            $table->boolean('saturday_lwop')->default(false);
            $table->boolean('sunday_lwop')->default(false);
            $table->string('remarks', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['friday_status', 'monday_status'], 'lwop_weekend_cases_combo_unique');
        });

        // HR's examples: LWOP Fri + present Mon = 3 days; present Fri + LWOP Mon = 1; LWOP Fri + Mon = 4.
        $now = now();
        DB::table('lwop_weekend_cases')->insert([
            ['friday_status' => 'lwop', 'monday_status' => 'present', 'saturday_lwop' => true, 'sunday_lwop' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['friday_status' => 'present', 'monday_status' => 'lwop', 'saturday_lwop' => false, 'sunday_lwop' => false, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['friday_status' => 'lwop', 'monday_status' => 'lwop', 'saturday_lwop' => true, 'sunday_lwop' => true, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down()
    {
        // The old rule-set tables are not recreated (their migrations were removed).
        Schema::dropIfExists('lwop_weekend_cases');
    }
};
