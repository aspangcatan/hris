<?php

namespace Tests\Feature;

use App\Services\PayrollServices;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class PayrollComponentRateOverrideTest extends TestCase
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    private int $wireTestUserId = 9801;
    private string $componentCode = 'TEST_RATE_WIRE';

    protected function setUp(): void
    {
        parent::setUp();
        // APP_URL contains a subfolder (/hris) which breaks test HTTP client path matching.
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        // payroll_components/payroll_settings/tdh_user.users/
        // employee_payroll_components are MyISAM in this environment, so
        // DatabaseTransactions rollback is a silent no-op - clean up explicitly.
        $this->cleanup();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        DB::table('hris.payroll_employee_components')
            ->whereIn('payroll_employee_id', DB::table('hris.payroll_employees')->where('employee_id', $this->wireTestUserId)->pluck('id'))
            ->delete();
        DB::table('hris.payroll_employees')->where('employee_id', $this->wireTestUserId)->delete();
        DB::table('hris.cutoff_summaries')->where('employee_id', $this->wireTestUserId)->delete();
        DB::table('hris.cutoff_summaries_master')->where('generated_by', $this->wireTestUserId)->delete();
        DB::table('hris.payrolls')->where('prepared_by', $this->wireTestUserId)->delete();
        DB::table('hris.employee_payroll_components')->where('userid', $this->wireTestUserId)->delete();
        DB::table('hris.payroll_settings')->where('userid', $this->wireTestUserId)->delete();
        DB::table('tdh_user.users')->where('id', $this->wireTestUserId)->delete();
        DB::table('hris.payroll_components')->where('code', $this->componentCode)->delete();
    }

    private function actAsAdmin(): void
    {
        Auth::shouldReceive('id')->andReturn($this->wireTestUserId);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'admin']);
    }

    private function seedTestUser(): void
    {
        DB::table('tdh_user.users')->insert([
            'id' => $this->wireTestUserId,
            'fname' => 'Rate',
            'lname' => 'Tester',
            'username' => 'ratetester' . $this->wireTestUserId,
            'password' => 'x',
            'division' => 2,
            'section' => 21,
            'designation' => 52,
        ]);
    }

    /**
     * Excludes every pre-existing global/active payroll component (e.g. PERA,
     * seeded independently of this test) for the test user, so an
     * other_earnings assertion reflects only the TEST_RATE_WIRE component
     * this test set up, not unrelated production seed data.
     */
    private function excludeOtherGlobalComponentsForTestUser(): void
    {
        $otherComponentIds = DB::table('hris.payroll_components')
            ->where('is_global', 1)
            ->where('is_active', 1)
            ->where('code', '!=', $this->componentCode)
            ->pluck('id');

        foreach ($otherComponentIds as $componentId) {
            DB::table('hris.employee_payroll_components')->insert([
                'payroll_component_id' => $componentId,
                'userid' => $this->wireTestUserId,
                'amount' => 0,
                'amount_override' => null,
                'is_active' => 0,
                'is_excluded' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function callResolveComponentAmounts(\Illuminate\Support\Collection $rows, float $basicSalary): \Illuminate\Support\Collection
    {
        $service = app(PayrollServices::class);
        $method = new ReflectionMethod(PayrollServices::class, 'resolveComponentAmounts');
        $method->setAccessible(true);
        return $method->invoke($service, $rows, $basicSalary);
    }

    /** @test */
    public function a_rate_override_replaces_the_catalog_rate_against_the_same_base()
    {
        $rows = collect([
            (object) [
                'code' => 'TEST_RATE_A',
                'default_amount' => 0,
                'amount_override' => null,
                'rate_percent_override' => 30,
                'calculation_type' => 'percentage',
                'base_type' => 'basic_salary',
                'base_component_code' => null,
                'rate_percent' => 25,
            ],
        ]);

        $resolved = $this->callResolveComponentAmounts($rows, 20000.0);

        // 30% of 20,000 - the employee's override rate, not the catalog's 25%.
        $this->assertEqualsWithDelta(6000.0, $resolved->firstWhere('code', 'TEST_RATE_A')->amount, 0.001);
    }

    /** @test */
    public function with_no_rate_override_the_catalog_rate_still_applies()
    {
        $rows = collect([
            (object) [
                'code' => 'TEST_RATE_A',
                'default_amount' => 0,
                'amount_override' => null,
                'rate_percent_override' => null,
                'calculation_type' => 'percentage',
                'base_type' => 'basic_salary',
                'base_component_code' => null,
                'rate_percent' => 25,
            ],
        ]);

        $resolved = $this->callResolveComponentAmounts($rows, 20000.0);

        $this->assertEqualsWithDelta(5000.0, $resolved->firstWhere('code', 'TEST_RATE_A')->amount, 0.001);
    }

    /** @test */
    public function an_amount_override_wins_outright_over_a_rate_override()
    {
        $rows = collect([
            (object) [
                'code' => 'TEST_RATE_A',
                'default_amount' => 0,
                'amount_override' => 9999.0,
                'rate_percent_override' => 30,
                'calculation_type' => 'percentage',
                'base_type' => 'basic_salary',
                'base_component_code' => null,
                'rate_percent' => 25,
            ],
        ]);

        $resolved = $this->callResolveComponentAmounts($rows, 20000.0);

        $this->assertEqualsWithDelta(9999.0, $resolved->firstWhere('code', 'TEST_RATE_A')->amount, 0.001);
    }

    /** @test */
    public function a_fixed_component_ignores_a_stray_rate_override()
    {
        $rows = collect([
            (object) [
                'code' => 'TEST_RATE_A',
                'default_amount' => 500,
                'amount_override' => null,
                'rate_percent_override' => 30,
                'calculation_type' => 'fixed',
                'base_type' => null,
                'base_component_code' => null,
                'rate_percent' => null,
            ],
        ]);

        $resolved = $this->callResolveComponentAmounts($rows, 20000.0);

        $this->assertEqualsWithDelta(500.0, $resolved->firstWhere('code', 'TEST_RATE_A')->amount, 0.001);
    }

    /** @test */
    public function a_rate_override_also_applies_when_the_base_is_another_component()
    {
        $rows = collect([
            (object) [
                'code' => 'TEST_RATE_BASE',
                'default_amount' => 0,
                'amount_override' => null,
                'rate_percent_override' => null,
                'calculation_type' => 'percentage',
                'base_type' => 'basic_salary',
                'base_component_code' => null,
                'rate_percent' => 25,
            ],
            (object) [
                'code' => 'TEST_RATE_DEPENDENT',
                'default_amount' => 0,
                'amount_override' => null,
                'rate_percent_override' => 20,
                'calculation_type' => 'percentage',
                'base_type' => 'component',
                'base_component_code' => 'TEST_RATE_BASE',
                'rate_percent' => 10,
            ],
        ]);

        $resolved = $this->callResolveComponentAmounts($rows, 20000.0);

        // Base resolves at its own (non-overridden) rate: 25% of 20,000 = 5,000.
        $this->assertEqualsWithDelta(5000.0, $resolved->firstWhere('code', 'TEST_RATE_BASE')->amount, 0.001);
        // Dependent uses its override rate (20%) against the resolved base, not the catalog's 10%.
        $this->assertEqualsWithDelta(1000.0, $resolved->firstWhere('code', 'TEST_RATE_DEPENDENT')->amount, 0.001);
    }

    /** @test */
    public function rejects_a_negative_rate_override_via_the_save_endpoint()
    {
        DB::table('hris.payroll_components')->insert([
            'code' => $this->componentCode,
            'name' => 'Test Rate Component',
            'type' => 'earning',
            'is_global' => 1,
            'taxable' => 0,
            'default_amount' => 0,
            'calculation_type' => 'percentage',
            'base_type' => 'basic_salary',
            'base_component_code' => null,
            'rate_percent' => 25,
            'is_active' => 1,
            'is_cutoff_adjustable' => 0,
            'sort_order' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->seedTestUser();
        $this->actAsAdmin();

        $response = $this->withoutMiddleware()->postJson('/save_payroll_settings', [
            'userid' => $this->wireTestUserId,
            'salary_grade' => 11,
            'step' => 1,
            'basic_salary' => 20000,
            'components' => [[
                'code' => $this->componentCode,
                'name' => 'Test Rate Component',
                'type' => 'earning',
                'taxable' => false,
                'is_global' => true,
                'default_amount' => 0,
                'amount_override' => null,
                'rate_percent_override' => -5,
                'is_excluded' => false,
                'effective_from' => null,
                'effective_to' => null,
            ]],
        ]);

        $response->assertStatus(422);

        $assignment = DB::table('hris.employee_payroll_components')
            ->where('userid', $this->wireTestUserId)
            ->first();
        $this->assertNull($assignment);
    }

    /** @test */
    public function saving_a_rate_override_persists_it_and_round_trips_through_get_payroll_settings()
    {
        DB::table('hris.payroll_components')->insert([
            'code' => $this->componentCode,
            'name' => 'Test Rate Component',
            'type' => 'earning',
            'is_global' => 1,
            'taxable' => 0,
            'default_amount' => 0,
            'calculation_type' => 'percentage',
            'base_type' => 'basic_salary',
            'base_component_code' => null,
            'rate_percent' => 25,
            'is_active' => 1,
            'is_cutoff_adjustable' => 0,
            'sort_order' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->seedTestUser();
        $this->actAsAdmin();

        $response = $this->withoutMiddleware()->postJson('/save_payroll_settings', [
            'userid' => $this->wireTestUserId,
            'salary_grade' => 11,
            'step' => 1,
            'basic_salary' => 20000,
            'components' => [[
                'code' => $this->componentCode,
                'name' => 'Test Rate Component',
                'type' => 'earning',
                'taxable' => false,
                'is_global' => true,
                'default_amount' => 0,
                'amount_override' => null,
                'rate_percent_override' => 30,
                'is_excluded' => false,
                'effective_from' => null,
                'effective_to' => null,
            ]],
        ]);
        $response->assertOk();

        $stored = DB::table('hris.employee_payroll_components')
            ->where('userid', $this->wireTestUserId)
            ->first();
        $this->assertNotNull($stored);
        $this->assertEqualsWithDelta(30.0, (float) $stored->rate_percent_override, 0.001);
        $this->assertEquals(1, (int) $stored->is_active);

        $reload = $this->withoutMiddleware()->get('/payroll-settings?userid=' . $this->wireTestUserId);
        $reload->assertOk();
        $reloadedComponent = collect($reload->json('components'))->firstWhere('code', $this->componentCode);
        $this->assertNotNull($reloadedComponent);
        $this->assertEqualsWithDelta(30.0, (float) $reloadedComponent['rate_percent_override'], 0.001);
    }

    /** @test */
    public function a_rate_override_flows_through_the_full_payroll_computation_and_tracks_a_later_salary_change()
    {
        DB::table('hris.payroll_components')->insert([
            'code' => $this->componentCode,
            'name' => 'Test Rate Component',
            'type' => 'earning',
            'is_global' => 1,
            'taxable' => 1,
            'default_amount' => 0,
            'calculation_type' => 'percentage',
            'base_type' => 'basic_salary',
            'base_component_code' => null,
            'rate_percent' => 25,
            'is_active' => 1,
            'is_cutoff_adjustable' => 0,
            'sort_order' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->seedTestUser();
        $this->excludeOtherGlobalComponentsForTestUser();

        $masterId = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title' => 'Rate Override Test Cutoff',
            'period_start' => '2027-10-01',
            'period_end' => '2027-10-15',
            'generated_by' => $this->wireTestUserId,
            'generated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payrollId = DB::table('hris.payrolls')->insertGetId([
            'cutoff_id' => $masterId,
            'title' => 'Rate Override Test Payroll',
            'start_date' => '2027-10-01',
            'end_date' => '2027-10-15',
            'cutoff_start_date' => '2027-10-01',
            'cutoff_end_date' => '2027-10-15',
            'cutoff_title' => 'Rate Override Test Cutoff',
            'payroll_type' => 'Regular',
            'rate_type' => 'Non Pro-rated',
            'status' => 'draft',
            'prepared_by' => $this->wireTestUserId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('hris.cutoff_summaries')->insert([
            'employee_id' => $this->wireTestUserId,
            'cutoff_id' => $masterId,
            'period_start' => '2027-10-01',
            'period_end' => '2027-10-15',
            'total_working_days' => 10,
            'days_present' => 10,
            'days_absent' => 0,
            'late_minutes' => 0,
            'undertime_minutes' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actAsAdmin();

        DB::table('hris.payroll_settings')->insert([
            'userid' => $this->wireTestUserId,
            'basic_salary' => 20000,
            'salary_grade' => 24,
            'step' => 1,
        ]);

        // Give this employee a 30% rate override (catalog default is 25%).
        $this->withoutMiddleware()->postJson('/save_payroll_settings', [
            'userid' => $this->wireTestUserId,
            'salary_grade' => 24,
            'step' => 1,
            'basic_salary' => 20000,
            'components' => [[
                'code' => $this->componentCode,
                'name' => 'Test Rate Component',
                'type' => 'earning',
                'taxable' => true,
                'is_global' => true,
                'default_amount' => 0,
                'amount_override' => null,
                'rate_percent_override' => 30,
                'is_excluded' => false,
                'effective_from' => null,
                'effective_to' => null,
            ]],
        ])->assertOk();

        $this->withoutMiddleware()->postJson('/save_payroll_employee', [
            'payroll_id' => $payrollId,
            'employees' => [[
                'userid' => $this->wireTestUserId,
                'salary_adjustment' => 0,
                'adjustments' => [],
            ]],
        ])->assertOk();

        $firstRun = DB::table('hris.payroll_employees')
            ->where('payroll_id', $payrollId)
            ->where('employee_id', $this->wireTestUserId)
            ->first();
        // 30% of 20,000 = 6,000 - the override rate, not the catalog's 25%.
        $this->assertEqualsWithDelta(6000.0, (float) $firstRun->other_earnings, 0.01);

        // A salary change between two runs: basic_salary goes from 20,000 to
        // 24,000. The rate override (30%) must still apply against the new
        // salary with no code change.
        DB::table('hris.payroll_settings')->where('userid', $this->wireTestUserId)
            ->update(['basic_salary' => 24000]);

        $this->withoutMiddleware()->postJson('/save_payroll_employee', [
            'payroll_id' => $payrollId,
            'employees' => [[
                'userid' => $this->wireTestUserId,
                'salary_adjustment' => 0,
                'adjustments' => [],
            ]],
        ])->assertOk();

        $secondRun = DB::table('hris.payroll_employees')
            ->where('payroll_id', $payrollId)
            ->where('employee_id', $this->wireTestUserId)
            ->first();
        // 30% of the new 24,000 basic salary = 7,200.
        $this->assertEqualsWithDelta(7200.0, (float) $secondRun->other_earnings, 0.01);

        DB::table('hris.payroll_employee_components')
            ->whereIn('payroll_employee_id', DB::table('hris.payroll_employees')->where('payroll_id', $payrollId)->pluck('id'))
            ->delete();
        DB::table('hris.payroll_employees')->where('payroll_id', $payrollId)->delete();
        DB::table('hris.payrolls')->where('id', $payrollId)->delete();
        DB::table('hris.cutoff_summaries')->where('cutoff_id', $masterId)->delete();
        DB::table('hris.cutoff_summaries_master')->where('id', $masterId)->delete();
    }

    /** @test */
    public function rejects_a_rate_override_that_would_overflow_the_column_via_the_save_endpoint()
    {
        DB::table('hris.payroll_components')->insert([
            'code' => $this->componentCode,
            'name' => 'Test Rate Component',
            'type' => 'earning',
            'is_global' => 1,
            'taxable' => 0,
            'default_amount' => 0,
            'calculation_type' => 'percentage',
            'base_type' => 'basic_salary',
            'base_component_code' => null,
            'rate_percent' => 25,
            'is_active' => 1,
            'is_cutoff_adjustable' => 0,
            'sort_order' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->seedTestUser();
        $this->actAsAdmin();

        $response = $this->withoutMiddleware()->postJson('/save_payroll_settings', [
            'userid' => $this->wireTestUserId,
            'salary_grade' => 11,
            'step' => 1,
            'basic_salary' => 20000,
            'components' => [[
                'code' => $this->componentCode,
                'name' => 'Test Rate Component',
                'type' => 'earning',
                'taxable' => false,
                'is_global' => true,
                'default_amount' => 0,
                'amount_override' => null,
                'rate_percent_override' => 999999,
                'is_excluded' => false,
                'effective_from' => null,
                'effective_to' => null,
            ]],
        ]);

        $response->assertStatus(422);

        $assignment = DB::table('hris.employee_payroll_components')
            ->where('userid', $this->wireTestUserId)
            ->first();
        $this->assertNull($assignment);
    }

    /** @test */
    public function rejects_a_non_numeric_rate_override_via_the_save_endpoint()
    {
        DB::table('hris.payroll_components')->insert([
            'code' => $this->componentCode,
            'name' => 'Test Rate Component',
            'type' => 'earning',
            'is_global' => 1,
            'taxable' => 0,
            'default_amount' => 0,
            'calculation_type' => 'percentage',
            'base_type' => 'basic_salary',
            'base_component_code' => null,
            'rate_percent' => 25,
            'is_active' => 1,
            'is_cutoff_adjustable' => 0,
            'sort_order' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->seedTestUser();
        $this->actAsAdmin();

        $response = $this->withoutMiddleware()->postJson('/save_payroll_settings', [
            'userid' => $this->wireTestUserId,
            'salary_grade' => 11,
            'step' => 1,
            'basic_salary' => 20000,
            'components' => [[
                'code' => $this->componentCode,
                'name' => 'Test Rate Component',
                'type' => 'earning',
                'taxable' => false,
                'is_global' => true,
                'default_amount' => 0,
                'amount_override' => null,
                'rate_percent_override' => 'not-a-number',
                'is_excluded' => false,
                'effective_from' => null,
                'effective_to' => null,
            ]],
        ]);

        $response->assertStatus(422);

        $assignment = DB::table('hris.employee_payroll_components')
            ->where('userid', $this->wireTestUserId)
            ->first();
        $this->assertNull($assignment);
    }

    /** @test */
    public function validate_rate_percent_overrides_rejects_overflow_and_non_numeric_values()
    {
        $service = app(\App\Services\PayrollServices::class);

        $overflowError = $service->validateRatePercentOverrides([
            ['code' => 'A', 'rate_percent_override' => 100000],
        ]);
        $this->assertNotNull($overflowError);

        $nonNumericError = $service->validateRatePercentOverrides([
            ['code' => 'B', 'rate_percent_override' => 'abc'],
        ]);
        $this->assertNotNull($nonNumericError);

        $validError = $service->validateRatePercentOverrides([
            ['code' => 'C', 'rate_percent_override' => 99999.999],
        ]);
        $this->assertNull($validError);

        $noOverrideError = $service->validateRatePercentOverrides([
            ['code' => 'D', 'rate_percent_override' => null],
        ]);
        $this->assertNull($noOverrideError);
    }
}
