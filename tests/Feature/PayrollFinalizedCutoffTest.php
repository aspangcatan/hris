<?php

namespace Tests\Feature;

use App\Services\DTRServices;
use App\Services\PayrollServices;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Payroll <- finalized cutoff (step 2 of the cutoff flow):
 *  - payroll can be created/computed from a finalized cutoff only;
 *  - approved LWOP days are deducted from pay (audit C1);
 *  - daily rate = basic / 22, whatever the cutoff length (audit H1);
 *  - releasing the last payroll of a cutoff closes it;
 *  - DH-disapproved paid-leave dates are not leave (audit H3).
 */
class PayrollFinalizedCutoffTest extends TestCase
{
    use DatabaseTransactions;

    private int $userId = 9431;
    private array $masterIds = [];
    private array $payrollIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);

        DB::table('tdh_user.users')->where('id', $this->userId)->delete();
        DB::table('tdh_user.users')->insert([
            'id' => $this->userId, 'fname' => 'Lwop', 'lname' => 'Payroll', 'username' => 'lwoppayroll' . $this->userId,
            'password' => 'x', 'division' => 2, 'section' => 21, 'designation' => 52,
        ]);
        DB::table('hris.payroll_settings')->where('userid', $this->userId)->delete();
        DB::table('hris.payroll_settings')->insert([
            'userid' => $this->userId, 'basic_salary' => 22000, 'salary_grade' => 11, 'step' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        $peIds = DB::table('hris.payroll_employees')->whereIn('payroll_id', $this->payrollIds)->pluck('id');
        DB::table('hris.payroll_employee_components')->whereIn('payroll_employee_id', $peIds)->delete();
        DB::table('hris.payroll_employees')->whereIn('payroll_id', $this->payrollIds)->delete();
        DB::table('hris.payroll_approvals')->whereIn('payroll_id', $this->payrollIds)->delete();
        DB::table('hris.payrolls')->whereIn('id', $this->payrollIds)->delete();
        DB::table('hris.payrolls')->where('prepared_by', $this->userId)->delete();
        DB::table('hris.cutoff_status_logs')->whereIn('cutoff_id', $this->masterIds)->delete();
        DB::table('hris.cutoff_summaries')->whereIn('cutoff_id', $this->masterIds)->delete();
        DB::table('hris.cutoff_summaries_master')->whereIn('id', $this->masterIds)->delete();
        DB::table('hris.payroll_settings')->where('userid', $this->userId)->delete();
        DB::table('tdh_user.users')->where('id', $this->userId)->delete();
        $leaveIds = DB::table('hris.leave_applications')->where('user_id', $this->userId)->pluck('id');
        DB::table('hris.leave_date_applied')->whereIn('leave_application_id', $leaveIds)->delete();
        DB::table('hris.leave_applications')->whereIn('id', $leaveIds)->delete();
        parent::tearDown();
    }

    private function actAsAdmin(): void
    {
        Auth::shouldReceive('id')->andReturn($this->userId);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn(null);
        session(['access_rights' => 'admin']);
    }

    private function seedCutoff(string $status, array $summary = []): int
    {
        $id = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title' => 'Finalized Cutoff Test ' . uniqid(), 'status' => $status,
            'period_start' => '2027-07-01', 'period_end' => '2027-07-31',
            'generated_by' => $this->userId, 'generated_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->masterIds[] = $id;
        DB::table('hris.cutoff_summaries')->insert(array_merge([
            'employee_id' => $this->userId, 'cutoff_id' => $id,
            'period_start' => '2027-07-01', 'period_end' => '2027-07-31',
            // A summary of 11 working days: the old formula would use basic / 11.
            'total_working_days' => 11, 'days_present' => 11, 'days_absent' => 0,
            'late_minutes' => 0, 'undertime_minutes' => 0, 'lwop_days' => 0,
            'is_locked' => $status === 'draft' ? 0 : 1,
            'created_at' => now(), 'updated_at' => now(),
        ], $summary));

        return $id;
    }

    private function seedPayroll(int $cutoffId, string $status = 'draft'): int
    {
        $id = DB::table('hris.payrolls')->insertGetId([
            'cutoff_id' => $cutoffId, 'title' => 'Finalized Cutoff Payroll', 'start_date' => '2027-07-01',
            'end_date' => '2027-07-31', 'cutoff_start_date' => '2027-07-01', 'cutoff_end_date' => '2027-07-31',
            'payroll_type' => 'Regular', 'rate_type' => 'Non Pro-rated', 'status' => $status,
            'prepared_by' => $this->userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->payrollIds[] = $id;

        return $id;
    }

    private function compute(int $payrollId)
    {
        return $this->withoutMiddleware()->postJson('/save_payroll_employee', [
            'payroll_id' => $payrollId,
            'employees' => [['userid' => $this->userId, 'salary_adjustment' => 0]],
        ]);
    }

    /** @test */
    public function a_payroll_can_only_be_created_from_a_finalized_cutoff()
    {
        $this->actAsAdmin();
        $draft = $this->seedCutoff('draft');
        $finalized = $this->seedCutoff('finalized');

        $this->withoutMiddleware()->postJson('/save_payroll', ['title' => 'X', 'cutoff_id' => $draft])
            ->assertStatus(422)->assertSee('still a draft', false);

        $this->withoutMiddleware()->postJson('/save_payroll', ['title' => 'X', 'cutoff_id' => $finalized])
            ->assertOk();
        $this->assertTrue(DB::table('hris.payrolls')->where('cutoff_id', $finalized)->exists());
    }

    /** @test */
    public function computing_a_payroll_on_a_draft_cutoff_is_refused()
    {
        $this->actAsAdmin();
        $payrollId = $this->seedPayroll($this->seedCutoff('draft'));

        $this->compute($payrollId)->assertStatus(422)->assertSee('still a draft', false);
        $this->assertFalse(DB::table('hris.payroll_employees')->where('payroll_id', $payrollId)->exists());
    }

    /** @test */
    public function lwop_absences_and_late_are_deducted_at_basic_over_22()
    {
        $this->actAsAdmin();
        // 22,000 / 22 = 1,000 a day; 1,000 / 8 / 60 per minute.
        $payrollId = $this->seedPayroll($this->seedCutoff('finalized', [
            'lwop_days' => 1.5, 'days_absent' => 1, 'late_minutes' => 30, 'undertime_minutes' => 18,
        ]));

        $this->compute($payrollId)->assertOk();

        $lateAbsent = (float) DB::table('hris.payroll_employees')->where('payroll_id', $payrollId)->value('late_absent');
        $expected = 1.5 * 1000 + 1 * 1000 + 48 * (1000 / 8 / 60);
        $this->assertEqualsWithDelta($expected, $lateAbsent, 0.01);
    }

    /** @test */
    public function personal_pass_slip_minutes_are_deducted_from_salary_like_late_and_undertime()
    {
        $this->actAsAdmin();
        // 30 late + 60 personal pass slip minutes at 1,000 / 8 / 60 per minute.
        $payrollId = $this->seedPayroll($this->seedCutoff('finalized', [
            'late_minutes' => 30, 'approved_pass_slips_minutes' => 60,
        ]));

        $this->compute($payrollId)->assertOk();

        $lateAbsent = (float) DB::table('hris.payroll_employees')->where('payroll_id', $payrollId)->value('late_absent');
        $this->assertEqualsWithDelta(90 * (1000 / 8 / 60), $lateAbsent, 0.01);
    }

    /** @test */
    public function releasing_the_last_payroll_of_a_cutoff_closes_it()
    {
        $cutoffId = $this->seedCutoff('finalized');
        $first = $this->seedPayroll($cutoffId, 'approved');
        $second = $this->seedPayroll($cutoffId, 'approved');

        $permissions = \Mockery::mock(\App\Services\PermissionService::class);
        $permissions->shouldReceive('can')->andReturn(true);
        $this->app->instance(\App\Services\PermissionService::class, $permissions);
        $this->actAsAdmin();

        $this->withoutMiddleware()->postJson('/payroll/release', ['payroll_id' => $first])->assertOk();
        $this->assertSame('finalized', DB::table('hris.cutoff_summaries_master')->where('id', $cutoffId)->value('status'));

        $this->withoutMiddleware()->postJson('/payroll/release', ['payroll_id' => $second])
            ->assertOk()->assertSee('cutoff is now closed', false);
        $master = DB::table('hris.cutoff_summaries_master')->where('id', $cutoffId)->first();
        $this->assertSame('closed', $master->status);
        $this->assertNotNull($master->closed_at);
        $this->assertTrue(DB::table('hris.cutoff_status_logs')
            ->where(['cutoff_id' => $cutoffId, 'from_status' => 'finalized', 'to_status' => 'closed'])->exists());
    }

    /** @test */
    public function dh_disapproved_paid_leave_dates_are_not_counted_as_leave()
    {
        $leaveId = DB::table('hris.leave_applications')->insertGetId([
            'user_id' => $this->userId, 'status' => 'Approved', 'is_lwop' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('hris.leave_date_applied')->insert([
            ['leave_application_id' => $leaveId, 'leave_type_id' => 1, 'date_applied' => '2027-07-05',
             'hours' => 8, 'status' => 'active', 'final_disapproved' => 0],
            ['leave_application_id' => $leaveId, 'leave_type_id' => 1, 'date_applied' => '2027-07-06',
             'hours' => 8, 'status' => 'active', 'final_disapproved' => 1],
        ]);

        $dtr = app(DTRServices::class);
        $dates = (new \ReflectionMethod($dtr, 'getApprovedLeaveDatesByRange'));
        $dates->setAccessible(true);
        $this->assertSame(['2027-07-05'], $dates->invoke($dtr, $this->userId, '2027-07-01', '2027-07-31'));

        $entries = (new \ReflectionMethod($dtr, 'getApprovedLeaveEntriesByRange'));
        $entries->setAccessible(true);
        $this->assertSame(['2027-07-05'], array_keys($entries->invoke($dtr, $this->userId, '2027-07-01', '2027-07-31')));
    }

    /** @test */
    public function the_daily_rate_divisor_is_22()
    {
        $this->assertSame(22, PayrollServices::WORKING_DAYS_PER_MONTH);
    }
}
