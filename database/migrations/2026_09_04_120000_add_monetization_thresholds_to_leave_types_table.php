<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->integer('min_monetize_days')->nullable()->default(10)->after('default_credit');
            $table->integer('min_retained_days')->nullable()->default(5)->after('min_monetize_days');
        });
    }

    public function down()
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn(['min_monetize_days', 'min_retained_days']);
        });
    }
};
