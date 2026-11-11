<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preserves the pay rates used for a cutoff. The daily base, hourly and minute
 * rates are the same for every schedule; only the scheduled daily rate follows
 * the hours actually scheduled, so it is kept per day and, for reference, for
 * the employee's usual shift. Additive and nullable: existing rows are unaffected.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'cutoff_employee_snapshots' => ['daily_base_rate', 'hourly_rate', 'minute_rate', 'scheduled_hours_per_day', 'scheduled_daily_rate'],
        'cutoff_summary_days' => ['scheduled_daily_rate'],
        'cutoff_summaries' => ['scheduled_absent_minutes', 'scheduled_lwop_minutes', 'payroll_absence_deduction_basis'],
    ];

    public function up()
    {
        Schema::table('cutoff_employee_snapshots', function (Blueprint $table) {
            $table->decimal('daily_base_rate', 16, 8)->nullable();
            $table->decimal('hourly_rate', 16, 8)->nullable();
            $table->decimal('minute_rate', 16, 8)->nullable();
            $table->decimal('scheduled_hours_per_day', 5, 2)->nullable();
            $table->decimal('scheduled_daily_rate', 14, 4)->nullable();
        });

        Schema::table('cutoff_summary_days', function (Blueprint $table) {
            $table->decimal('scheduled_daily_rate', 14, 4)->nullable();
        });

        Schema::table('cutoff_summaries', function (Blueprint $table) {
            $table->integer('scheduled_absent_minutes')->nullable();
            $table->integer('scheduled_lwop_minutes')->nullable();
            $table->decimal('payroll_absence_deduction_basis', 12, 2)->nullable();
        });
    }

    public function down()
    {
        foreach (self::COLUMNS as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
