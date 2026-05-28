<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Create leave_details table
        if (!Schema::hasTable('leave_details')) {
            Schema::create('leave_details', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('leave_application_id');
                $table->unsignedBigInteger('leave_id');
                $table->string('detail', 255)->nullable();
                $table->string('extra', 255)->nullable();
                $table->decimal('credits_deducted', 10, 3)->default(0);
                $table->timestamps();

                $table->foreign('leave_application_id')
                      ->references('id')->on('leave_applications')
                      ->onDelete('cascade');
                $table->foreign('leave_id')
                      ->references('id')->on('leave_types')
                      ->onDelete('restrict');
            });
        }

        // 2. Migrate existing data into leave_details
        //    Each leave_applications row already maps to one leave type via leave_id.
        //    For grouped batches (same group_id), keep only the LOWEST id (primary app)
        //    and merge all leave types from the group into leave_details rows on that app.

        // Step 2a: Identify the primary app for each group (lowest id per group_id)
        $primaryIds = DB::table('leave_applications')
            ->whereNotNull('group_id')
            ->select(DB::raw('MIN(id) as primary_id'), 'group_id')
            ->groupBy('group_id')
            ->pluck('primary_id', 'group_id'); // [group_id => primary_id]

        // Step 2b: For each group, migrate every member app into leave_details on the primary
        foreach ($primaryIds as $groupId => $primaryId) {
            $members = DB::table('leave_applications')
                ->where('group_id', $groupId)
                ->get(['id', 'leave_id', 'detail', 'extra', 'user_id']);

            foreach ($members as $member) {
                // Calculate credits deducted for this member
                $totalHours = DB::table('leave_date_applied')
                    ->where('leave_application_id', $member->id)
                    ->sum('hours');
                $creditsDeducted = $totalHours > 0 ? ($totalHours / 8) / $members->count() : 0;

                DB::table('leave_details')->insert([
                    'leave_application_id' => $primaryId,
                    'leave_id'             => $member->leave_id,
                    'detail'               => $member->detail,
                    'extra'                => $member->extra,
                    'credits_deducted'     => $creditsDeducted,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);

                // Re-point dates on non-primary members to the primary app
                if ($member->id !== $primaryId) {
                    DB::table('leave_date_applied')
                        ->where('leave_application_id', $member->id)
                        ->delete(); // dates already exist on primary; remove duplicates

                    // Re-point approvals that may have landed on a non-primary (shouldn't happen, but safe)
                    DB::table('leave_application_approvals')
                        ->where('leave_application_id', $member->id)
                        ->update(['leave_application_id' => $primaryId]);
                }
            }

            // Delete non-primary group members
            DB::table('leave_applications')
                ->where('group_id', $groupId)
                ->where('id', '!=', $primaryId)
                ->delete();
        }

        // Step 2c: Migrate solo apps (no group_id) — simple 1-to-1 insert into leave_details
        $soloApps = DB::table('leave_applications')
            ->whereNull('group_id')
            ->get(['id', 'leave_id', 'detail', 'extra', 'user_id']);

        foreach ($soloApps as $app) {
            // Skip if already migrated somehow
            $exists = DB::table('leave_details')
                ->where('leave_application_id', $app->id)
                ->exists();
            if ($exists) continue;

            $totalHours = DB::table('leave_date_applied')
                ->where('leave_application_id', $app->id)
                ->sum('hours');
            $creditsDeducted = $totalHours > 0 ? $totalHours / 8 : 0;

            DB::table('leave_details')->insert([
                'leave_application_id' => $app->id,
                'leave_id'             => $app->leave_id,
                'detail'               => $app->detail,
                'extra'                => $app->extra,
                'credits_deducted'     => $creditsDeducted,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        }

        // 3. Drop columns from leave_applications that are now in leave_details
        Schema::table('leave_applications', function (Blueprint $table) {
            // Drop index on group_id before dropping column
            $table->dropIndex(['group_id']);
        });
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropColumn(['leave_id', 'detail', 'extra', 'group_id']);
        });

        // 4. Drop group_id from leave_application_approvals
        if (Schema::hasColumn('leave_application_approvals', 'group_id')) {
            Schema::table('leave_application_approvals', function (Blueprint $table) {
                $table->dropIndex(['group_id']);
            });
            Schema::table('leave_application_approvals', function (Blueprint $table) {
                $table->dropColumn('group_id');
            });
        }
    }

    public function down()
    {
        // Restore leave_applications columns
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->unsignedBigInteger('leave_id')->nullable()->after('user_id');
            $table->string('detail', 150)->nullable()->after('leave_id');
            $table->string('extra', 150)->nullable()->after('detail');
            $table->string('group_id', 36)->nullable()->after('extra')->index();
        });

        // Restore group_id on leave_application_approvals
        if (!Schema::hasColumn('leave_application_approvals', 'group_id')) {
            Schema::table('leave_application_approvals', function (Blueprint $table) {
                $table->string('group_id', 36)->nullable()->after('leave_application_id')->index();
            });
        }

        // Drop leave_details
        Schema::dropIfExists('leave_details');
    }
};
