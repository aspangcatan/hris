<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add new columns to leave_date_applied
        if (!Schema::hasColumn('leave_date_applied', 'leave_type_id')) {
            Schema::table('leave_date_applied', function (Blueprint $table) {
                $table->unsignedBigInteger('leave_type_id')->nullable()->after('leave_application_id');
                $table->enum('status', ['active', 'cancelled'])->default('active')->after('hours');
                $table->decimal('credits_deducted', 10, 3)->default(0)->after('status');
            });
        }

        // 2. Backfill leave_type_id from first leave_id in leave_details per application
        DB::statement("
            UPDATE leave_date_applied lda
            JOIN (
                SELECT leave_application_id, MIN(leave_id) AS first_leave_id
                FROM leave_details
                GROUP BY leave_application_id
            ) ld ON ld.leave_application_id = lda.leave_application_id
            SET lda.leave_type_id      = ld.first_leave_id,
                lda.status             = 'active',
                lda.credits_deducted   = ROUND((lda.hours / 8) * 1.25, 3)
            WHERE lda.leave_type_id IS NULL
        ");

        // 3. Drop leave_id FK and column from leave_details
        if (Schema::hasColumn('leave_details', 'leave_id')) {
            try {
                Schema::table('leave_details', function (Blueprint $table) {
                    $table->dropForeign(['leave_id']);
                });
            } catch (\Exception $e) {
                // FK may have a non-standard name; try raw drop
                try {
                    DB::statement('ALTER TABLE leave_details DROP FOREIGN KEY leave_details_leave_id_foreign');
                } catch (\Exception $e2) {
                    // FK doesn't exist or already dropped — continue
                }
            }

            Schema::table('leave_details', function (Blueprint $table) {
                $table->dropColumn('leave_id');
            });
        }

        // 4. Drop credits_deducted from leave_details
        if (Schema::hasColumn('leave_details', 'credits_deducted')) {
            Schema::table('leave_details', function (Blueprint $table) {
                $table->dropColumn('credits_deducted');
            });
        }
    }

    public function down(): void
    {
        // Re-add credits_deducted to leave_details
        if (!Schema::hasColumn('leave_details', 'credits_deducted')) {
            Schema::table('leave_details', function (Blueprint $table) {
                $table->decimal('credits_deducted', 10, 3)->default(0);
            });
        }

        // Re-add leave_id to leave_details (no FK to keep it simple)
        if (!Schema::hasColumn('leave_details', 'leave_id')) {
            Schema::table('leave_details', function (Blueprint $table) {
                $table->unsignedBigInteger('leave_id')->nullable()->after('leave_application_id');
            });
        }

        // Remove new columns from leave_date_applied
        Schema::table('leave_date_applied', function (Blueprint $table) {
            if (Schema::hasColumn('leave_date_applied', 'credits_deducted')) $table->dropColumn('credits_deducted');
            if (Schema::hasColumn('leave_date_applied', 'status'))           $table->dropColumn('status');
            if (Schema::hasColumn('leave_date_applied', 'leave_type_id'))    $table->dropColumn('leave_type_id');
        });
    }
};
