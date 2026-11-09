<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How a leave type is deducted when filed:
 *  - 'hours': by the hours filed, 8 hours = 1.000 (VL, SL, CTO ... - the CSC point system)
 *  - 'days' : per day, whatever the shift length - whole day = 1.000, half day = 0.500
 *             (Wellness, Special Privilege and the other special leaves granted in days)
 */
return new class extends Migration
{
    private const DAY_BASED_CODES = ['WELL', 'SPL', 'SOPL', 'PL', 'ML', 'VAWC', 'CL', 'AL', 'SLBW', 'RL', 'STL'];

    public function up()
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->string('deduction_unit', 10)->default('hours')->after('deduct_tardiness');
        });

        DB::table('leave_types')->whereIn('code', self::DAY_BASED_CODES)->update(['deduction_unit' => 'days']);
    }

    public function down()
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn('deduction_unit');
        });
    }
};
