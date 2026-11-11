<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DTRServices;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * Cutoff snapshots: generation, review, immutability, and who may finalize.
 * hris writes roll back; tdh_user rows are committed and removed in tearDown.
 */
class CutoffSnapshotTest extends TestCase
{
    use DatabaseTransactions;

    private int $userId;
    private array $extraUsers = [];
    private array $spareUsers = [];
    private string $title;
    private string $start;
    private string $end;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost', 'hris.cutoff_snapshots_enabled' => true]);
        app('url')->forceRootUrl('http://localhost');

        $this->title = 'Snapshot Test ' . uniqid();
        $previous = now()->startOfMonth()->subMonth();
        $this->start = $previous->toDateString();
        $this->end = $previous->copy()->endOfMonth()->toDateString();

        // Created before this test's transaction first reads, or it could not see them.
        $this->userId = $this->makeUser('standard');
        $this->spareUsers = [$this->makeUser('standard'), $this->makeUser('standard'), $this->makeUser('standard')];
        $this->login($this->userId);
        $this->grant($this->userId, ['payroll.settings', 'cutoff.finalize', 'cutoff.reopen']);
        $this->setSalary($this->userId, 30000);
    }

    protected function tearDown(): void
    {
        foreach (array_merge([$this->userId], $this->extraUsers) as $id) {
            DB::connection('users')->table('user_priv')->where('user_id', $id)->delete();
            DB::connection('users')->table('users')->where('id', $id)->delete();
        }
        parent::tearDown();
    }

    private function makeUser(string $level): int
    {
        $id = DB::connection('users')->table('users')->insertGetId([
            'fname' => 'Snap', 'lname' => 'Shot' . uniqid(), 'mname' => '',
            'username' => 'snap_' . uniqid(), 'password' => bcrypt('password'),
            'designation' => 0, 'division' => 0, 'section' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::connection('users')->table('user_priv')->updateOrInsert(
            ['user_id' => $id],
            ['user_id' => $id, 'syscode' => 'hris', 'level' => $level]
        );
        if (isset($this->userId)) {
            $this->extraUsers[] = $id;
        }

        return $id;
    }

    private function login(int $id): void
    {
        Auth::guard('web')->login(User::on('users')->findOrFail($id));
        $this->app->forgetInstance(\App\Services\PermissionService::class);
    }

    private function grant(int $id, array $codes): void
    {
        foreach ($codes as $code) {
            DB::table('hris.user_permissions')->insert([
                'user_id' => $id,
                'permission_id' => DB::table('hris.permissions')->where('code', $code)->value('id'),
                'type' => 'grant', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->app->forgetInstance(\App\Services\PermissionService::class);
    }

    private function setSalary(int $id, float $basic): void
    {
        DB::table('hris.payroll_settings')->updateOrInsert(
            ['userid' => $id],
            ['salary_grade' => 11, 'step' => 1, 'basic_salary' => $basic, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    private function setEmployee(int $id, ?string $type, ?string $resigned = null): void
    {
        DB::table('hris.employees')->updateOrInsert(['user_id' => $id], ['employee_type' => $type, 'date_resigned' => $resigned]);
    }

    private function create(?array $employees = null)
    {
        return $this->postJson('/cutoff-summaries', [
            'title' => $this->title, 'start_date' => $this->start, 'end_date' => $this->end,
            'employees' => $employees ?? [$this->userId],
        ]);
    }

    private function cutoffId(): int
    {
        return (int) DB::table('hris.cutoff_summaries_master')->where('title', $this->title)->value('id');
    }

    private function master(): object
    {
        return DB::table('hris.cutoff_summaries_master')->where('id', $this->cutoffId())->first();
    }

    private function generate()
    {
        return $this->postJson("/cutoff-summaries/{$this->cutoffId()}/generate-snapshot");
    }

    private function finalize()
    {
        return $this->postJson("/cutoff-summaries/{$this->cutoffId()}/finalize", ['confirmed' => true]);
    }

    private function firstWeekday(int $skip = 0): string
    {
        $date = \Carbon\Carbon::parse($this->start);
        $holidays = DB::table('hris.calendar_holidays')->pluck('date')->all();
        while (true) {
            if ($date->isWeekday() && !in_array($date->toDateString(), $holidays, true)) {
                if ($skip-- <= 0) {
                    return $date->toDateString();
                }
            }
            $date->addDay();
        }
    }

    private function fileLeave(string $date, bool $lwop, float $credits = 0.0, string $status = 'approved'): int
    {
        $appId = DB::table('hris.leave_applications')->insertGetId([
            'user_id' => $this->userId, 'status' => $status, 'is_lwop' => $lwop ? 1 : 0,
            'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
        ]);
        DB::table('hris.leave_date_applied')->insert([
            'leave_application_id' => $appId,
            'leave_type_id' => DB::table('hris.leave_types')->where('code', 'VL')->value('id'),
            'date_applied' => $date, 'hours' => 8, 'status' => 'active', 'credits_deducted' => $credits,
        ]);

        return $appId;
    }

    private function rows(string $table)
    {
        return DB::table('hris.' . $table)->where('cutoff_id', $this->cutoffId())->get();
    }

    /** @test */
    public function generating_moves_a_draft_to_review_and_fills_every_snapshot()
    {
        $this->setEmployee($this->userId, 'Permanent');
        $worked = $this->firstWeekday();
        DB::table('hris.dtr_logs')->insert([
            ['userid' => $this->userId, 'status' => 'IN', 'created_at' => "$worked 08:00:00"],
            ['userid' => $this->userId, 'status' => 'OUT', 'created_at' => "$worked 17:00:00"],
        ]);
        $paid = $this->firstWeekday(1);
        $this->fileLeave($paid, false, 1.0);
        $unpaid = $this->firstWeekday(2);
        $this->fileLeave($unpaid, true);

        $this->create()->assertOk();
        $this->generate()->assertOk()->assertJsonPath('report.processed', 1);

        $master = $this->master();
        $this->assertSame('for_review', $master->status);
        $this->assertSame(1, (int) $master->snapshot_version);

        $employee = $this->rows('cutoff_employee_snapshots')->first();
        $this->assertEquals(30000, $employee->monthly_salary);
        $this->assertSame('Permanent', $employee->employment_type);
        $this->assertSame(1, (int) $employee->payroll_eligible);

        $days = $this->rows('cutoff_summary_days')->keyBy('date');
        $this->assertSame('present', $days[$worked]->attendance_status);
        $this->assertSame("$worked 08:00:00", $days[$worked]->time_in);
        $this->assertNotNull($days[$worked]->source_log_ids);
        $this->assertSame('lwop', $days[$unpaid]->attendance_status);
        $this->assertSame(0, (int) $days[$unpaid]->is_paid);
        $this->assertEquals(1, $days[$unpaid]->lwop_fraction);

        $leaves = $this->rows('cutoff_leave_snapshots')->keyBy('leave_date');
        $this->assertEquals(1, $leaves[$paid]->paid_days);
        $this->assertEquals(1, $leaves[$unpaid]->unpaid_days);

        $summary = $this->rows('cutoff_summaries')->first();
        $this->assertEquals(1, $summary->total_paid_leave_days);
        $this->assertEqualsWithDelta(30000 / 22, (float) $summary->payroll_lwop_deduction_basis, 0.01);

        $this->assertSame(
            ['holidays', 'leave_types', 'pay_computations', 'pay_divisor', 'shifts', 'table_iii', 'tax_rules'],
            $this->rows('cutoff_config_snapshots')->pluck('config_key')->sort()->values()->all()
        );
        $this->assertNotEmpty($this->rows('cutoff_leave_balance_snapshots'));
    }

    /** @test */
    public function regenerating_replaces_the_snapshot_without_duplicates()
    {
        $this->fileLeave($this->firstWeekday(), false, 1.0);
        $this->create()->assertOk();
        $this->generate()->assertOk();
        $before = [$this->rows('cutoff_employee_snapshots')->count(), $this->rows('cutoff_leave_snapshots')->count(), $this->rows('cutoff_config_snapshots')->count()];

        $this->generate()->assertStatus(422);
        $this->postJson("/cutoff-summaries/{$this->cutoffId()}/return-to-draft")->assertOk();
        $this->assertSame('draft', $this->master()->status);
        $this->generate()->assertOk();

        $this->assertSame($before, [$this->rows('cutoff_employee_snapshots')->count(), $this->rows('cutoff_leave_snapshots')->count(), $this->rows('cutoff_config_snapshots')->count()]);
    }

    /** @test */
    public function a_failed_generation_leaves_no_partial_snapshot_and_returns_to_draft()
    {
        $second = $this->makeUser('standard');
        $this->create([$this->userId, $second])->assertOk();

        $mock = Mockery::mock(DTRServices::class)->makePartial();
        $mock->shouldReceive('getCutoffBreakdown')->once()->passthru();
        $mock->shouldReceive('getCutoffBreakdown')->andThrow(new \RuntimeException('boom'));
        $this->app->instance(DTRServices::class, $mock);

        $this->generate()->assertStatus(500);

        $this->assertSame('draft', $this->master()->status);
        $this->assertCount(0, $this->rows('cutoff_employee_snapshots'));
        $this->assertCount(0, $this->rows('cutoff_config_snapshots'));
        $this->assertStringContainsString('boom', DB::table('hris.cutoff_status_logs')
            ->where(['cutoff_id' => $this->cutoffId(), 'from_status' => 'processing', 'to_status' => 'draft'])->value('reason'));
    }

    /** @test */
    public function a_draft_must_be_generated_before_it_can_be_finalized()
    {
        $this->create()->assertOk();
        $this->finalize()->assertStatus(422)->assertSee('Generate the snapshot', false);

        $this->generate()->assertOk();
        $this->finalize()->assertOk();

        $master = $this->master();
        $this->assertSame('finalized', $master->status);
        $this->assertNotNull($master->snapshot_hash);
    }

    /** @test */
    public function a_finalized_snapshot_ignores_later_changes_to_the_live_records()
    {
        $this->setEmployee($this->userId, 'Permanent');
        $date = $this->firstWeekday();
        DB::table('hris.dtr_logs')->insert([
            ['userid' => $this->userId, 'status' => 'IN', 'created_at' => "$date 08:00:00"],
            ['userid' => $this->userId, 'status' => 'OUT', 'created_at' => "$date 17:00:00"],
        ]);
        $leaveDate = $this->firstWeekday(1);
        $appId = $this->fileLeave($leaveDate, false, 1.0);

        $this->create()->assertOk();
        $this->generate()->assertOk();
        $this->finalize()->assertOk();

        $this->setSalary($this->userId, 99999);
        $this->setEmployee($this->userId, 'Temporary');
        DB::table('hris.dtr_logs')->where('userid', $this->userId)->delete();
        DB::table('hris.leave_date_applied')->where('leave_application_id', $appId)->update(['status' => 'cancelled']);

        $employee = $this->rows('cutoff_employee_snapshots')->first();
        $this->assertEquals(30000, $employee->monthly_salary);
        $this->assertSame('Permanent', $employee->employment_type);
        $this->assertSame("$date 08:00:00", $this->rows('cutoff_summary_days')->keyBy('date')[$date]->time_in);
        $this->assertSame('active', $this->rows('cutoff_leave_snapshots')->first()->date_status);

        $this->getJson("/cutoff-summaries/{$this->cutoffId()}/status")->assertOk()->assertJsonPath('snapshot_intact', true);
        $this->generate()->assertStatus(422);
    }

    /** @test */
    public function changing_a_finalized_snapshot_directly_is_detected()
    {
        $this->create()->assertOk();
        $this->generate()->assertOk();
        $this->finalize()->assertOk();

        DB::table('hris.cutoff_employee_snapshots')->where('cutoff_id', $this->cutoffId())->update(['monthly_salary' => 1]);

        $this->getJson("/cutoff-summaries/{$this->cutoffId()}/status")->assertOk()->assertJsonPath('snapshot_intact', false);
    }

    /** @test */
    public function finalize_and_reopen_need_their_own_permissions()
    {
        $manager = $this->makeUser('standard');
        $this->setSalary($manager, 20000);
        $this->login($manager);
        $this->grant($manager, ['payroll.settings']);

        $this->create([$manager])->assertOk();
        $this->generate()->assertOk();
        $this->finalize()->assertStatus(403);
        $this->getJson("/cutoff-summaries/{$this->cutoffId()}/status")->assertOk()
            ->assertJsonPath('can_manage', true)->assertJsonPath('can_finalize', false);
    }

    /** @test */
    public function the_hrh_level_can_view_finalize_and_reopen_but_not_generate()
    {
        $this->create()->assertOk();
        $this->generate()->assertOk();

        $hrh = $this->makeUser('hrh');
        $this->login($hrh);

        $this->get('/cutoff-summaries/manage')->assertOk();
        $this->getJson("/cutoff-summaries/{$this->cutoffId()}/status")->assertOk()
            ->assertJsonPath('can_manage', false)->assertJsonPath('can_finalize', true)->assertJsonPath('can_reopen', true);
        $this->postJson("/cutoff-summaries/{$this->cutoffId()}/return-to-draft")->assertStatus(403);

        $this->finalize()->assertOk();
        $this->postJson("/cutoff-summaries/{$this->cutoffId()}/reopen", ['reason' => 'Corrections needed'])->assertOk();
        $this->assertSame('draft', $this->master()->status);
        $this->assertNull($this->master()->snapshot_hash);
        $this->generate()->assertStatus(403);
    }

    /** @test */
    public function an_early_exit_employee_stays_in_but_earlier_separations_do_not()
    {
        [$during, $before, $unknown] = $this->spareUsers;
        $this->setEmployee($during, 'Resigned', \Carbon\Carbon::parse($this->start)->addDays(9)->toDateString());
        $this->setEmployee($before, 'Resigned', \Carbon\Carbon::parse($this->start)->subDay()->toDateString());
        $this->setEmployee($unknown, 'Retired', null);

        $this->create([$this->userId, $during, $before, $unknown])->assertOk();

        $this->assertEqualsCanonicalizing(
            [$this->userId, $during],
            $this->rows('cutoff_summaries')->pluck('employee_id')->map(fn($id) => (int) $id)->all()
        );

        $report = $this->generate()->assertOk()->json('report');
        $this->assertContains($during, collect($report['exceptions'])->where('type', 'separated_in_period')->pluck('employee_id')->all());
        $this->assertEquals(10, $this->rows('cutoff_summaries')->firstWhere('employee_id', $during)->qualifying_service_days);
    }

    /** @test */
    public function lwop_on_a_holiday_is_kept_for_review_but_not_counted()
    {
        $holiday = $this->firstWeekday();
        DB::table('hris.calendar_holidays')->insert(['date' => $holiday, 'type' => 'test', 'description' => 'Test holiday']);
        $this->fileLeave($holiday, true);

        $this->create()->assertOk();
        $report = $this->generate()->assertOk()->json('report');

        $this->assertEquals(0, $this->rows('cutoff_summaries')->first()->lwop_days);
        $this->assertEquals(0, $this->rows('cutoff_leave_snapshots')->first()->unpaid_days);
        $this->assertSame(1, $report['counts']['lwop_non_working_day']);
    }

    /** @test */
    public function a_missing_salary_is_reported_and_excluded_from_payroll()
    {
        DB::table('hris.payroll_settings')->where('userid', $this->userId)->delete();
        $this->create()->assertOk();

        $report = $this->generate()->assertOk()->json('report');

        $this->assertSame(0, $report['payroll_eligible']);
        $this->assertSame('No salary on file', $report['excluded'][0]['reason']);
    }

    /** @test */
    public function the_snapshot_uses_the_salary_in_effect_on_the_last_day()
    {
        $old = \Carbon\Carbon::parse($this->start)->subMonth()->toDateString();
        $afterEnd = \Carbon\Carbon::parse($this->end)->addDay()->toDateString();
        DB::table('hris.employee_salary_history')->insert([
            ['userid' => $this->userId, 'salary_grade' => 10, 'step' => 1, 'basic_salary' => 25000, 'effective_from' => $old, 'effective_to' => $this->end, 'source' => 'test'],
            ['userid' => $this->userId, 'salary_grade' => 12, 'step' => 1, 'basic_salary' => 40000, 'effective_from' => $afterEnd, 'effective_to' => null, 'source' => 'test'],
        ]);
        $this->create()->assertOk();
        $this->generate()->assertOk();

        $employee = $this->rows('cutoff_employee_snapshots')->first();
        $this->assertEquals(25000, $employee->monthly_salary);
        $this->assertSame($old, $employee->salary_effective_date);
    }

    /** @test */
    public function beginning_balances_come_from_the_previous_finalized_snapshot_not_the_live_balance()
    {
        $vl = (int) DB::table('hris.leave_types')->where('code', 'VL')->value('id');
        DB::table('hris.leave_credits')->updateOrInsert(['user_id' => $this->userId, 'leave_id' => $vl], ['based_credit' => 77, 'used_credit' => 0]);
        $earlier = \Carbon\Carbon::parse($this->start)->subMonth();
        $previousId = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title' => $this->title . ' earlier', 'period_start' => $earlier->toDateString(),
            'period_end' => $earlier->copy()->endOfMonth()->toDateString(), 'status' => 'finalized',
        ]);
        DB::table('hris.cutoff_leave_balance_snapshots')->insert([
            'cutoff_id' => $previousId, 'employee_id' => $this->userId, 'leave_type_id' => $vl,
            'ending_balance' => 12.5, 'opening_source' => 'opening_balance',
        ]);
        $this->fileLeave($this->firstWeekday(), false, 1.0);

        $this->create()->assertOk();
        $this->generate()->assertOk();

        $balance = $this->rows('cutoff_leave_balance_snapshots')->firstWhere('leave_type_id', $vl);
        $this->assertEquals(12.5, $balance->beginning_balance);
        $this->assertEquals(1, $balance->leave_used);
        $this->assertEquals(11.5, $balance->ending_balance);
        $this->assertSame('previous_cutoff', $balance->opening_source);
    }

    /** @test */
    public function no_opening_balance_is_reported_instead_of_using_the_live_balance()
    {
        $vl = (int) DB::table('hris.leave_types')->where('code', 'VL')->value('id');
        DB::table('hris.leave_credits')->updateOrInsert(['user_id' => $this->userId, 'leave_id' => $vl], ['based_credit' => 77, 'used_credit' => 0]);

        $this->create()->assertOk();
        $report = $this->generate()->assertOk()->json('report');

        $balance = $this->rows('cutoff_leave_balance_snapshots')->firstWhere('leave_type_id', $vl);
        $this->assertEquals(0, $balance->beginning_balance);
        $this->assertSame('unavailable', $balance->opening_source);
        $this->assertSame(1, $report['counts']['no_opening_balance']);
    }

    /** @test */
    public function only_records_inside_the_period_are_captured()
    {
        $before = \Carbon\Carbon::parse($this->start)->subDays(5)->toDateString();
        $after = \Carbon\Carbon::parse($this->end)->addDays(5)->toDateString();
        $inside = $this->firstWeekday();
        $this->fileLeave($before, false, 1.0);
        $this->fileLeave($inside, false, 1.0);
        $this->fileLeave($after, false, 1.0);
        DB::table('hris.dtr_logs')->insert([
            ['userid' => $this->userId, 'status' => 'IN', 'created_at' => "$after 08:00:00"],
            ['userid' => $this->userId, 'status' => 'OUT', 'created_at' => "$after 17:00:00"],
        ]);

        $this->create()->assertOk();
        $this->generate()->assertOk();

        $this->assertSame([$inside], $this->rows('cutoff_leave_snapshots')->pluck('leave_date')->all());
        $days = $this->rows('cutoff_summary_days')->pluck('date');
        $this->assertSame($this->start, $days->min());
        $this->assertSame($this->end, $days->max());
    }

    /** @test */
    public function every_status_change_is_logged_with_who_did_it()
    {
        $this->create()->assertOk();
        $this->generate()->assertOk();
        $this->finalize()->assertOk();
        $this->postJson("/cutoff-summaries/{$this->cutoffId()}/reopen", ['reason' => 'Corrections needed'])->assertOk();

        $logs = DB::table('hris.cutoff_status_logs')->where('cutoff_id', $this->cutoffId())->orderBy('id')->get();
        $this->assertSame(['processing', 'for_review', 'finalized', 'draft'], $logs->pluck('to_status')->all());
        $this->assertSame([$this->userId], $logs->pluck('acted_by')->map(fn($id) => (int) $id)->unique()->values()->all());
        $this->assertSame('Corrections needed', $logs->last()->reason);
    }

    /** @test */
    public function the_employee_detail_shows_everything_saved_for_them()
    {
        $this->setEmployee($this->userId, 'Permanent');
        $this->fileLeave($this->firstWeekday(), false, 1.0);
        $this->create()->assertOk();
        $this->generate()->assertOk();

        $detail = $this->getJson("/cutoff-summaries/{$this->cutoffId()}/employees/{$this->userId}/snapshot")->assertOk();

        $this->assertEquals(30000, $detail->json('employee.monthly_salary'));
        $this->assertSame('40 hours per week computation', $detail->json('employee.pay_computation_name'));
        $this->assertCount(\Carbon\Carbon::parse($this->start)->daysInMonth, $detail->json('days'));
        $this->assertCount(1, $detail->json('leaves'));
        $this->assertNotEmpty($detail->json('balances'));
        $this->assertIsArray($detail->json('summary.exceptions'));
    }

    /** @test */
    public function an_employee_without_a_snapshot_gets_a_clear_response()
    {
        $this->create()->assertOk();

        $this->getJson("/cutoff-summaries/{$this->cutoffId()}/employees/{$this->userId}/snapshot")->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function the_employee_detail_needs_a_cutoff_permission()
    {
        $this->create()->assertOk();
        $this->generate()->assertOk();

        $outsider = $this->spareUsers[0];
        $this->login($outsider);

        $this->getJson("/cutoff-summaries/{$this->cutoffId()}/employees/{$this->userId}/snapshot")->assertStatus(403);
    }

    /** @test */
    public function the_cutoff_list_rows_carry_the_snapshot_indicators()
    {
        DB::table('hris.payroll_settings')->where('userid', $this->userId)->delete();
        $this->create()->assertOk();
        $this->generate()->assertOk();

        $row = $this->getJson('/cutoff-summary-preview?cutoff_id=' . $this->cutoffId())->assertOk()->json('data.0');

        $this->assertNotNull($row['snapshot_id']);
        $this->assertSame(0, (int) $row['payroll_eligible']);
        $this->assertSame('No salary on file', $row['exclusion_reason']);
        $this->assertContains('no_salary', array_column(json_decode($row['exceptions'], true), 'type'));
    }

    /** @test */
    public function the_payroll_period_settings_are_saved_and_editable()
    {
        $body = ['title' => $this->title, 'start_date' => $this->start, 'end_date' => $this->end, 'employees' => [$this->userId],
            'payroll_date' => $this->end, 'remarks' => 'For October'];
        $this->postJson('/cutoff-summaries', $body)->assertOk();

        $master = $this->master();
        $this->assertSame($this->end, $master->payroll_date);
        $this->assertSame('For October', $master->remarks);
        $this->assertSame('whole_month', $master->cutoff_type);

        $half = \Carbon\Carbon::parse($this->start)->addDays(14)->toDateString();
        $this->putJson("/cutoff-summaries/{$master->id}", ['end_date' => $half, 'remarks' => 'Half'] + $body)->assertOk();

        $edit = $this->getJson("/cutoff-summaries/{$master->id}/edit")->assertOk();
        $this->assertSame('Half', $edit->json('data.remarks'));
        $this->assertSame('partial', $this->master()->cutoff_type);
    }

    /** @test */
    public function the_status_shows_the_settings_and_a_finalized_cutoff_keeps_its_detail()
    {
        $this->create()->assertOk();
        $this->generate()->assertOk();
        $this->finalize()->assertOk();

        $status = $this->getJson("/cutoff-summaries/{$this->cutoffId()}/status")->assertOk();
        $this->assertSame('whole_month', $status->json('cutoff_type'));
        $this->getJson("/cutoff-summaries/{$this->cutoffId()}/employees/{$this->userId}/snapshot")->assertOk();
        $this->assertSame('finalized', collect($this->getJson('/cutoff-periods')->json('data'))->firstWhere('id', $this->cutoffId())['status']);
    }

    /** @test */
    public function saving_a_salary_records_history_only_when_it_changes()
    {
        $payload = ['userid' => $this->userId, 'salary_grade' => 11, 'step' => 1, 'basic_salary' => 30000];
        $history = fn() => DB::table('hris.employee_salary_history')->where('userid', $this->userId)->orderBy('effective_from')->get();

        $this->postJson(route('savePayrollSettings'), $payload + ['effective_date' => '2026-01-01'])->assertOk();
        $this->assertCount(1, $history());

        $this->postJson(route('savePayrollSettings'), $payload + ['effective_date' => '2026-02-01'])->assertOk();
        $this->assertCount(1, $history());

        $this->postJson(route('savePayrollSettings'), ['basic_salary' => 35000, 'effective_date' => '2026-03-01'] + $payload)->assertOk();
        $rows = $history();
        $this->assertCount(2, $rows);
        $this->assertSame('2026-02-28', $rows[0]->effective_to);
        $this->assertSame('2026-03-01', $rows[1]->effective_from);
        $this->assertNull($rows[1]->effective_to);
        $this->assertEquals(35000, $rows[1]->basic_salary);
    }
}
