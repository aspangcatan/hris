<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Effective-dated LWOP / earned-credit rules (LWOP Settings page). A cutoff
 * uses the rule set whose effective_from is the latest on or before its
 * period start, so old months keep the rules they were computed with.
 *
 * Seeds the base rule set = the rules coded on 2026-11-10 (CSC MC 41 s. 1998,
 * Rule XVI, Sec. 33), effective 2000-01-01, so nothing changes on deploy.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('lwop_rule_sets', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->date('effective_from')->unique();
            $table->string('legal_basis', 255)->nullable();
            $table->text('remarks')->nullable();

            // LWOP identification (CSC Sec. 33)
            $table->boolean('count_rest_days_in_long_runs')->default(true);
            $table->unsignedSmallInteger('long_run_min_days')->default(7); // run must be LONGER than this
            $table->string('rest_day_source', 20)->default('schedule');    // schedule | weekend
            $table->boolean('holidays_bridge_runs')->default(true);
            $table->string('partial_day_mode', 20)->default('fraction');    // fraction | whole
            $table->boolean('partial_day_breaks_run')->default(true);

            // Earned credits
            $table->boolean('absences_count_without_pay')->default(true);
            $table->boolean('absences_join_runs')->default(true);
            $table->unsignedSmallInteger('month_basis_days')->default(30);
            $table->string('fractional_key_mode', 20)->default('interpolate'); // interpolate | floor | nearest

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        DB::table('lwop_rule_sets')->insert([
            'effective_from' => '2000-01-01',
            'legal_basis' => 'CSC MC No. 41, s. 1998, Rule XVI, Sec. 33',
            'remarks' => 'Base rules (system default as of 2026-11-10).',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('lwop_rule_sets');
    }
};
