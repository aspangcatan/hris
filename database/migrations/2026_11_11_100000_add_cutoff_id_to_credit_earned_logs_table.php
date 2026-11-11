<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Earned-credit postings trace back to the finalized cutoff(s) they came from:
 *  - cutoff_id: the month's latest finalized cutoff for the employee (when a
 *    month is split into several cutoffs, their LWOP days are added together);
 *  - lwop_days: the filed LWOP days the Table III value was read by;
 *  - posted_by: who clicked Post.
 * All nullable: rows posted before this migration have none of them.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('credit_earned_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('cutoff_id')->nullable()->after('year')->index();
            $table->decimal('lwop_days', 6, 3)->nullable()->after('total_present');
            $table->integer('posted_by')->nullable()->after('deducted_points');
        });
    }

    public function down()
    {
        Schema::table('credit_earned_logs', function (Blueprint $table) {
            $table->dropIndex(['cutoff_id']);
            $table->dropColumn(['cutoff_id', 'lwop_days', 'posted_by']);
        });
    }
};
