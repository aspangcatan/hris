<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Which LWOP rule set a cutoff summary was computed with (NULL = before LWOP Settings existed). */
return new class extends Migration
{
    public function up()
    {
        Schema::table('cutoff_summaries', function (Blueprint $table) {
            $table->unsignedBigInteger('lwop_rule_set_id')->nullable()->after('credit_without_pay_days');
        });
    }

    public function down()
    {
        Schema::table('cutoff_summaries', function (Blueprint $table) {
            $table->dropColumn('lwop_rule_set_id');
        });
    }
};
