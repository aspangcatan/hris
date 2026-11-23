<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Component type 'employer_share': the government's own GSIS share (GS) and
 *   EC contribution. Kept with each payroll for remittance, never deducted
 *   from the employee's pay. GSIS_GS and GSIS_EC move to it.
 * - permanent_only: the component goes to Permanent employees only (set on the
 *   Magna Carta benefits: Hazard, Subsistence, Laundry).
 * - absence_reduction / absence_reduction_rate: how an earning is reduced for
 *   days on leave, absent or on LWOP - 'none', 'prorate' (monthly amount /
 *   working days per day) or 'per_day' (a fixed amount per day). HR sets it.
 */
return new class extends Migration
{
    private const TYPE_TABLES = ['payroll_components', 'payroll_employee_components', 'cutoff_payroll_components'];
    private const EMPLOYER_SHARES = ['GSIS_GS', 'GSIS_EC'];
    private const PERMANENT_ONLY = ['HAZARD', 'SUBSISTENCE', 'LAUNDRY'];

    public function up()
    {
        foreach (self::TYPE_TABLES as $table) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `type` ENUM('earning','deduction','employer_share') NOT NULL");
        }

        Schema::table('payroll_components', function (Blueprint $table) {
            $table->boolean('permanent_only')->default(false)->after('is_global');
            $table->enum('absence_reduction', ['none', 'prorate', 'per_day'])->default('none')->after('rate_percent');
            $table->decimal('absence_reduction_rate', 12, 2)->nullable()->after('absence_reduction');
        });

        DB::table('payroll_components')->whereIn('code', self::EMPLOYER_SHARES)
            ->update(['type' => 'employer_share', 'taxable' => 0, 'updated_at' => now()]);
        DB::table('payroll_components')->whereIn('code', self::PERMANENT_ONLY)
            ->update(['permanent_only' => 1, 'updated_at' => now()]);
    }

    public function down()
    {
        Schema::table('payroll_components', function (Blueprint $table) {
            $table->dropColumn(['permanent_only', 'absence_reduction', 'absence_reduction_rate']);
        });

        foreach (self::TYPE_TABLES as $table) {
            DB::table($table)->where('type', 'employer_share')->update(['type' => 'deduction']);
            DB::statement("ALTER TABLE `{$table}` MODIFY `type` ENUM('earning','deduction') NOT NULL");
        }
    }
};
