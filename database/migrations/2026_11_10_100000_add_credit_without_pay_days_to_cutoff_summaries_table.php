<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Days without pay used for earned VL/SL credits (LWOP + unexcused absences,
 * plus rest days/holidays inside continuous runs over 7 calendar days -
 * CSC MC 41 s. 1998, Rule XVI, Sec. 33). Filled when a cutoff is generated;
 * NULL = generated before this column existed (credits use the old formula
 * until the cutoff is regenerated).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('cutoff_summaries', function (Blueprint $table) {
            $table->decimal('credit_without_pay_days', 6, 3)->nullable()->after('lwop_days');
        });
    }

    public function down()
    {
        Schema::table('cutoff_summaries', function (Blueprint $table) {
            $table->dropColumn('credit_without_pay_days');
        });
    }
};
