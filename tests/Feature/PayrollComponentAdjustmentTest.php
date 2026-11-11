<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PayrollComponentAdjustmentTest extends TestCase
{
    use DatabaseTransactions;

    private int $userId = 9401;
    private ?int $seededMasterId = null;
    private ?int $payrollId = null;
    private string $earningCode = 'TEST_ADJ_EARNING';
    private string $deductionCode = 'TEST_ADJ_DEDUCTION';

    protected function setUp(): void
    {
        parent::setUp();
        // APP_URL contains a subfolder (/hris) which breaks test HTTP client path matching.
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);
        $this->cleanupTestComponents();
    }

    protected function tearDown(): void
    {
        if ($this->payrollId) {
            DB::table('hris.payroll_employee_components')->whereIn(
                'payroll_employee_id',
                DB::table('hris.payroll_employees')->where('payroll_id', $this->payrollId)->pluck('id')
            )->delete();
            DB::table('hris.payroll_employees')->where('payroll_id', $this->payrollId)->delete();
            DB::table('hris.payrolls')->where('id', $this->payrollId)->delete();
        }
        if ($this->seededMasterId) {
            DB::table('hris.cutoff_summaries')->where('cutoff_id', $this->seededMasterId)->delete();
            DB::table('hris.cutoff_summaries_master')->where('id', $this->seededMasterId)->delete();
        }
        DB::table('hris.payroll_settings')->where('userid', $this->userId)->delete();
        DB::table('tdh_user.users')->where('id', $this->userId)->delete();
        $this->cleanupTestComponents();
        parent::tearDown();
    }

    private function cleanupTestComponents(): void
    {
        DB::table('hris.payroll_components')->whereIn('code', [$this->earningCode, $this->deductionCode])->delete();
    }

    private function actAsAdmin(): void
    {
        Auth::shouldReceive('id')->andReturn($this->userId);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn(null);
        session(['access_rights' => 'admin']);
    }

    private function seedCutoffMaster(string $start, string $end, string $title): int
    {
        DB::table('hris.cutoff_summaries_master')
            ->where('period_start', $start)
            ->where('period_end', $end)
            ->where('title', $title)
            ->delete();

        $id = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'status' => 'finalized', // payroll reads finalized cutoffs only
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

    private function seedPayrollWithCutoff(int $cutoffId): int
    {
        $master = DB::table('hris.cutoff_summaries_master')->where('id', $cutoffId)->first();
        $id = DB::table('hris.payrolls')->insertGetId([
            'cutoff_id'         => $cutoffId,
            'title'             => 'Adjustment Test Payroll',
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

        $this->payrollId = $id;
        return $id;
    }

    private function seedCutoffSummary(int $cutoffId, string $start, string $end): void
    {
        DB::table('hris.cutoff_summaries')->insert([
            'employee_id'        => $this->userId,
            'cutoff_id'          => $cutoffId,
            'period_start'       => $start,
            'period_end'         => $end,
            'total_working_days' => 10,
            'days_present'       => 10,
            'days_absent'        => 0,
            'late_minutes'       => 0,
            'undertime_minutes'  => 0,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    private function seedTestUser(): void
    {
        DB::table('tdh_user.users')->where('id', $this->userId)->delete();
        DB::table('tdh_user.users')->insert([
            'id'          => $this->userId,
            'fname'       => 'Adj',
            'lname'       => 'Tester',
            'username'    => 'adjtester' . $this->userId,
            'password'    => 'x',
            'division'    => 2,
            'section'     => 21,
            'designation' => 52,
        ]);
    }

    /** @test */
    public function cutoff_employees_endpoint_only_exposes_flagged_components_as_columns()
    {
        DB::table('hris.payroll_components')->insert([
            'code'                 => $this->earningCode,
            'name'                 => 'Test Adjustable Earning',
            'type'                 => 'earning',
            'is_global'            => false,
            'taxable'              => false,
            'default_amount'       => 0,
            'is_active'            => 1,
            'is_cutoff_adjustable' => 1,
            'sort_order'           => 999,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);
        // A non-adjustable component must NOT show up as a column.
        DB::table('hris.payroll_components')->insert([
            'code'                 => $this->deductionCode,
            'name'                 => 'Test Non-Adjustable Deduction',
            'type'                 => 'deduction',
            'is_global'            => false,
            'taxable'              => false,
            'default_amount'       => 0,
            'is_active'            => 1,
            'is_cutoff_adjustable' => 0,
            'sort_order'           => 1000,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        $masterId = $this->seedCutoffMaster('2027-05-01', '2027-05-15', 'Adj Column Test Cutoff');
        $payrollId = $this->seedPayrollWithCutoff($masterId);
        $this->seedCutoffSummary($masterId, '2027-05-01', '2027-05-15');
        $this->seedTestUser();

        $this->actAsAdmin();
        $response = $this->withoutMiddleware()->get("/payroll/{$payrollId}/cutoff-employees");
        $response->assertOk();

        $columnCodes = collect($response->json('columns'))->pluck('code');
        $this->assertTrue($columnCodes->contains($this->earningCode));
        $this->assertFalse($columnCodes->contains($this->deductionCode));

        $employee = collect($response->json('data'))->firstWhere('employee_id', $this->userId);
        $this->assertNotNull($employee);
        $this->assertArrayHasKey($this->earningCode, $employee['adjustments']);
        $this->assertEquals(0, $employee['adjustments'][$this->earningCode]);
    }

    /** @test */
    public function saving_dynamic_earning_and_deduction_adjustments_persists_and_round_trips()
    {
        DB::table('hris.payroll_components')->insert([
            [
                'code'                 => $this->earningCode,
                'name'                 => 'Test Adjustable Earning',
                'type'                 => 'earning',
                'is_global'            => false,
                'taxable'              => false,
                'default_amount'       => 0,
                'is_active'            => 1,
                'is_cutoff_adjustable' => 1,
                'sort_order'           => 999,
                'created_at'           => now(),
                'updated_at'           => now(),
            ],
            [
                'code'                 => $this->deductionCode,
                'name'                 => 'Test Adjustable Deduction',
                'type'                 => 'deduction',
                'is_global'            => false,
                'taxable'              => false,
                'default_amount'       => 0,
                'is_active'            => 1,
                'is_cutoff_adjustable' => 1,
                'sort_order'           => 1000,
                'created_at'           => now(),
                'updated_at'           => now(),
            ],
        ]);

        $masterId = $this->seedCutoffMaster('2027-06-01', '2027-06-15', 'Adj Save Test Cutoff');
        $payrollId = $this->seedPayrollWithCutoff($masterId);
        $this->seedCutoffSummary($masterId, '2027-06-01', '2027-06-15');
        $this->seedTestUser();

        DB::table('hris.payroll_settings')->where('userid', $this->userId)->delete();
        DB::table('hris.payroll_settings')->insert([
            'userid'       => $this->userId,
            'basic_salary' => 20000,
            'salary_grade' => 11,
            'step'         => 1,
        ]);

        $this->actAsAdmin();

        // Positive earning adjustment, negative ("refund") deduction adjustment.
        $response = $this->withoutMiddleware()->postJson('/save_payroll_employee', [
            'payroll_id' => $payrollId,
            'employees'  => [[
                'userid'             => $this->userId,
                'salary_adjustment'  => 0,
                'adjustments'        => [
                    $this->earningCode   => 750,
                    $this->deductionCode => -200,
                ],
            ]],
        ]);
        $response->assertOk();

        $payrollEmployeeId = DB::table('hris.payroll_employees')
            ->where('payroll_id', $payrollId)
            ->where('employee_id', $this->userId)
            ->value('id');
        $this->assertNotNull($payrollEmployeeId);

        $earningRow = DB::table('hris.payroll_employee_components')
            ->where('payroll_employee_id', $payrollEmployeeId)
            ->where('code', $this->earningCode . '_ADJUSTMENT')
            ->first();
        $this->assertNotNull($earningRow);
        $this->assertEquals('earning', $earningRow->type);
        $this->assertEqualsWithDelta(750.0, (float) $earningRow->amount, 0.01);

        $deductionRow = DB::table('hris.payroll_employee_components')
            ->where('payroll_employee_id', $payrollEmployeeId)
            ->where('code', $this->deductionCode . '_ADJUSTMENT')
            ->first();
        $this->assertNotNull($deductionRow);
        $this->assertEquals('deduction', $deductionRow->type);
        $this->assertEqualsWithDelta(-200.0, (float) $deductionRow->amount, 0.01);

        // Reloading the modal's data source must reflect what was just saved.
        $reload = $this->withoutMiddleware()->get("/payroll/{$payrollId}/cutoff-employees");
        $reload->assertOk();
        $employee = collect($reload->json('data'))->firstWhere('employee_id', $this->userId);
        $this->assertEquals(750, $employee['adjustments'][$this->earningCode]);
        $this->assertEquals(-200, $employee['adjustments'][$this->deductionCode]);
    }

    /** @test */
    public function admin_toggling_is_cutoff_adjustable_round_trips_through_settings_endpoints()
    {
        $this->actAsAdmin();

        $create = $this->withoutMiddleware()->postJson('/payroll-components', [
            'components' => [[
                'code'                 => $this->earningCode,
                'name'                 => 'Test Adjustable Earning',
                'type'                 => 'earning',
                'taxable'              => false,
                'default_amount'       => 0,
                'is_global'            => false,
                'is_active'            => true,
                'is_cutoff_adjustable' => true,
            ]],
        ]);
        $create->assertOk();

        $stored = DB::table('hris.payroll_components')->where('code', $this->earningCode)->first();
        $this->assertNotNull($stored);
        $this->assertEquals(1, (int) $stored->is_cutoff_adjustable);

        $list = $this->withoutMiddleware()->get('/payroll-components');
        $list->assertOk();
        $fetched = collect($list->json('data'))->firstWhere('code', $this->earningCode);
        $this->assertNotNull($fetched);
        $this->assertEquals(1, (int) $fetched['is_cutoff_adjustable']);

        // Toggle it back off.
        $update = $this->withoutMiddleware()->postJson('/payroll-components', [
            'components' => [[
                'code'                 => $this->earningCode,
                'name'                 => 'Test Adjustable Earning',
                'type'                 => 'earning',
                'taxable'              => false,
                'default_amount'       => 0,
                'is_global'            => false,
                'is_active'            => true,
                'is_cutoff_adjustable' => false,
            ]],
        ]);
        $update->assertOk();

        $stored = DB::table('hris.payroll_components')->where('code', $this->earningCode)->first();
        $this->assertEquals(0, (int) $stored->is_cutoff_adjustable);
    }
}
