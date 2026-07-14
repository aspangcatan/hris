<?php

namespace Tests\Feature;

use App\Services\PayrollServices;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class PayrollComponentRateOverrideTest extends TestCase
{
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
}
