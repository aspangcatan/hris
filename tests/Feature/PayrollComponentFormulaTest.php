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
        // payroll_components is MyISAM in this environment, so
        // DatabaseTransactions rollback is a silent no-op - clean up explicitly.
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
}
