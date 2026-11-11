<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cutoff lifecycle (draft -> finalized -> closed): pre-check, Finalize locks
 * the rows and stops regeneration, Reopen needs a reason and is refused once a
 * payroll or posted credits use the cutoff.
 */
class CutoffLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    private int $userId;
    private string $title;
    private string $start;
    private string $end;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);

        $this->title = 'Cutoff Lifecycle Test ' . uniqid();
        $prev = now()->startOfMonth()->subMonth();
        $this->start = $prev->toDateString();
        $this->end = $prev->copy()->endOfMonth()->toDateString();

        $this->userId = DB::connection('users')->table('users')->insertGetId([
            'fname' => 'Lifecycle', 'lname' => 'Test', 'mname' => '',
            'username' => 'lifecycle_' . uniqid(), 'password' => bcrypt('password'),
            'designation' => 0, 'division' => 0, 'section' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::connection('users')->table('user_priv')->updateOrInsert(
            ['user_id' => $this->userId],
            ['user_id' => $this->userId, 'syscode' => 'hris', 'level' => 'standard']
        );

        Auth::guard('web')->login(User::on('users')->findOrFail($this->userId));
    }

    protected function tearDown(): void
    {
        $masterIds = DB::table('hris.cutoff_summaries_master')->where('title', $this->title)->pluck('id');
        DB::table('hris.cutoff_status_logs')->whereIn('cutoff_id', $masterIds)->delete();
        DB::table('hris.payrolls')->whereIn('cutoff_id', $masterIds)->delete();
        DB::table('hris.cutoff_summary_days')->whereIn('cutoff_id', $masterIds)->delete();
        DB::table('hris.cutoff_summaries')->whereIn('cutoff_id', $masterIds)->delete();
        DB::table('hris.cutoff_summaries_master')->whereIn('id', $masterIds)->delete();
        DB::table('hris.credit_earned_logs')->where('user_id', $this->userId)->delete();
        $leaveIds = DB::table('hris.leave_applications')->where('user_id', $this->userId)->pluck('id');
        DB::table('hris.leave_date_applied')->whereIn('leave_application_id', $leaveIds)->delete();
        DB::table('hris.leave_applications')->whereIn('id', $leaveIds)->delete();

        DB::table('hris.user_permissions')->where('user_id', $this->userId)->delete();
        DB::connection('users')->table('user_priv')->where('user_id', $this->userId)->delete();
        DB::connection('users')->table('users')->where('id', $this->userId)->delete();
        parent::tearDown();
    }

    private function grantPayrollSettings(): void
    {
        foreach (['payroll.settings', 'cutoff.finalize', 'cutoff.reopen'] as $code) {
            DB::table('hris.user_permissions')->insert([
                'user_id' => $this->userId,
                'permission_id' => DB::table('hris.permissions')->where('code', $code)->value('id'),
                'type' => 'grant', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->app->forgetInstance(\App\Services\PermissionService::class);
    }

    private function generate()
    {
        return $this->postJson('/cutoff-summaries', [
            'title' => $this->title, 'start_date' => $this->start, 'end_date' => $this->end, 'employees' => [$this->userId],
        ]);
    }

    private function cutoffId(): int
    {
        return (int) DB::table('hris.cutoff_summaries_master')->where('title', $this->title)->value('id');
    }

    /** @test */
    public function lifecycle_actions_need_the_payroll_settings_permission()
    {
        $this->getJson('/cutoff-summaries/1/status')->assertStatus(403);
        $this->postJson('/cutoff-summaries/1/finalize', ['confirmed' => true])->assertStatus(403);
        $this->postJson('/cutoff-summaries/1/reopen', ['reason' => 'Testing reopen'])->assertStatus(403);
    }

    /** @test */
    public function a_new_cutoff_is_a_draft_and_the_precheck_lists_unfiled_absences_and_pending_leave()
    {
        $this->grantPayrollSettings();

        // Pending leave on a date inside the period.
        $leaveId = DB::table('hris.leave_applications')->insertGetId([
            'user_id' => $this->userId, 'status' => 'pending', 'is_lwop' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('hris.leave_date_applied')->insert([
            'leave_application_id' => $leaveId, 'leave_type_id' => 1,
            'date_applied' => $this->start, 'hours' => 8, 'status' => 'active',
        ]);

        $this->generate()->assertOk();
        $cutoffId = $this->cutoffId();

        $response = $this->getJson("/cutoff-summaries/{$cutoffId}/status")->assertOk();
        $this->assertSame('draft', $response->json('status'));
        $this->assertSame(1, $response->json('precheck.employees'));
        // A test user has no time logs, so every work day is an unfiled absence.
        $this->assertSame(1, $response->json('precheck.counts.unfiled_absence'));
        $this->assertSame(1, $response->json('precheck.counts.pending_leave'));
        $this->assertNull($response->json('reopen_blocker'));
    }

    /** @test */
    public function finalize_needs_confirmation_then_locks_every_row_and_stops_regeneration()
    {
        $this->grantPayrollSettings();
        $this->generate()->assertOk();
        $cutoffId = $this->cutoffId();

        $this->postJson("/cutoff-summaries/{$cutoffId}/finalize", [])
            ->assertStatus(422)
            ->assertSee('Confirm that you reviewed', false);

        $this->postJson("/cutoff-summaries/{$cutoffId}/finalize", ['confirmed' => true])
            ->assertOk()->assertJsonFragment(['success' => true]);

        $master = DB::table('hris.cutoff_summaries_master')->where('id', $cutoffId)->first();
        $this->assertSame('finalized', $master->status);
        $this->assertSame($this->userId, (int) $master->finalized_by);
        $this->assertSame(0, DB::table('hris.cutoff_summaries')->where('cutoff_id', $cutoffId)->where('is_locked', 0)->count());
        $this->assertTrue(DB::table('hris.cutoff_status_logs')
            ->where(['cutoff_id' => $cutoffId, 'from_status' => 'draft', 'to_status' => 'finalized'])->exists());

        // Regenerating a finalized cutoff is refused, and so is finalizing twice.
        $this->generate()->assertStatus(422)->assertSee('finalized', false);
        $this->postJson("/cutoff-summaries/{$cutoffId}/finalize", ['confirmed' => true])->assertStatus(422);

        $this->assertSame('finalized', collect($this->getJson('/cutoff-periods')->json('data'))->firstWhere('id', $cutoffId)['status']);
    }

    /** @test */
    public function reopen_needs_a_reason_unlocks_the_rows_and_allows_regeneration()
    {
        $this->grantPayrollSettings();
        $this->generate()->assertOk();
        $cutoffId = $this->cutoffId();
        $this->postJson("/cutoff-summaries/{$cutoffId}/finalize", ['confirmed' => true])->assertOk();

        $this->postJson("/cutoff-summaries/{$cutoffId}/reopen", ['reason' => ''])->assertStatus(422);

        $this->postJson("/cutoff-summaries/{$cutoffId}/reopen", ['reason' => 'Sick leave approved late'])->assertOk();

        $master = DB::table('hris.cutoff_summaries_master')->where('id', $cutoffId)->first();
        $this->assertSame('draft', $master->status);
        $this->assertNull($master->finalized_at);
        $this->assertSame(0, DB::table('hris.cutoff_summaries')->where('cutoff_id', $cutoffId)->where('is_locked', 1)->count());
        $this->assertSame('Sick leave approved late', DB::table('hris.cutoff_status_logs')
            ->where(['cutoff_id' => $cutoffId, 'to_status' => 'draft'])->value('reason'));

        $this->generate()->assertOk();
    }

    /** @test */
    public function a_draft_cutoff_can_be_edited_by_id_and_unticked_employees_are_removed()
    {
        $this->grantPayrollSettings();
        $otherId = DB::connection('users')->table('users')->insertGetId([
            'fname' => 'Lifecycle2', 'lname' => 'Test', 'mname' => '',
            'username' => 'lifecycle2_' . uniqid(), 'password' => bcrypt('password'),
            'designation' => 0, 'division' => 0, 'section' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        try {
            $this->postJson('/cutoff-summaries', [
                'title' => $this->title, 'start_date' => $this->start, 'end_date' => $this->end,
                'employees' => [$this->userId, $otherId],
            ])->assertOk();
            $cutoffId = $this->cutoffId();

            $edit = $this->getJson("/cutoff-summaries/{$cutoffId}/edit")->assertOk();
            $this->assertSame('draft', $edit->json('data.status'));
            $this->assertEqualsCanonicalizing([$this->userId, $otherId],
                array_map('intval', array_column($edit->json('data.employees'), 'user_id')));

            // New title, half-month period, and the second employee unticked.
            $halfEnd = \Carbon\Carbon::parse($this->start)->addDays(14)->toDateString();
            $newTitle = $this->title . ' (1-15)';
            $this->putJson("/cutoff-summaries/{$cutoffId}", [
                'title' => $newTitle, 'start_date' => $this->start, 'end_date' => $halfEnd,
                'employees' => [$this->userId],
            ])->assertOk()->assertSee('updated for 1 employee', false);
            $this->title = $newTitle; // tearDown cleans up by title

            $master = DB::table('hris.cutoff_summaries_master')->where('id', $cutoffId)->first();
            $this->assertSame([$newTitle, $halfEnd], [$master->title, $master->period_end]);
            $rows = DB::table('hris.cutoff_summaries')->where('cutoff_id', $cutoffId)->get();
            $this->assertSame([$this->userId], $rows->pluck('employee_id')->map(fn($i) => (int) $i)->all());
            $this->assertSame($halfEnd, $rows->first()->period_end, 'Kept employees are regenerated for the new period.');
            $this->assertFalse(DB::table('hris.cutoff_summary_days')
                ->where('cutoff_id', $cutoffId)->where('employee_id', $otherId)->exists());
            $this->assertSame(0, DB::table('hris.cutoff_summary_days')
                ->where('cutoff_id', $cutoffId)->where('date', '>', $halfEnd)->count());
        } finally {
            DB::table('hris.cutoff_summary_days')->where('employee_id', $otherId)->delete();
            DB::table('hris.cutoff_summaries')->where('employee_id', $otherId)->delete();
            DB::connection('users')->table('users')->where('id', $otherId)->delete();
        }
    }

    /** @test */
    public function a_finalized_cutoff_cannot_be_edited_and_titles_stay_unique()
    {
        $this->grantPayrollSettings();
        $this->generate()->assertOk();
        $cutoffId = $this->cutoffId();
        $payload = ['title' => $this->title, 'start_date' => $this->start, 'end_date' => $this->end, 'employees' => [$this->userId]];

        // Another draft with a different title can't be renamed onto this one.
        $otherId = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title' => $this->title . ' other', 'period_start' => $this->start, 'period_end' => $this->end,
            'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);
        try {
            $this->putJson("/cutoff-summaries/{$otherId}", $payload)->assertStatus(422)->assertSee('already has this title', false);
        } finally {
            DB::table('hris.cutoff_summaries_master')->where('id', $otherId)->delete();
        }

        $this->postJson("/cutoff-summaries/{$cutoffId}/finalize", ['confirmed' => true])->assertOk();
        $this->putJson("/cutoff-summaries/{$cutoffId}", $payload)->assertStatus(422)->assertSee('Only a draft cutoff can be edited', false);

        $listed = collect($this->getJson('/cutoff-periods')->json('data'))->firstWhere('id', $cutoffId);
        $this->assertSame(1, (int) $listed['employees']);
    }

    /** @test */
    public function reopen_is_refused_once_a_payroll_or_posted_credits_use_the_cutoff()
    {
        $this->grantPayrollSettings();
        $this->generate()->assertOk();
        $cutoffId = $this->cutoffId();
        $this->postJson("/cutoff-summaries/{$cutoffId}/finalize", ['confirmed' => true])->assertOk();

        $payrollId = DB::table('hris.payrolls')->insertGetId([
            'cutoff_id' => $cutoffId, 'title' => 'Lifecycle Payroll', 'start_date' => $this->start,
            'end_date' => $this->end, 'status' => 'draft', 'prepared_by' => $this->userId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->getJson("/cutoff-summaries/{$cutoffId}/status")->assertOk()
            ->assertJsonFragment(['status' => 'finalized'])
            ->assertSee('already uses this cutoff', false);
        $this->postJson("/cutoff-summaries/{$cutoffId}/reopen", ['reason' => 'Should be refused'])
            ->assertStatus(422)->assertSee('already uses this cutoff', false);

        DB::table('hris.payrolls')->where('id', $payrollId)->delete();
        $start = \Carbon\Carbon::parse($this->start);
        DB::table('hris.credit_earned_logs')->insert([
            'user_id' => $this->userId, 'month' => $start->month, 'year' => $start->year,
            'total_lateundertime_minutes' => 0, 'leave_type_id' => 1, 'earned_points' => 1.25,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->postJson("/cutoff-summaries/{$cutoffId}/reopen", ['reason' => 'Should be refused'])
            ->assertStatus(422)->assertSee('already posted', false);

        $this->assertSame('finalized', DB::table('hris.cutoff_summaries_master')->where('id', $cutoffId)->value('status'));
    }
}
