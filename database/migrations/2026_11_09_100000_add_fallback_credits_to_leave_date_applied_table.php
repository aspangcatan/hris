<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SL -> VL fallback (CSC MC No. 41, s. 1998, Sec. 56): when sick leave credits run
 * out, the rest of a sick leave date is charged to vacation leave. credits_deducted
 * keeps the part charged to the date's own leave type; these columns hold the part
 * charged to the fallback type, so every restore (rejection, cancellation, edit,
 * delete, DH disapproval) can give each part back to the right balance.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('leave_date_applied', function (Blueprint $table) {
            $table->integer('fallback_leave_type_id')->nullable()->after('credits_deducted');
            $table->decimal('fallback_credits', 10, 3)->default(0)->after('fallback_leave_type_id');
        });
    }

    public function down()
    {
        Schema::table('leave_date_applied', function (Blueprint $table) {
            $table->dropColumn(['fallback_leave_type_id', 'fallback_credits']);
        });
    }
};
