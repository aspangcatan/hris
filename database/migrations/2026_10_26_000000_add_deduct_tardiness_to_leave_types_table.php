<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('leave_types', function (Blueprint $table) {
            // 1 = monthly earned credits are reduced by late/undertime (CSC: VL only),
            // 0 = always receives the full monthly_earned_points amount.
            $table->integer('deduct_tardiness')->default(0)->after('monthly_earned');
        });

        DB::table('leave_types')->where('code', 'VL')->update(['deduct_tardiness' => 1]);
    }

    public function down()
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn('deduct_tardiness');
        });
    }
};
