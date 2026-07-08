<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PayrollCutoffTest extends TestCase
{
    use DatabaseTransactions;

    private int $userId = 9201;

    /** @var int|null */
    private $seededMasterId = null;

    protected function setUp(): void
    {
        parent::setUp();
        // APP_URL contains a subfolder (/hris) which breaks test HTTP client path matching.
        // Override to bare host so routes resolve correctly in tests.
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);
    }

    protected function tearDown(): void
    {
        if ($this->seededMasterId) {
            DB::table('hris.cutoff_summaries_master')->where('id', $this->seededMasterId)->delete();
        }
        // These tables are MyISAM, so DatabaseTransactions rollback is a no-op — clean up explicitly.
        DB::table('hris.payrolls')->where('prepared_by', $this->userId)->delete();
        parent::tearDown();
    }

    private function actAsUser(): void
    {
        Auth::shouldReceive('id')->andReturn($this->userId);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn(null);
        session(['access_rights' => 'admin']);
    }

    private function seedCutoffMaster(
        string $start = '2026-06-01',
        string $end   = '2026-06-15',
        string $title = 'Test Cutoff'
    ): int {
        // Remove any stale record from a previous failed run before inserting
        DB::table('hris.cutoff_summaries_master')
            ->where('period_start', $start)
            ->where('period_end', $end)
            ->where('title', $title)
            ->delete();

        $id = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title'        => $title,
            'period_start' => $start,
            'period_end'   => $end,
            'generated_by' => $this->userId,
            'generated_at' => now(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $this->seededMasterId = $id;
        return $id;
    }

    /** @test */
    public function get_cutoff_periods_includes_id()
    {
        $masterId = $this->seedCutoffMaster();
        $this->actAsUser();

        $response = $this->withoutMiddleware()->get('/cutoff-periods');

        $response->assertOk();
        $data = $response->json('data');
        $found = collect($data)->firstWhere('id', $masterId);
        $this->assertNotNull($found, 'Master record not found in response');
        $this->assertArrayHasKey('id', (array) $found);
        $this->assertEquals($masterId, $found['id']);
    }

    /** @test */
    public function save_payroll_requires_cutoff_id()
    {
        $this->actAsUser();

        $response = $this->withoutMiddleware()->postJson('/save_payroll', [
            'title'        => 'Test Payroll',
            'payroll_type' => 'Regular',
            'rate_type'    => 'Non Pro-rated',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function save_payroll_rejects_nonexistent_cutoff_id()
    {
        $this->actAsUser();

        $response = $this->withoutMiddleware()->postJson('/save_payroll', [
            'title'     => 'Test Payroll',
            'cutoff_id' => 99999,
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function save_payroll_with_valid_cutoff_id_stores_cutoff_id_and_derives_dates()
    {
        $masterId = $this->seedCutoffMaster('2026-06-01', '2026-06-15', 'June 1st Half');
        $this->actAsUser();

        $response = $this->withoutMiddleware()->postJson('/save_payroll', [
            'title'        => 'June Payroll',
            'cutoff_id'    => $masterId,
            'payroll_type' => 'Regular',
            'rate_type'    => 'Non Pro-rated',
        ]);

        $response->assertOk();

        $payroll = DB::table('hris.payrolls')
            ->where('title', 'June Payroll')
            ->where('prepared_by', $this->userId)
            ->latest('id')
            ->first();

        $this->assertNotNull($payroll);
        $this->assertEquals($masterId, (int) $payroll->cutoff_id);
        $this->assertEquals('2026-06-01', $payroll->start_date);
        $this->assertEquals('2026-06-15', $payroll->end_date);
        $this->assertEquals('2026-06-01', $payroll->cutoff_start_date);
        $this->assertEquals('2026-06-15', $payroll->cutoff_end_date);
        $this->assertEquals('June 1st Half', $payroll->cutoff_title);
    }

    private function seedPayrollWithCutoff(int $cutoffId): int
    {
        $master = DB::table('hris.cutoff_summaries_master')->where('id', $cutoffId)->first();
        return DB::table('hris.payrolls')->insertGetId([
            'cutoff_id'         => $cutoffId,
            'title'             => 'Test Payroll',
            'start_date'        => $master->period_start,
            'end_date'          => $master->period_end,
            'cutoff_start_date' => $master->period_start,
            'cutoff_end_date'   => $master->period_end,
            'cutoff_title'      => $master->title,
            'payroll_type'      => 'Regular',
            'rate_type'         => 'Non Pro-rated',
            'status'            => 'draft',
            'prepared_by'       => $this->userId,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    /** @test */
    public function get_payroll_list_includes_cutoff_label()
    {
        $masterId  = $this->seedCutoffMaster('2026-06-01', '2026-06-15', 'June 1st Half');
        $payrollId = $this->seedPayrollWithCutoff($masterId);
        $this->actAsUser();

        $response = $this->withoutMiddleware()->get('/get_payroll');

        $response->assertOk();
        $found = collect($response->json())->firstWhere('id', $payrollId);
        $this->assertNotNull($found);
        $this->assertEquals('June 1st Half', $found['cutoff_label'] ?? null);
    }

    /** @test */
    public function save_payroll_employee_fails_gracefully_when_no_cutoff_summaries_exist()
    {
        $masterId  = $this->seedCutoffMaster();
        $payrollId = $this->seedPayrollWithCutoff($masterId);
        $this->actAsUser();

        $response = $this->withoutMiddleware()->postJson('/save_payroll_employee', [
            'payroll_id' => $payrollId,
            'employees'  => [['userid' => $this->userId]],
        ]);

        // Should fail with missing cutoff summaries error
        $response->assertStatus(422);
        $responseData = $response->json();
        $errorMsg = strtolower($responseData['error'] ?? '');
        $this->assertStringContainsString('cutoff', $errorMsg);
    }

    /** @test */
    public function payslip_and_summary_pdfs_render_for_a_computed_payroll()
    {
        $masterId = $this->seedCutoffMaster('2027-03-01', '2027-03-15', 'Print Test Cutoff');
        $payrollId = $this->seedPayrollWithCutoff($masterId);
        $this->actAsUser();

        // getPayrollEmployee() inner-joins users/designation/division/section,
        // so the test employee needs a real row with valid FKs.
        DB::table('tdh_user.users')->where('id', $this->userId)->delete();
        DB::table('tdh_user.users')->insert([
            'id' => $this->userId,
            'fname' => 'Test',
            'lname' => 'User',
            'username' => 'testuser' . $this->userId,
            'password' => 'x',
            'division' => 2,
            'section' => 21,
            'designation' => 52,
        ]);

        DB::table('hris.payroll_employees')->insertGetId([
            'payroll_id' => $payrollId,
            'employee_id' => $this->userId,
            'division' => 1,
            'section' => 2,
            'basic_salary' => 30000,
            'other_earnings' => 2000,
            'late_absent' => 0,
            'gross_pay' => 30000,
            'other_deductions' => 1375,
            'deductions' => 1375,
            'net_pay' => 30625,
            'first_half_10th' => 15312.50,
            'second_half_25th' => 15312.50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $payslipResponse = $this->withoutMiddleware()->get('/payroll/payslip/' . $payrollId);
            $payslipResponse->assertOk();
            $this->assertStringContainsString('application/pdf', $payslipResponse->headers->get('Content-Type'));

            $summaryResponse = $this->withoutMiddleware()->get('/payroll/summary/' . $payrollId);
            $summaryResponse->assertOk();
            $this->assertStringContainsString('application/pdf', $summaryResponse->headers->get('Content-Type'));
        } finally {
            DB::table('hris.payroll_employees')->where('payroll_id', $payrollId)->delete();
            DB::table('tdh_user.users')->where('id', $this->userId)->delete();
        }
    }

    private function actAsAdminWithOrg(): void
    {
        Auth::shouldReceive('id')->andReturn($this->userId);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn((object) [
            'id'       => $this->userId,
            'section'  => 1,
            'division' => 1,
        ]);
        session(['access_rights' => 'admin']);
    }

    /** @test */
    public function full_payroll_lifecycle_moves_through_approval_chain_to_released()
    {
        DB::table('tdh_user.user_priv')->where('user_id', $this->userId)->where('syscode', 'hris')->delete();
        DB::table('tdh_user.user_priv')->insert([
            'user_id' => $this->userId,
            'syscode' => 'hris',
            'level'   => 'admin',
        ]);

        $masterId = $this->seedCutoffMaster('2027-01-01', '2027-01-15', 'Smoke Test Cutoff');

        app(\App\Services\PayrollServices::class)->generateCutoffSummary(
            $this->userId,
            '2027-01-01',
            '2027-01-15',
            'Smoke Test Cutoff',
            $this->userId
        );

        $this->actAsAdminWithOrg();

        $saveResponse = $this->withoutMiddleware()->postJson('/save_payroll', [
            'title'        => 'Smoke Test Payroll',
            'cutoff_id'    => $masterId,
            'payroll_type' => 'Regular',
            'rate_type'    => 'Non Pro-rated',
        ]);
        $saveResponse->assertOk();

        $payroll = DB::table('hris.payrolls')->where('title', 'Smoke Test Payroll')->latest('id')->first();
        $this->assertNotNull($payroll);
        $payrollId = $payroll->id;

        try {
            $computeResponse = $this->withoutMiddleware()->postJson('/save_payroll_employee', [
                'payroll_id' => $payrollId,
                'employees'  => [['userid' => $this->userId]],
            ]);
            $computeResponse->assertOk();

            $submitResponse = $this->withoutMiddleware()->postJson('/payroll/submit_for_approval', [
                'payroll_id' => $payrollId,
            ]);
            $submitResponse->assertOk();

            $approvals = DB::table('hris.payroll_approvals')
                ->where('payroll_id', $payrollId)
                ->orderBy('order_no')
                ->get();
            $this->assertCount(3, $approvals);
            $this->assertEquals(['SH', 'DH', 'HR'], $approvals->pluck('role')->toArray());
            $this->assertEquals(['pending', 'pending', 'pending'], $approvals->pluck('status')->toArray());

            foreach (['SH', 'DH', 'HR'] as $expectedRole) {
                $before = DB::table('hris.payroll_approvals')
                    ->where('payroll_id', $payrollId)
                    ->where('status', 'pending')
                    ->orderBy('order_no')
                    ->first();
                $this->assertNotNull($before, "Expected a pending approval step for role {$expectedRole}");
                $this->assertEquals($expectedRole, $before->role);

                $actionResponse = $this->withoutMiddleware()->postJson('/payroll/approval_action', [
                    'payroll_id' => $payrollId,
                    'status'     => 'approved',
                ]);
                $actionResponse->assertOk();
            }

            $payroll = DB::table('hris.payrolls')->where('id', $payrollId)->first();
            $this->assertEquals('approved', strtolower($payroll->status));
            $this->assertEquals($this->userId, (int) $payroll->reviewed_by);
            $this->assertNotNull($payroll->approved_at);

            $releaseResponse = $this->withoutMiddleware()->postJson('/payroll/release', [
                'payroll_id' => $payrollId,
            ]);
            $releaseResponse->assertOk();

            $payroll = DB::table('hris.payrolls')->where('id', $payrollId)->first();
            $this->assertEquals('released', strtolower($payroll->status));
            $this->assertNotNull($payroll->released_at);
        } finally {
            DB::table('hris.payroll_approvals')->where('payroll_id', $payrollId)->delete();
            DB::table('hris.payroll_employee_components')->whereIn(
                'payroll_employee_id',
                DB::table('hris.payroll_employees')->where('payroll_id', $payrollId)->pluck('id')
            )->delete();
            DB::table('hris.payroll_employees')->where('payroll_id', $payrollId)->delete();
            DB::table('hris.payrolls')->where('id', $payrollId)->delete();
            DB::table('hris.cutoff_summaries')->where('cutoff_id', $masterId)->delete();
            DB::table('tdh_user.user_priv')->where('user_id', $this->userId)->where('syscode', 'hris')->delete();
        }
    }
}
