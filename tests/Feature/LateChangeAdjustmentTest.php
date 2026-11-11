<?php

namespace Tests\Feature;

use App\Services\CutoffAdjustmentService;
use App\Services\CutoffLifecycleService;
use App\Services\CutoffSnapshotService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Step 4: leave changed after a cutoff is finalized becomes an adjustment in the
 * employee's next cutoff, never an edit of the finalized one. Every write rolls back.
 */
class LateChangeAdjustmentTest extends TestCase
{
    use DatabaseTransactions;

    private const DAY = '2027-07-06'; // a Tuesday
    private int $userId = 9931;
    private int $vl;
    private int $sl;
    private ?int $july = null;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost', 'hris.cutoff_snapshots_enabled' => true]);
        app('url')->forceRootUrl('http://localhost');

        DB::table('tdh_user.users')->where('id', $this->userId)->delete();
        DB::table('tdh_user.users')->insert([
            'id' => $this->userId, 'fname' => 'Late', 'lname' => 'Change', 'username' => 'late' . $this->userId,
            'password' => 'x', 'division' => 2, 'section' => 21, 'designation' => 52,
        ]);
        DB::table('hris.employees')->updateOrInsert(['user_id' => $this->userId], ['employee_type' => 'Permanent']);
        DB::table('hris.payroll_settings')->updateOrInsert(['userid' => $this->userId], ['salary_grade' => 11, 'step' => 1, 'basic_salary' => 22000]);
        DB::table('hris.payroll_components')->update(['is_active' => 0]);
        DB::table('hris.payroll_tax_rules')->update(['is_active' => 0]);
        DB::table('hris.calendar_holidays')->whereBetween('date', ['2027-07-01', '2027-08-31'])->delete();

        $this->vl = (int) DB::table('hris.leave_types')->where('code', 'VL')->value('id');
        $this->sl = (int) DB::table('hris.leave_types')->where('code', 'SL')->value('id');
        foreach ([$this->vl, $this->sl] as $type) {
            DB::table('hris.leave_credits')->updateOrInsert(['user_id' => $this->userId, 'leave_id' => $type], ['based_credit' => 10, 'used_credit' => 0]);
        }

