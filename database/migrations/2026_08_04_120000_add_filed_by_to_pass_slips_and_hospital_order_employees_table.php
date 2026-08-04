<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('pass_slips', function (Blueprint $table) {
            $table->integer('filed_by')->nullable()->after('user_id');
        });

        Schema::table('hospital_order_employees', function (Blueprint $table) {
            $table->integer('filed_by')->nullable()->after('userid');
        });
    }

    public function down()
    {
        Schema::table('pass_slips', function (Blueprint $table) {
            $table->dropColumn('filed_by');
        });

        Schema::table('hospital_order_employees', function (Blueprint $table) {
            $table->dropColumn('filed_by');
        });
    }
};
