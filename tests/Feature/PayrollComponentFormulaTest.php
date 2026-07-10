<?php

namespace Tests\Feature;

use App\Services\PayrollServices;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class PayrollComponentFormulaTest extends TestCase
{
    use DatabaseTransactions;

    private array $testCodes = ['TEST_FORMULA_A', 'TEST_FORMULA_B', 'TEST_FORMULA_C'];

    private int $wireTestUserId = 9602;

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
        // payroll_components/payroll_settings/tdh_user.users/
        // employee_payroll_components are MyISAM in this environment, so
        // DatabaseTransactions rollback is a silent no-op - clean up explicitly.
        DB::table('hris.employee_payroll_components')->where('userid', $this->wireTestUserId)->delete();
        DB::table('hris.payroll_settings')->where('userid', $this->wireTestUserId)->delete();
        DB::table('tdh_user.users')->where('id', $this->wireTestUserId)->delete();
        $this->cleanupTestComponents();
        parent::tearDown();
    }

    private function cleanupTestComponents(): void
    {
        DB::table('hris.payroll_components')->whereIn('code', $this->testCodes)->delete();
    }

    private function actAsAdmin(): void
    {
        Auth::shouldReceive('id')->andReturn(9601);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'admin']);
    }

    /** @test */
    public function rejects_a_percentage_component_with_no_base_selected()
    {
        $service = app(PayrollServices::class);

        $error = $service->validateComponentFormulas([
            'TEST_FORMULA_A' => [
                'calculation_type' => 'percentage',
                'base_type' => null,
                'base_component_code' => null,
                'rate_percent' => 25,
            ],
        ]);

        $this->assertNotNull($error);
        $this->assertStringContainsString('no valid base type', $error);
    }

    /** @test */
    public function rejects_a_component_that_references_itself_as_its_base()
    {
        $service = app(PayrollServices::class);

        $error = $service->validateComponentFormulas([
            'TEST_FORMULA_A' => [
                'calculation_type' => 'percentage',
                'base_type' => 'component',
                'base_component_code' => 'TEST_FORMULA_A',
                'rate_percent' => 25,
            ],
        ]);

        $this->assertNotNull($error);
        $this->assertStringContainsString('cannot reference itself', $error);
    }

    /** @test */
    public function rejects_a_reference_to_an_unknown_component()
    {
        $service = app(PayrollServices::class);

        $error = $service->validateComponentFormulas([
            'TEST_FORMULA_A' => [
                'calculation_type' => 'percentage',
                'base_type' => 'component',
                'base_component_code' => 'DOES_NOT_EXIST',
                'rate_percent' => 25,
            ],
        ]);

        $this->assertNotNull($error);
        $this->assertStringContainsString('unknown base component', $error);
    }

    /** @test */
    public function rejects_a_two_hop_circular_reference()
    {
        $service = app(PayrollServices::class);

        $error = $service->validateComponentFormulas([
            'TEST_FORMULA_A' => [
                'calculation_type' => 'percentage',
                'base_type' => 'component',
                'base_component_code' => 'TEST_FORMULA_B',
                'rate_percent' => 25,
            ],
            'TEST_FORMULA_B' => [
                'calculation_type' => 'percentage',
                'base_type' => 'component',
                'base_component_code' => 'TEST_FORMULA_A',
                'rate_percent' => 10,
            ],
        ]);

        $this->assertNotNull($error);
        $this->assertStringContainsString('Circular reference', $error);
    }

    /** @test */
    public function rejects_a_negative_percentage_rate()
    {
        $service = app(PayrollServices::class);

        $error = $service->validateComponentFormulas([
            'TEST_FORMULA_A' => [
                'calculation_type' => 'percentage',
                'base_type' => 'basic_salary',
                'base_component_code' => null,
                'rate_percent' => -5,
            ],
        ]);

        $this->assertNotNull($error);
        $this->assertStringContainsString('invalid percentage rate', $error);
    }

    /** @test */
    public function accepts_a_valid_chained_percentage_set()
    {
        $service = app(PayrollServices::class);

        $error = $service->validateComponentFormulas([
            'TEST_FORMULA_A' => [
                'calculation_type' => 'fixed',
                'base_type' => null,
                'base_component_code' => null,
                'rate_percent' => null,
            ],
            'TEST_FORMULA_B' => [
                'calculation_type' => 'percentage',
                'base_type' => 'basic_salary',
                'base_component_code' => null,
                'rate_percent' => 25,
            ],
            'TEST_FORMULA_C' => [
                'calculation_type' => 'percentage',
                'base_type' => 'component',
                'base_component_code' => 'TEST_FORMULA_B',
                'rate_percent' => 10,
            ],
        ]);

        $this->assertNull($error);
    }

    /** @test */
    public function resolves_a_basic_salary_percentage_component()
    {
        $rows = collect([
            (object) [
                'code' => 'TEST_FORMULA_A',
                'default_amount' => 0,
                'amount_override' => null,
                'calculation_type' => 'percentage',
                'base_type' => 'basic_salary',
                'base_component_code' => null,
                'rate_percent' => 25,
            ],
        ]);

        $resolved = $this->callResolveComponentAmounts($rows, 20000.0);

        $this->assertEqualsWithDelta(5000.0, $resolved->firstWhere('code', 'TEST_FORMULA_A')->amount, 0.001);
    }

    /** @test */
    public function resolves_a_component_chained_off_another_computed_component_regardless_of_payload_order()
    {
        $rows = collect([
            (object) [
                'code' => 'TEST_FORMULA_B',
                'default_amount' => 0,
                'amount_override' => null,
                'calculation_type' => 'percentage',
                'base_type' => 'component',
                'base_component_code' => 'TEST_FORMULA_A',
                'rate_percent' => 10,
            ],
            (object) [
                'code' => 'TEST_FORMULA_A',
                'default_amount' => 0,
                'amount_override' => null,
                'calculation_type' => 'percentage',
                'base_type' => 'basic_salary',
                'base_component_code' => null,
                'rate_percent' => 25,
            ],
        ]);

        // B is listed before A in $rows, but B depends on A - proving
        // resolution order follows the dependency graph, not payload order.
        $resolved = $this->callResolveComponentAmounts($rows, 20000.0);

        $this->assertEqualsWithDelta(5000.0, $resolved->firstWhere('code', 'TEST_FORMULA_A')->amount, 0.001);
        $this->assertEqualsWithDelta(500.0, $resolved->firstWhere('code', 'TEST_FORMULA_B')->amount, 0.001);
    }

    /** @test */
    public function an_amount_override_wins_over_the_formula_and_feeds_dependents()
    {
        $rows = collect([
            (object) [
                'code' => 'TEST_FORMULA_A',
                'default_amount' => 0,
                'amount_override' => 9999.0,
                'calculation_type' => 'percentage',
                'base_type' => 'basic_salary',
                'base_component_code' => null,
                'rate_percent' => 25,
            ],
            (object) [
                'code' => 'TEST_FORMULA_B',
                'default_amount' => 0,
                'amount_override' => null,
                'calculation_type' => 'percentage',
                'base_type' => 'component',
                'base_component_code' => 'TEST_FORMULA_A',
                'rate_percent' => 10,
            ],
        ]);

        $resolved = $this->callResolveComponentAmounts($rows, 20000.0);

        $this->assertEqualsWithDelta(9999.0, $resolved->firstWhere('code', 'TEST_FORMULA_A')->amount, 0.001);
        // 10% of the override (9,999), not of the 25%-of-basic-salary formula.
        $this->assertEqualsWithDelta(999.9, $resolved->firstWhere('code', 'TEST_FORMULA_B')->amount, 0.001);
    }

    /** @test */
    public function rounds_computed_amounts_to_three_decimal_places()
    {
        $rows = collect([
            (object) [
                'code' => 'TEST_FORMULA_A',
                'default_amount' => 0,
                'amount_override' => null,
                'calculation_type' => 'percentage',
                'base_type' => 'basic_salary',
                'base_component_code' => null,
                'rate_percent' => 33.333,
            ],
        ]);

        $resolved = $this->callResolveComponentAmounts($rows, 100.0);

        $this->assertEqualsWithDelta(33.333, $resolved->firstWhere('code', 'TEST_FORMULA_A')->amount, 0.0005);
    }

    /** @test */
    public function a_cyclic_pair_resolves_to_zero_instead_of_looping_forever()
    {
        $rows = collect([
            (object) [
                'code' => 'TEST_FORMULA_A',
                'default_amount' => 0,
                'amount_override' => null,
                'calculation_type' => 'percentage',
                'base_type' => 'component',
                'base_component_code' => 'TEST_FORMULA_B',
                'rate_percent' => 25,
            ],
            (object) [
                'code' => 'TEST_FORMULA_B',
                'default_amount' => 0,
                'amount_override' => null,
                'calculation_type' => 'percentage',
                'base_type' => 'component',
                'base_component_code' => 'TEST_FORMULA_A',
                'rate_percent' => 10,
            ],
        ]);

        // Defensive backstop only - validateComponentFormulas() (Task 2) is
        // what actually keeps this shape out of real saved data.
        $resolved = $this->callResolveComponentAmounts($rows, 20000.0);

        $this->assertEqualsWithDelta(0.0, $resolved->firstWhere('code', 'TEST_FORMULA_A')->amount, 0.001);
        $this->assertEqualsWithDelta(0.0, $resolved->firstWhere('code', 'TEST_FORMULA_B')->amount, 0.001);
    }

    private function callResolveComponentAmounts(\Illuminate\Support\Collection $rows, float $basicSalary): \Illuminate\Support\Collection
    {
        $service = app(PayrollServices::class);
        $method = new ReflectionMethod(PayrollServices::class, 'resolveComponentAmounts');
        $method->setAccessible(true);
        return $method->invoke($service, $rows, $basicSalary);
    }

    /** @test */
    public function get_employee_dynamic_components_resolves_a_percentage_component_against_the_employees_basic_salary()
    {
        DB::table('tdh_user.users')->where('id', $this->wireTestUserId)->delete();
        DB::table('tdh_user.users')->insert([
            'id' => $this->wireTestUserId,
            'fname' => 'Formula',
            'lname' => 'Tester',
            'username' => 'formulatester' . $this->wireTestUserId,
            'password' => 'x',
            'division' => 2,
            'section' => 21,
            'designation' => 52,
        ]);

        DB::table('hris.payroll_settings')->where('userid', $this->wireTestUserId)->delete();
        DB::table('hris.payroll_settings')->insert([
            'userid' => $this->wireTestUserId,
            'basic_salary' => 20000,
            'salary_grade' => 11,
            'step' => 1,
        ]);

        DB::table('hris.payroll_components')->insert([
            'code' => 'TEST_FORMULA_A',
            'name' => 'Test Hazard Pay',
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

        $service = app(PayrollServices::class);
        $method = new ReflectionMethod(PayrollServices::class, 'getEmployeeDynamicComponents');
        $method->setAccessible(true);
        $components = $method->invoke($service, $this->wireTestUserId, '2027-07-01', '2027-07-15', 20000.0);

        $component = $components->firstWhere('code', 'TEST_FORMULA_A');
        $this->assertNotNull($component);
        $this->assertEqualsWithDelta(5000.0, $component->amount, 0.001);
    }

    /** @test */
    public function a_base_component_excluded_for_the_employee_resolves_its_dependent_to_zero()
    {
        DB::table('tdh_user.users')->where('id', $this->wireTestUserId)->delete();
        DB::table('tdh_user.users')->insert([
            'id' => $this->wireTestUserId,
            'fname' => 'Formula',
            'lname' => 'Tester',
            'username' => 'formulatester' . $this->wireTestUserId,
            'password' => 'x',
            'division' => 2,
            'section' => 21,
            'designation' => 52,
        ]);

        DB::table('hris.payroll_settings')->where('userid', $this->wireTestUserId)->delete();
        DB::table('hris.payroll_settings')->insert([
            'userid' => $this->wireTestUserId,
            'basic_salary' => 20000,
            'salary_grade' => 11,
            'step' => 1,
        ]);

        $baseId = DB::table('hris.payroll_components')->insertGetId([
            'code' => 'TEST_FORMULA_A',
            'name' => 'Test Base Earning',
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
            'sort_order' => 998,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('hris.payroll_components')->insert([
            'code' => 'TEST_FORMULA_B',
            'name' => 'Test Dependent Earning',
            'type' => 'earning',
            'is_global' => 1,
            'taxable' => 0,
            'default_amount' => 0,
            'calculation_type' => 'percentage',
            'base_type' => 'component',
            'base_component_code' => 'TEST_FORMULA_A',
            'rate_percent' => 10,
            'is_active' => 1,
            'is_cutoff_adjustable' => 0,
            'sort_order' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // This employee is explicitly excluded from the base component.
        DB::table('hris.employee_payroll_components')->insert([
            'payroll_component_id' => $baseId,
            'userid' => $this->wireTestUserId,
            'amount' => 0,
            'amount_override' => null,
            'is_active' => 0,
            'is_excluded' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(PayrollServices::class);
        $method = new ReflectionMethod(PayrollServices::class, 'getEmployeeDynamicComponents');
        $method->setAccessible(true);
        $components = $method->invoke($service, $this->wireTestUserId, '2027-07-01', '2027-07-15', 20000.0);

        // TEST_FORMULA_A itself is filtered out entirely (excluded), and
        // TEST_FORMULA_B - 10% of a base that isn't there - resolves to 0
        // rather than erroring or being dropped.
        $this->assertNull($components->firstWhere('code', 'TEST_FORMULA_A'));
        $dependent = $components->firstWhere('code', 'TEST_FORMULA_B');
        $this->assertNotNull($dependent);
        $this->assertEqualsWithDelta(0.0, $dependent->amount, 0.001);

        DB::table('hris.employee_payroll_components')->where('payroll_component_id', $baseId)->where('userid', $this->wireTestUserId)->delete();
    }
}