        Auth::shouldReceive('id')->andReturn($this->userId);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn(null);
        session(['access_rights' => 'admin']);
    }

    protected function tearDown(): void
    {
        DB::table('tdh_user.users')->where('id', $this->userId)->delete();
        parent::tearDown();
    }

    private function attend(string $start, string $end, array $except = []): void
    {
        for ($day = \Carbon\Carbon::parse($start); $day->lte($end); $day->addDay()) {
            $date = $day->toDateString();
            if ($day->isWeekday() && !in_array($date, $except, true)) {
                DB::table('hris.dtr_logs')->insert([
                    ['userid' => $this->userId, 'status' => 'IN', 'created_at' => "$date 08:00:00"],
                    ['userid' => $this->userId, 'status' => 'OUT', 'created_at' => "$date 17:00:00"],
                ]);
            }
        }
    }

    private function leave(bool $lwop, string $when): int
    {
        $appId = DB::table('hris.leave_applications')->insertGetId([
            'user_id' => $this->userId, 'status' => 'approved', 'is_lwop' => $lwop ? 1 : 0, 'created_at' => $when, 'updated_at' => $when,
        ]);
        DB::table('hris.leave_date_applied')->insert([
            'leave_application_id' => $appId, 'leave_type_id' => $this->vl, 'date_applied' => self::DAY, 'hours' => 8,
            'status' => 'active', 'credits_deducted' => $lwop ? 0 : 1,
        ]);

        return $appId;
    }

    private function cutoff(string $start, string $end): int
    {
        $id = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title' => 'Late ' . uniqid(), 'status' => 'draft', 'period_start' => $start, 'period_end' => $end, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('hris.cutoff_summaries')->insert([
            'employee_id' => $this->userId, 'cutoff_id' => $id, 'period_start' => $start, 'period_end' => $end,
            'total_working_days' => 22, 'days_present' => 0, 'days_absent' => 0, 'late_minutes' => 0, 'undertime_minutes' => 0, 'lwop_days' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        app(CutoffSnapshotService::class)->generate($id, $this->userId);

        return $id;
    }

    /** July finalized a few minutes ago, so anything changed now is "late". */
    private function finalizedJuly(): int
    {
        $this->july = $this->cutoff('2027-07-01', '2027-07-31');
        app(CutoffLifecycleService::class)->finalize($this->july, $this->userId);
        DB::table('hris.cutoff_summaries_master')->where('id', $this->july)->update(['finalized_at' => now()->subMinutes(10)]);

        return $this->july;
    }

    private function adjustments(?string $type = null)
    {
        $query = DB::table('hris.cutoff_adjustments')->where('source_cutoff_id', $this->july);

        return ($type ? $query->where('adjustment_type', $type) : $query)->get();
    }

    private function payroll(int $cutoffId): object
    {
        DB::table('hris.cutoff_summaries_master')->where('id', $cutoffId)->update(['status' => 'finalized']);
        $payrollId = DB::table('hris.payrolls')->insertGetId([
            'cutoff_id' => $cutoffId, 'title' => 'Aug', 'start_date' => '2027-08-01', 'end_date' => '2027-08-31',
            'status' => 'draft', 'prepared_by' => $this->userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->withoutMiddleware()->postJson('/save_payroll_employee', ['payroll_id' => $payrollId, 'employees' => [['userid' => $this->userId]]])->assertOk();

        return DB::table('hris.payroll_employees')->where('payroll_id', $payrollId)->first();
    }

    private function balance(int $type): float
    {
        $row = DB::table('hris.leave_credits')->where(['user_id' => $this->userId, 'leave_id' => $type])->first();

        return round($row->based_credit - $row->used_credit, 3);
    }

    /** @test */
    public function leave_approved_for_an_absence_after_finalizing_is_refunded_in_the_next_cutoff()
    {
        $this->attend('2027-07-01', '2027-07-31', [self::DAY]);
        $this->finalizedJuly();
        $savedDay = DB::table('hris.cutoff_summary_days')->where(['cutoff_id' => $this->july, 'date' => self::DAY])->first();

        $this->leave(false, now()->toDateTimeString());
        app(CutoffAdjustmentService::class)->detect($this->july);

        $pay = $this->adjustments(CutoffAdjustmentService::PAY)->first();
        $this->assertEquals(-1000, $pay->amount);
        $this->assertSame('pending', $pay->status);
        $this->assertStringContainsString('absent', $pay->reason);
        $this->assertEquals((array) $savedDay, (array) DB::table('hris.cutoff_summary_days')->where(['cutoff_id' => $this->july, 'date' => self::DAY])->first(), 'July is not edited');

        $this->attend('2027-08-01', '2027-08-31');
        $august = $this->cutoff('2027-08-01', '2027-08-31');

        $line = DB::table('hris.cutoff_payroll_components')->where(['cutoff_id' => $august, 'code' => CutoffAdjustmentService::REFUND_CODE])->first();
        $this->assertSame('earning', $line->type);
        $this->assertEquals(1000, $line->amount);
        $this->assertSame('included', $this->adjustments(CutoffAdjustmentService::PAY)->first()->status);

        $row = $this->payroll($august);
        $this->assertEquals(23000, (float) $row->net_pay);
    }

    /** @test */
    public function a_paid_leave_cancelled_after_finalizing_is_charged_in_the_next_cutoff()
    {
        $this->attend('2027-07-01', '2027-07-31', [self::DAY]);
        $appId = $this->leave(false, now()->subHour()->toDateTimeString());
        $this->finalizedJuly();

        $dateId = DB::table('hris.leave_date_applied')->where('leave_application_id', $appId)->value('id');
        DB::table('hris.leave_date_applied')->where('id', $dateId)->update(['status' => 'cancelled']);
        $cancellation = DB::table('hris.leave_cancellations')->insertGetId([
            'user_id' => $this->userId, 'reason' => 'test', 'status' => 'Approved', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('hris.leave_cancellation_dates')->insert([
            'leave_cancellation_id' => $cancellation, 'leave_date_applied_id' => $dateId, 'date_applied' => self::DAY, 'hours' => 8,
        ]);

        $this->attend('2027-08-01', '2027-08-31');
        $august = $this->cutoff('2027-08-01', '2027-08-31');

        $this->assertEquals(1000, $this->adjustments(CutoffAdjustmentService::PAY)->first()->amount);
        $line = DB::table('hris.cutoff_payroll_components')->where(['cutoff_id' => $august, 'code' => CutoffAdjustmentService::CHARGE_CODE])->first();
        $this->assertSame('deduction', $line->type);
        $this->assertEquals(21000, (float) $this->payroll($august)->net_pay);
    }

    /** @test */
    public function lwop_approved_late_changes_earned_credits_when_the_next_cutoff_is_finalized()
    {
        $this->attend('2027-07-01', '2027-07-31', [self::DAY]);
        $this->finalizedJuly();
        $this->leave(true, now()->toDateTimeString());

        $this->attend('2027-08-01', '2027-08-31');
        $august = $this->cutoff('2027-08-01', '2027-08-31');

        // Absent and LWOP cost the same pay, so only credits change.
        $this->assertCount(0, $this->adjustments(CutoffAdjustmentService::PAY));
        $credits = $this->adjustments(CutoffAdjustmentService::CREDITS)->first();
        $expected = (float) DB::table('hris.monthly_earned_points')->where('lwop', 1)->value('points') - 1.25;
        $this->assertEqualsWithDelta($expected, (float) $credits->credits, 0.0005);
        $this->assertSame('2027-07-31', $credits->adjustment_date);

        app(CutoffLifecycleService::class)->finalize($august, $this->userId);

        $this->assertSame('applied', $this->adjustments(CutoffAdjustmentService::CREDITS)->first()->status);
        $this->assertEqualsWithDelta(10 + $expected, $this->balance($this->vl), 0.0005);
        $this->assertEqualsWithDelta(10 + $expected, $this->balance($this->sl), 0.0005);
        $entry = DB::table('hris.leave_credit_ledger')->where(['source_type' => 'cutoff_adjustments', 'leave_type_id' => $this->vl])->first();
        $this->assertSame('2027-07-31', $entry->effective_date);

        // Reopening August takes the credits back; they wait in August again.
        app(CutoffLifecycleService::class)->reopen($august, $this->userId, 'Corrections needed');
        $this->assertEqualsWithDelta(10, $this->balance($this->vl), 0.0005);
        $this->assertSame('included', $this->adjustments(CutoffAdjustmentService::CREDITS)->first()->status);
    }

    /** @test */
    public function changes_made_before_finalizing_are_not_late()
    {
        $this->attend('2027-07-01', '2027-07-31', [self::DAY]);
        $this->leave(false, now()->subHour()->toDateTimeString());
        $this->finalizedJuly();

        app(CutoffAdjustmentService::class)->detect($this->july);

        $this->assertCount(0, $this->adjustments());
    }

    /** @test */
    public function checking_and_regenerating_again_never_duplicates()
    {
        $this->attend('2027-07-01', '2027-07-31', [self::DAY]);
        $this->finalizedJuly();
        $this->leave(false, now()->toDateTimeString());

        $service = app(CutoffAdjustmentService::class);
        $service->detect($this->july);
        $service->detect($this->july);
        $this->assertCount(1, $this->adjustments(CutoffAdjustmentService::PAY));

        $this->attend('2027-08-01', '2027-08-31');
        $august = $this->cutoff('2027-08-01', '2027-08-31');
        app(CutoffLifecycleService::class)->returnToDraft($august, $this->userId);
        app(CutoffSnapshotService::class)->generate($august, $this->userId);
        $service->detect($this->july);

        $this->assertCount(1, $this->adjustments(CutoffAdjustmentService::PAY));
        $this->assertSame(1, DB::table('hris.cutoff_payroll_components')->where(['cutoff_id' => $august, 'code' => CutoffAdjustmentService::REFUND_CODE])->count());
    }

    /** @test */
    public function the_cutoff_status_shows_late_changes_both_ways()
    {
        $this->attend('2027-07-01', '2027-07-31', [self::DAY]);
        $this->finalizedJuly();
        $this->leave(false, now()->toDateTimeString());
        $this->attend('2027-08-01', '2027-08-31');
        $august = $this->cutoff('2027-08-01', '2027-08-31');

        $this->withoutMiddleware();
        $july = $this->getJson("/cutoff-summaries/{$this->july}/status")->assertOk();
        $this->assertCount(1, $july->json('late_changes.found'));
        $this->assertSame('included', $july->json('late_changes.found.0.status'));

        $aug = $this->getJson("/cutoff-summaries/{$august}/status")->assertOk();
        $this->assertCount(1, $aug->json('late_changes.included'));
        $this->assertContains('late_changes_included', collect($aug->json('snapshot.exceptions'))->pluck('type')->all());
    }
}
