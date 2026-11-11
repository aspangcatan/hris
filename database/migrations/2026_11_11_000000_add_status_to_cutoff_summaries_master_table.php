<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cutoff lifecycle: draft -> finalized -> closed.
 *  - draft:     can be regenerated freely.
 *  - finalized: HR reviewed it; every row is locked. Earned credits and payroll
 *               read it. Can be reopened (with a reason) only while no payroll
 *               uses it and no credits were posted from it.
 *  - closed:    its payroll was released; read-only. Late changes become
 *               next-cutoff adjustments.
 * Every status change is written to cutoff_status_logs.
 *
 * Backfill: a cutoff used by a released payroll -> closed; one with locked rows
 * or used by any other payroll -> finalized; everything else stays draft.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('cutoff_summaries_master', function (Blueprint $table) {
            $table->string('status', 20)->default('draft')->after('period_end');
            $table->integer('finalized_by')->nullable()->after('generated_at');
            $table->timestamp('finalized_at')->nullable()->after('finalized_by');
            $table->timestamp('closed_at')->nullable()->after('finalized_at');
            $table->index('status', 'cutoff_summaries_master_status_index');
        });

        Schema::create('cutoff_status_logs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cutoff_id')->index();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('reason')->nullable();
            $table->integer('acted_by')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        $now = now();

        $released = DB::table('payrolls')
            ->whereNotNull('cutoff_id')
            ->whereRaw('LOWER(status) = ?', ['released'])
            ->pluck('cutoff_id')
            ->unique()
            ->all();

        $finalized = DB::table('payrolls')->whereNotNull('cutoff_id')->pluck('cutoff_id')
            ->merge(DB::table('cutoff_summaries')->where('is_locked', 1)->pluck('cutoff_id'))
            ->unique()
            ->diff($released)
            ->all();

        if (!empty($released)) {
            DB::table('cutoff_summaries_master')->whereIn('id', $released)
                ->update(['status' => 'closed', 'finalized_at' => $now, 'closed_at' => $now]);
        }
        if (!empty($finalized)) {
            DB::table('cutoff_summaries_master')->whereIn('id', $finalized)
                ->update(['status' => 'finalized', 'finalized_at' => $now]);
        }

        // Finalized/closed cutoffs have all rows locked.
        $nonDraft = array_merge($released, $finalized);
        if (!empty($nonDraft)) {
            DB::table('cutoff_summaries')->whereIn('cutoff_id', $nonDraft)->where('is_locked', 0)
                ->update(['is_locked' => 1, 'locked_at' => $now]);
        }

        foreach ([['closed', $released], ['finalized', $finalized]] as [$status, $ids]) {
            foreach ($ids as $id) {
                DB::table('cutoff_status_logs')->insert([
                    'cutoff_id' => $id, 'from_status' => null, 'to_status' => $status,
                    'reason' => 'Set by migration from existing payroll/lock data', 'created_at' => $now,
                ]);
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('cutoff_status_logs');
        Schema::table('cutoff_summaries_master', function (Blueprint $table) {
            $table->dropIndex('cutoff_summaries_master_status_index');
            $table->dropColumn(['status', 'finalized_by', 'finalized_at', 'closed_at']);
        });
    }
};
