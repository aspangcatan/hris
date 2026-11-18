<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compressed work week dates (client rule 2026-11-18): the filer marks a date as a
 * compressed (10-hour) schedule day. Such a date is filed as 10 hours (whole day,
 * 1.000 credit) or 5 hours (half day, 0.500) instead of following the minute
 * conversion table (where 10 hours = 1.250). Existing rows stay 0 (regular).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('leave_date_applied', function (Blueprint $table) {
            $table->boolean('is_compressed')->default(0)->after('hours');
        });
    }

    public function down()
    {
        Schema::table('leave_date_applied', function (Blueprint $table) {
            $table->dropColumn('is_compressed');
        });
    }
};
