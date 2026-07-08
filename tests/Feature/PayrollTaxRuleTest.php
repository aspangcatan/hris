<?php

namespace Tests\Feature;

use App\Services\PayrollServices;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class PayrollTaxRuleTest extends TestCase
{
    use DatabaseTransactions;

    private string $purpose = 'Test Tax Purpose';

    protected function setUp(): void
    {
        parent::setUp();
        // APP_URL contains a subfolder (/hris) which breaks test HTTP client path matching.
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);
        $this->cleanupTaxRules();
    }

    protected function tearDown(): void
    {
        // payroll_tax_rules' storage engine isn't guaranteed to honor
        // DatabaseTransactions rollback in this environment, so clean up explicitly.
        $this->cleanupTaxRules();
        parent::tearDown();
    }

    private function cleanupTaxRules(): void
    {
        DB::table('hris.payroll_tax_rules')->where('purpose', $this->purpose)->delete();
    }

    private function actAsAdmin(): void
    {
        Auth::shouldReceive('id')->andReturn(9301);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'admin']);
    }

    private function callComputeWithholdingTax(float $annualGross, string $purpose)
    {
        $service = app(PayrollServices::class);
        $method = new ReflectionMethod(PayrollServices::class, 'computeWithholdingTax');
        $method->setAccessible(true);
        return $method->invoke($service, $annualGross, $purpose);
    }

    /** @test */
    public function saving_a_new_bracket_immediately_changes_computed_withholding_tax_with_no_code_changes()
    {
        $this->actAsAdmin();

        // A single bracket covering 0-400,000 at 10%.
        $this->withoutMiddleware()->postJson('/payroll-tax-rules', [
            'rules' => [[
                'purpose' => $this->purpose,
                'min_annual' => 0,
                'max_annual' => 400000,
                'rate_percent' => 10,
                'tax_mode' => 'flat',
                'is_active' => true,
            ]],
        ])->assertOk();

        // A gross above the only bracket's ceiling is uncovered -> 0 tax.
        $this->assertEquals(0, $this->callComputeWithholdingTax(500000, $this->purpose));

        // Add a second bracket picking up where the first leaves off - purely
        // a data entry, no code touched.
        $this->withoutMiddleware()->postJson('/payroll-tax-rules', [
            'rules' => [[
                'purpose' => $this->purpose,
                'min_annual' => 400000,
                'max_annual' => null,
                'rate_percent' => 20,
                'tax_mode' => 'flat',
                'is_active' => true,
            ]],
        ])->assertOk();

        $this->assertEquals(100000.0, $this->callComputeWithholdingTax(500000, $this->purpose));
    }

    /** @test */
    public function progressive_brackets_compute_marginal_tax_across_multiple_slices()
    {
        $this->actAsAdmin();

        $this->withoutMiddleware()->postJson('/payroll-tax-rules', [
            'rules' => [
                ['purpose' => $this->purpose, 'min_annual' => 0, 'max_annual' => 250000, 'rate_percent' => 0, 'tax_mode' => 'progressive', 'is_active' => true],
                ['purpose' => $this->purpose, 'min_annual' => 250000, 'max_annual' => 400000, 'rate_percent' => 15, 'tax_mode' => 'progressive', 'is_active' => true],
                ['purpose' => $this->purpose, 'min_annual' => 400000, 'max_annual' => null, 'rate_percent' => 20, 'tax_mode' => 'progressive', 'is_active' => true],
            ],
        ])->assertOk();

        // 500,000 gross: 0% on first 250k, 15% on next 150k (22,500), 20% on remaining 100k (20,000) = 42,500.
        $this->assertEquals(42500.0, $this->callComputeWithholdingTax(500000, $this->purpose));
    }

    /** @test */
    public function rejects_a_new_bracket_that_overlaps_an_existing_active_bracket()
    {
        $this->actAsAdmin();

        $this->withoutMiddleware()->postJson('/payroll-tax-rules', [
            'rules' => [
                ['purpose' => $this->purpose, 'min_annual' => 0, 'max_annual' => 400000, 'rate_percent' => 10, 'tax_mode' => 'flat', 'is_active' => true],
                ['purpose' => $this->purpose, 'min_annual' => 400000, 'max_annual' => null, 'rate_percent' => 20, 'tax_mode' => 'flat', 'is_active' => true],
            ],
        ])->assertOk();

        $response = $this->withoutMiddleware()->postJson('/payroll-tax-rules', [
            'rules' => [[
                'purpose' => $this->purpose,
                'min_annual' => 300000,
                'max_annual' => 450000,
                'rate_percent' => 15,
                'tax_mode' => 'flat',
                'is_active' => true,
            ]],
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('overlapping', strtolower($response->json('message')));

        // The bad row must not have been persisted.
        $this->assertEquals(2, DB::table('hris.payroll_tax_rules')->where('purpose', $this->purpose)->count());
    }

    /** @test */
    public function rejects_a_new_bracket_whose_tax_mode_conflicts_with_existing_active_brackets()
    {
        $this->actAsAdmin();

        $this->withoutMiddleware()->postJson('/payroll-tax-rules', [
            'rules' => [[
                'purpose' => $this->purpose,
                'min_annual' => 0,
                'max_annual' => 400000,
                'rate_percent' => 10,
                'tax_mode' => 'progressive',
                'is_active' => true,
            ]],
        ])->assertOk();

        $response = $this->withoutMiddleware()->postJson('/payroll-tax-rules', [
            'rules' => [[
                'purpose' => $this->purpose,
                'min_annual' => 400000,
                'max_annual' => null,
                'rate_percent' => 20,
                'tax_mode' => 'flat',
                'is_active' => true,
            ]],
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('mix tax modes', $response->json('message'));

        $this->assertEquals(1, DB::table('hris.payroll_tax_rules')->where('purpose', $this->purpose)->count());
    }

    /** @test */
    public function a_deactivated_bracket_is_excluded_from_conflict_checks()
    {
        $this->actAsAdmin();

        $this->withoutMiddleware()->postJson('/payroll-tax-rules', [
            'rules' => [[
                'purpose' => $this->purpose,
                'min_annual' => 0,
                'max_annual' => 400000,
                'rate_percent' => 10,
                'tax_mode' => 'flat',
                'is_active' => false,
            ]],
        ])->assertOk();

        // A progressive bracket covering the same range is fine since the
        // only existing row for this purpose is inactive.
        $response = $this->withoutMiddleware()->postJson('/payroll-tax-rules', [
            'rules' => [[
                'purpose' => $this->purpose,
                'min_annual' => 0,
                'max_annual' => 400000,
                'rate_percent' => 15,
                'tax_mode' => 'progressive',
                'is_active' => true,
            ]],
        ]);

        $response->assertOk();
    }

    /** @test */
    public function compute_falls_back_to_the_lowest_brackets_mode_when_existing_data_already_mixes_modes()
    {
        // Simulate a pre-existing data anomaly by bypassing the (now-guarded) endpoint.
        DB::table('hris.payroll_tax_rules')->insert([
            [
                'purpose' => $this->purpose,
                'min_annual' => 0,
                'max_annual' => 400000,
                'rate_percent' => 10,
                'tax_mode' => 'flat',
                'is_active' => 1,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'purpose' => $this->purpose,
                'min_annual' => 400000,
                'max_annual' => null,
                'rate_percent' => 20,
                'tax_mode' => 'progressive',
                'is_active' => 1,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Lowest bracket (min_annual = 0) is 'flat' mode, so the progressive
        // row is ignored rather than silently folded into a marginal sum.
        $this->assertEquals(0, $this->callComputeWithholdingTax(500000, $this->purpose));
    }
}
