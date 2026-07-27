<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('cutoff_summaries', function (Blueprint $table) {
            $table->integer('lwop_days')->default(0)->after('approved_leave_days');
        });
    }

    public function down()
    {
        Schema::table('cutoff_summaries', function (Blueprint $table) {
            $table->dropColumn('lwop_days');
        });
    }
};
