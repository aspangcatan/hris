<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LWOP Settings "cases": for a rule set whose rest_day_rule = 'cases', each row
 * says whether the rest days between two work days count as LWOP, given the
 * status of the work day BEFORE and AFTER them (lwop / present / absent /
 * paid_leave). A combination with no row does not count.
 *
 * Replaces the 'after_lwop' option added in 2026_11_10_300000: any rule set
 * using it becomes 'cases' with HR's three cases (LWOP Fri + present Mon = 3,
 * present Fri + LWOP Mon = 1, LWOP Fri + Mon = 4).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('lwop_rest_day_cases', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('lwop_rule_set_id')->constrained('lwop_rule_sets')->cascadeOnDelete();
            $table->string('before_status', 20);
            $table->string('after_status', 20);
            $table->boolean('counts_as_lwop')->default(false);
            $table->string('remarks', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['lwop_rule_set_id', 'before_status', 'after_status'], 'lwop_rest_day_cases_combo_unique');
        });

        if (Schema::hasColumn('lwop_rule_sets', 'rest_day_rule')) {
            $ids = DB::table('lwop_rule_sets')->where('rest_day_rule', 'after_lwop')->pluck('id');
            foreach ($ids as $id) {
                DB::table('lwop_rest_day_cases')->insert([
                    ['lwop_rule_set_id' => $id, 'before_status' => 'lwop', 'after_status' => 'present', 'counts_as_lwop' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
                    ['lwop_rule_set_id' => $id, 'before_status' => 'present', 'after_status' => 'lwop', 'counts_as_lwop' => false, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
                    ['lwop_rule_set_id' => $id, 'before_status' => 'lwop', 'after_status' => 'lwop', 'counts_as_lwop' => true, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
                ]);
            }
            DB::table('lwop_rule_sets')->whereIn('id', $ids)->update(['rest_day_rule' => 'cases']);
        }
    }

    public function down()
    {
        if (Schema::hasColumn('lwop_rule_sets', 'rest_day_rule')) {
            DB::table('lwop_rule_sets')->where('rest_day_rule', 'cases')->update(['rest_day_rule' => 'long_runs']);
        }
        Schema::dropIfExists('lwop_rest_day_cases');
    }
};
