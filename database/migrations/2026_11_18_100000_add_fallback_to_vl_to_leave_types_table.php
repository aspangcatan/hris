<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "If short, charge the rest to VL" per leave type (client decision 2026-11-18), set in
 * Leave Settings. Generalises the SL -> VL fallback (CSC MC No. 41, s. 1998, Sec. 56):
 * when a flagged type's balance can't cover a filing, the rest is charged to Vacation
 * Leave; if VL can't cover it either, the filing is refused. Sick Leave is switched on
 * so its behaviour is unchanged; every other type starts off.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->boolean('fallback_to_vl')->default(0)->after('deduction_unit');
        });

        DB::table('leave_types')->where('code', 'SL')->update(['fallback_to_vl' => 1]);
    }

    public function down()
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn('fallback_to_vl');
        });
    }
};
