<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdatePayrollsForWorkflow extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('payrolls', 'payroll_type')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->string('payroll_type', 30)->default('Regular');
            });
        }

        if (!Schema::hasColumn('payrolls', 'rate_type')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->string('rate_type', 30)->default('Non Pro-rated');
            });
        }

        if (!Schema::hasColumn('payrolls', 'status')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->string('status', 30)->default('draft');
            });
        } else {
            DB::table('payrolls')
                ->whereNull('status')
                ->update(['status' => 'draft']);
        }

        if (!Schema::hasColumn('payrolls', 'reviewed_by')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->unsignedBigInteger('reviewed_by')->nullable();
            });
        }

        if (!Schema::hasColumn('payrolls', 'approved_at')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->timestamp('approved_at')->nullable();
            });
        }

        if (!Schema::hasColumn('payrolls', 'released_at')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->timestamp('released_at')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('payrolls', 'released_at')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->dropColumn('released_at');
            });
        }

        if (Schema::hasColumn('payrolls', 'approved_at')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->dropColumn('approved_at');
            });
        }

        if (Schema::hasColumn('payrolls', 'reviewed_by')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->dropColumn('reviewed_by');
            });
        }

        if (Schema::hasColumn('payrolls', 'rate_type')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->dropColumn('rate_type');
            });
        }

        if (Schema::hasColumn('payrolls', 'payroll_type')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->dropColumn('payroll_type');
            });
        }
    }
}
