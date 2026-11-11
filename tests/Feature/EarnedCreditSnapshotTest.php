<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Earned Credits reading from the cutoff snapshot, the unfiled-absence block,
 * and the ledger written on posting.
 * hris writes roll back; tdh_user rows are committed and removed in tearDown.
 */
class EarnedCreditSnapshotTest extends TestCase
{
    use DatabaseTransactions;

    private int $userId;
    private int $otherId;
    private string $title;
    private string $start;
    private string $end;
    private int $year;
    private int $month;
    private int $vl;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost', 'hris.cutoff_snapshots_enabled' => true]);
        app('url')->forceRootUrl('http://localhost');

        $this->title = 'Credit Snapshot ' . uniqid();
        $previous = now()->startOfMonth()->subMonth();
        $this->start = $previous->toDateString();
        $this->end = $previous->copy()->endOfMonth()->toDateString();
        $this->year = $previous->year;
        $this->month = $previous->month;

        // Created before this test's transaction first reads, or it could not see them.
        $this->userId = $this->makeUser('Alpha');
        $this->otherId = $this->makeUser('Bravo');

        Auth::guard('web')->login(User::on('users')->findOrFail($this->userId));
        foreach (['payroll.settings', 'cutoff.finalize', 'leave.manage_credits'] as $code) {
            DB::table('hris.user_permissions')->insert([
                'user_id' => $this->userId,
                'permission_id' => DB::table('hris.permissions')->where('code', $code)->value('id'),
                'type' => 'grant', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->app->forgetInstance(\App\Services\PermissionService::class);

        $this->vl = (int) DB::table('hris.leave_types')->where('code', 'VL')->value('id');
        foreach ([$this->userId, $this->otherId] as $id) {
            DB::table('hris.payroll_settings')->updateOrInsert(
                ['userid' => $id],
                ['salary_grade' => 11, 'step' => 1, 'basic_salary' => 30000, 'created_at' => now(), 'updated_at' => now()]
            );
            DB::table('hris.employees')->updateOrInsert(['user_id' => $id], ['employee_type' => 'Permanent']);
        }
    }

    protected function tearDown(): void
    {
        foreach ([$this->userId, $this->otherId] as $id) {
            DB::connection('users')->table('user_priv')->where('user_id', $id)->delete();
            DB::connection('users')->table('users')->where('id', $id)->delete();
        }
        parent::tearDown();
    }

    private function makeUser(string $name): int
    {
        $id = DB::connection('users')->table('users')->insertGetId([
            'fname' => $name, 'lname' => 'Credit' . uniqid(), 'mname' => '',
            'username' => 'credit_' . uniqid(), 'password' => bcrypt('password'),
            'designation' => 0, 'division' => 0, 'section' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::connection('users')->table('user_priv')->updateOrInsert(
            ['user_id' => $id],
            ['user_id' => $id, 'syscode' => 'hris', 'level' => 'standard']
        );

        return $id;
    }

    /** Full attendance for every working day, except the dates given. */
    private function attend(int $id, array $except = []): void
    {
        $holidays = DB::table('hris.calendar_holidays')->pluck('date')->all();
        $rows = [];
        for ($day = Carbon::parse($this->start); $day->lte($this->end); $day->addDay()) {
            $date = $day->toDateString();
            if ($day->isWeekday() && !in_array($date, $holidays, true) && !in_array($date, $except, true)) {
                $rows[] = ['userid' => $id, 'status' => 'IN', 'created_at' => "$date 08:00:00"];
                $rows[] = ['userid' => $id, 'status' => 'OUT', 'created_at' => "$date 17:00:00"];
            }
        }
        DB::table('hris.dtr_logs')->insert($rows);
    }

    private function workDay(int $skip = 0): string
    {
        $holidays = DB::table('hris.calendar_holidays')->pluck('date')->all();
        for ($day = Carbon::parse($this->start);; $day->addDay()) {
            if ($day->isWeekday() && !in_array($day->toDateString(), $holidays, true) && $skip-- <= 0) {
                return $day->toDateString();
            }
        }
    }

    private function prepare(array $employees): int
    {
        $this->postJson('/cutoff-summaries', [
            'title' => $this->title, 'start_date' => $this->start, 'end_date' => $this->end, 'employees' => $employees,
        ])->assertOk();
        $id = (int) DB::table('hris.cutoff_summaries_master')->where('title', $this->title)->value('id');
        $this->postJson("/cutoff-summaries/{$id}/generate-snapshot")->assertOk();
        $this->postJson("/cutoff-summaries/{$id}/finalize", ['confirmed' => true])->assertOk();

        return $id;
    }

    private function preview()
    {
        return $this->getJson('/leave-credits-earned/preview?' . http_build_query(['year' => $this->year, 'month' => $this->month]))->assertOk();
    }

    private function row(int $id): ?array
    {
        return collect($this->preview()->json('data'))->firstWhere('employee_id', $id);
    }

    private function postCredits(array $employees)
    {
        return $this->postJson('/leave-credits-earned/post', ['year' => $this->year, 'month' => $this->month, 'employees' => $employees]);
    }

    /** @test */
    public function eligibility_comes_from_the_snapshot_not_the_live_employee_type()
    {
        $this->attend($this->userId);
        $this->attend($this->otherId);
        DB::table('hris.employees')->where('user_id', $this->otherId)->update(['employee_type' => 'Job Order']);
        $this->prepare([$this->userId, $this->otherId]);

        DB::table('hris.employees')->where('user_id', $this->userId)->update(['employee_type' => 'Job Order']);
        DB::table('hris.employees')->where('user_id', $this->otherId)->update(['employee_type' => 'Permanent']);

        $this->assertSame('ready', $this->row($this->userId)['status']);
        $this->assertNull($this->row($this->otherId));
    }

    /** @test */
    public function table_iii_comes_from_the_snapshot_not_the_live_table()
    {
        $this->attend($this->userId);
        $this->prepare([$this->userId]);

        DB::table('hris.monthly_earned_points')->where('lwop', 0)->update(['points' => 0.111]);

        $this->assertEquals(1.25, $this->row($this->userId)['earned_points']);
    }

    /** @test */
    public function the_name_comes_from_the_snapshot()
    {
        $this->attend($this->userId);
        $cutoffId = $this->prepare([$this->userId]);
        $saved = DB::table('hris.cutoff_employee_snapshots')->where('cutoff_id', $cutoffId)->value('employee_name');

        $this->assertSame($saved, $this->row($this->userId)['name']);
    }

    /** @test */
    public function filed_lwop_still_sets_the_credit_through_table_iii()
    {
        $lwop = $this->workDay();
        $this->attend($this->userId, [$lwop]);
        $appId = DB::table('hris.leave_applications')->insertGetId([
            'user_id' => $this->userId, 'status' => 'approved', 'is_lwop' => 1, 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
        ]);
        DB::table('hris.leave_date_applied')->insert([
            'leave_application_id' => $appId, 'leave_type_id' => $this->vl, 'date_applied' => $lwop, 'hours' => 8, 'status' => 'active', 'credits_deducted' => 0,
        ]);
        $this->prepare([$this->userId]);

        $expected = (float) DB::table('hris.monthly_earned_points')->where('lwop', 1)->value('points');
        $row = $this->row($this->userId);
        $this->assertSame('ready', $row['status']);
        $this->assertEquals(1, $row['without_pay_days']);
        $this->assertEquals($expected, $row['earned_points']);
    }

    /** @test */
    public function unfiled_absences_are_flagged_and_named()
    {
        $missed = $this->workDay(1);
        $this->attend($this->userId);
        $this->attend($this->otherId, [$missed]);
        $this->prepare([$this->userId, $this->otherId]);

        $row = $this->row($this->otherId);
        $this->assertSame('unfiled', $row['status']);
        $label = Carbon::parse($missed)->format('M j');
        $this->assertStringContainsString($label, $row['reason']);

        $unfiled = $this->preview()->json('unfiled_absences');
        $this->assertCount(1, $unfiled);
        $this->assertSame($row['name'], $unfiled[0]['name']);
        $this->assertStringContainsString('1 day(s) absent with nothing filed', $unfiled[0]['detail']);
    }

    /** @test */
    public function one_selected_employee_with_unfiled_absences_stops_the_whole_post_until_unticked()
    {
        $this->attend($this->userId);
        $this->attend($this->otherId, [$this->workDay()]);
        $this->prepare([$this->userId, $this->otherId]);
        $blockedName = $this->row($this->otherId)['name'];

        $response = $this->postCredits([$this->userId, $this->otherId])->assertStatus(422);

        $this->assertStringContainsString($blockedName, $response->json('message'));
        $this->assertStringContainsString('untick', $response->json('message'));
        $this->assertFalse(DB::table('hris.credit_earned_logs')->whereIn('user_id', [$this->userId, $this->otherId])->exists());

        // HR unticks the employee with the issue: the rest go through.
        $this->assertSame(1, $this->postCredits([$this->userId])->assertOk()->json('credited'));
        $this->assertTrue(DB::table('hris.credit_earned_logs')->where('user_id', $this->userId)->exists());
        $this->assertFalse(DB::table('hris.credit_earned_logs')->where('user_id', $this->otherId)->exists());
    }

    /** @test */
    public function when_everyone_selected_is_blocked_posting_fails_with_their_names()
    {
        $this->attend($this->otherId, [$this->workDay()]);
        $this->prepare([$this->otherId]);
        $name = $this->row($this->otherId)['name'];

        $response = $this->postCredits([$this->otherId])->assertStatus(422);

        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString($name, $response->json('message'));
        $this->assertStringContainsString('absent with nothing filed', $response->json('message'));
    }

    /** @test */
    public function an_absence_covered_by_filed_leave_does_not_block()
    {
        $leave = $this->workDay();
        $this->attend($this->userId, [$leave]);
        $appId = DB::table('hris.leave_applications')->insertGetId([
            'user_id' => $this->userId, 'status' => 'approved', 'is_lwop' => 0, 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
        ]);
        DB::table('hris.leave_date_applied')->insert([
            'leave_application_id' => $appId, 'leave_type_id' => $this->vl, 'date_applied' => $leave, 'hours' => 8, 'status' => 'active', 'credits_deducted' => 1,
        ]);
        $this->prepare([$this->userId]);

        $this->assertSame('ready', $this->row($this->userId)['status']);
    }

    /** @test */
    public function posting_writes_a_ledger_that_reconciles_with_the_balance_and_keeps_the_snapshot_intact()
    {
        $this->attend($this->userId);
        DB::table('hris.leave_credits')->updateOrInsert(['user_id' => $this->userId, 'leave_id' => $this->vl], ['based_credit' => 10, 'used_credit' => 2]);
        $cutoffId = $this->prepare([$this->userId]);

        $this->postCredits([$this->userId])->assertOk();

        $entry = DB::table('hris.leave_credit_ledger')->where(['employee_id' => $this->userId, 'leave_type_id' => $this->vl])->first();
        $this->assertSame('earned', $entry->transaction_type);
        $this->assertSame($cutoffId, (int) $entry->cutoff_id);
        $this->assertEquals(8, $entry->beginning_balance);
        $this->assertEquals(1.25, $entry->earned_credits);
        $this->assertEquals(9.25, $entry->ending_balance);
        $this->assertSame($this->end, $entry->effective_date);
        $this->assertSame($this->userId, (int) $entry->finalized_by);

        $balance = DB::table('hris.leave_credits')->where(['user_id' => $this->userId, 'leave_id' => $this->vl])->first();
        $this->assertEquals((float) $entry->ending_balance, round((float) $balance->based_credit - (float) $balance->used_credit, 3));

        $this->postCredits([$this->userId])->assertStatus(422);
        $this->assertSame(1, DB::table('hris.leave_credit_ledger')->where(['employee_id' => $this->userId, 'leave_type_id' => $this->vl])->count());

        $this->getJson("/cutoff-summaries/{$cutoffId}/status")->assertOk()->assertJsonPath('snapshot_intact', true);
    }

    /** @test */
    public function the_snapshot_holds_this_cutoffs_table_iii_value()
    {
        $this->attend($this->userId);
        $cutoffId = $this->prepare([$this->userId]);

        $summary = DB::table('hris.cutoff_summaries')->where(['cutoff_id' => $cutoffId, 'employee_id' => $this->userId])->first();
        $this->assertEquals(1.25, $summary->vl_earned);
        $this->assertEquals(1.25, $summary->sl_earned);
    }

    /** @test */
    public function the_next_cutoffs_beginning_balance_includes_posted_credits()
    {
        // A cutoff one month back ended with 5.000, and its month was credited 1.250.
        $earlier = Carbon::parse($this->start)->subMonth();
        $earlierEnd = $earlier->copy()->endOfMonth()->toDateString();
        $earlierId = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title' => $this->title . ' earlier', 'period_start' => $earlier->toDateString(), 'period_end' => $earlierEnd, 'status' => 'finalized',
        ]);
        DB::table('hris.cutoff_leave_balance_snapshots')->insert([
            'cutoff_id' => $earlierId, 'employee_id' => $this->userId, 'leave_type_id' => $this->vl,
            'ending_balance' => 5, 'opening_source' => 'opening_balance',
        ]);
        DB::table('hris.leave_credit_ledger')->insert([
            'employee_id' => $this->userId, 'leave_type_id' => $this->vl, 'cutoff_id' => $earlierId, 'transaction_type' => 'earned', 'source_type' => 'credit_earned_logs',
            'earned_credits' => 1.25, 'effective_date' => $earlierEnd, 'transaction_date' => now(),
        ]);
        $this->attend($this->userId);
        $cutoffId = $this->prepare([$this->userId]);

        $balance = DB::table('hris.cutoff_leave_balance_snapshots')->where(['cutoff_id' => $cutoffId, 'leave_type_id' => $this->vl])->first();
        $this->assertEquals(6.25, $balance->beginning_balance);
    }
}
