<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cutoff snapshot architecture, Phase 2: extends the three existing cutoff
 * tables. Every column is nullable or defaulted, so existing rows and code are
 * unaffected. snapshot_version 0 marks a legacy cutoff, generated before full
 * snapshots existed.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'cutoff_summaries_master' => [
            'cutoff_type', 'payroll_date', 'remarks', 'snapshot_version', 'snapshot_hash',
            'processing_started_at', 'reviewed_at',
        ],
        'cutoff_summaries' => [
            'total_paid_leave_days', 'total_rendered_minutes', 'qualifying_service_days',
            'leave_earning_rate', 'vl_earned', 'sl_earned', 'gross_salary_basis',
            'payroll_lwop_deduction_basis', 'exceptions',
        ],
        'cutoff_summary_days' => [
            'attendance_status', 'time_in', 'time_out', 'rendered_minutes', 'required_minutes',
            'schedule_code', 'pass_slip_minutes', 'is_paid', 'is_lwop', 'lwop_fraction', 'source_log_ids',
        ],
    ];

    public function up()
    {
        Schema::table('cutoff_summaries_master', function (Blueprint $table) {
            $table->string('cutoff_type', 30)->nullable();
            $table->date('payroll_date')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedSmallInteger('snapshot_version')->default(0);
            $table->char('snapshot_hash', 64)->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
        });

        Schema::table('cutoff_summaries', function (Blueprint $table) {
            $table->decimal('total_paid_leave_days', 6, 3)->nullable();
            $table->integer('total_rendered_minutes')->nullable();
            $table->decimal('qualifying_service_days', 6, 3)->nullable();
            $table->decimal('leave_earning_rate', 6, 3)->nullable();
            $table->decimal('vl_earned', 8, 3)->nullable();
            $table->decimal('sl_earned', 8, 3)->nullable();
            $table->decimal('gross_salary_basis', 12, 2)->nullable();
            $table->decimal('payroll_lwop_deduction_basis', 12, 2)->nullable();
            $table->text('exceptions')->nullable();
        });

        Schema::table('cutoff_summary_days', function (Blueprint $table) {
            $table->string('attendance_status', 20)->nullable();
            $table->dateTime('time_in')->nullable();
            $table->dateTime('time_out')->nullable();
            $table->integer('rendered_minutes')->nullable();
            $table->integer('required_minutes')->nullable();
            $table->string('schedule_code', 10)->nullable();
            $table->integer('pass_slip_minutes')->nullable();
            $table->boolean('is_paid')->nullable();
            $table->boolean('is_lwop')->nullable();
            $table->decimal('lwop_fraction', 6, 3)->nullable();
            $table->text('source_log_ids')->nullable();
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
