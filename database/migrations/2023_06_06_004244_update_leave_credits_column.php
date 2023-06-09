<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateLeaveCreditsColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('leave_credits', function (Blueprint $table) {
            $table->dropColumn('credits');
            $table->decimal('vl_bal', 10, 2);
            $table->decimal('sl_bal', 10, 2);
            $table->decimal('cto_bal', 10, 2);
            $table->decimal('fl_bal', 10, 2);
            $table->decimal('spl_bal', 10, 2);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('leave_credits', function (Blueprint $table) {
            $table->decimal('credits', 10, 2);
            $table->dropColumn('vl_bal');
            $table->dropColumn('sl_bal');
            $table->dropColumn('cto_bal');
            $table->dropColumn('fl_bal');
            $table->dropColumn('spl_bal');
        });
    }
}
