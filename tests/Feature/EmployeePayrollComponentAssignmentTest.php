<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeePayrollComponentAssignmentTest extends TestCase
{
    use DatabaseTransactions;

    private int $userId = 9701;
    private string $componentCode = 'TEST_NONGLOBAL_DEDUCTION';

    protected function setUp(): void
    {
        parent::setUp();
        // APP_URL contains a subfolder (/hris) which breaks test HTTP client path matching.
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);
        $this->cleanupTestComponent();
        DB::table('tdh_user.users')->where('id', $this->userId)->delete();
        DB::table('tdh_user.users')->insert([
            'id' => $this->userId,
            'fname' => 'Assign',
            'lname' => 'Tester',
            'username' => 'assigntester' . $this->userId,
            'password' => 'x',
            'division' => 2,
            'section' => 21,
            'designation' => 52,
        ]);
    }

    protected function tearDown(): void
    {
        // payroll_components/payroll_settings/tdh_user.users/
        // employee_payroll_components are MyISAM in this environment, so
        // DatabaseTransactions rollback is a silent no-op - clean up explicitly.
        DB::table('hris.employee_payroll_components')->where('userid', $this->userId)->delete();
        DB::table('hris.payroll_settings')->where('userid', $this->userId)->delete();
        DB::table('tdh_user.users')->where('id', $this->userId)->delete();
        $this->cleanupTestComponent();
        parent::tearDown();
    }

    private function cleanupTestComponent(): void
    {
        DB::table('hris.payroll_components')->where('code', $this->componentCode)->delete();
    }

    private function actAsAdmin(): void
    {
        Auth::shouldReceive('id')->andReturn($this->userId);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'admin']);
    }

    /** @test */
    public function assigning_an_amount_to_a_non_global_component_persists_it_without_promoting_the_catalog_to_global()
    {
        $componentId = DB::table('hris.payroll_components')->insertGetId([
            'code' => $this->componentCode,
            'name' => 'Test Non-Global Deduction',
            'type' => 'deduction',
            'is_global' => 0,
            'taxable' => 0,
            'default_amount' => 0,
            'is_active' => 1,
            'is_cutoff_adjustable' => 0,
            'sort_order' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actAsAdmin();

        // Mirrors the payload the Employee modal's Payroll tab now builds for
        // a non-global row: is_global is echoed back from the catalog
        // (false), not hardcoded true - see resources/views/js/employee.blade.php.
        $response = $this->withoutMiddleware()->postJson('/save_payroll_settings', [
            'userid' => $this->userId,
            'salary_grade' => 11,
            'step' => 1,
            'basic_salary' => 20000,
            'components' => [[
                'code' => $this->componentCode,
                'name' => 'Test Non-Global Deduction',
                'type' => 'deduction',
                'taxable' => false,
                'is_global' => false,
                'default_amount' => 0,
                'amount_override' => 500,
                'is_excluded' => false,
                'effective_from' => null,
                'effective_to' => null,
            ]],
        ]);
        $response->assertOk();

        // The catalog row must NOT have been promoted to global by an
        // employee-specific save.
        $catalog = DB::table('hris.payroll_components')->where('id', $componentId)->first();
        $this->assertEquals(0, (int) $catalog->is_global);

        $assignment = DB::table('hris.employee_payroll_components')
            ->where('payroll_component_id', $componentId)
            ->where('userid', $this->userId)
            ->first();
        $this->assertNotNull($assignment);
        $this->assertEqualsWithDelta(500.0, (float) $assignment->amount_override, 0.01);
        $this->assertEquals(1, (int) $assignment->is_active);
        $this->assertEquals(0, (int) $assignment->is_excluded);

        // getPayrollSettings (what the modal re-fetches) must reflect it too.
        $reload = $this->withoutMiddleware()->get('/payroll-settings?userid=' . $this->userId);
        $reload->assertOk();
        $reloadedComponent = collect($reload->json('components'))->firstWhere('code', $this->componentCode);
        $this->assertNotNull($reloadedComponent);
        $this->assertEqualsWithDelta(500.0, (float) $reloadedComponent['amount_override'], 0.01);
    }

    /** @test */
    public function clearing_a_previously_assigned_amount_deactivates_it_on_the_next_save()
    {
        $componentId = DB::table('hris.payroll_components')->insertGetId([
            'code' => $this->componentCode,
            'name' => 'Test Non-Global Deduction',
            'type' => 'deduction',
            'is_global' => 0,
            'taxable' => 0,
            'default_amount' => 0,
            'is_active' => 1,
            'is_cutoff_adjustable' => 0,
            'sort_order' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actAsAdmin();

        $this->withoutMiddleware()->postJson('/save_payroll_settings', [
            'userid' => $this->userId,
            'salary_grade' => 11,
            'step' => 1,
            'basic_salary' => 20000,
            'components' => [[
                'code' => $this->componentCode,
                'name' => 'Test Non-Global Deduction',
                'type' => 'deduction',
                'taxable' => false,
                'is_global' => false,
                'default_amount' => 0,
                'amount_override' => 500,
                'is_excluded' => false,
                'effective_from' => null,
                'effective_to' => null,
            ]],
        ])->assertOk();

        // Re-save with no components at all - mirrors the Amount field being
        // cleared, so collectDynamicOverrides() omits the row entirely.
        $this->withoutMiddleware()->postJson('/save_payroll_settings', [
            'userid' => $this->userId,
            'salary_grade' => 11,
            'step' => 1,
            'basic_salary' => 20000,
            'components' => [],
        ])->assertOk();

        $assignment = DB::table('hris.employee_payroll_components')
            ->where('payroll_component_id', $componentId)
            ->where('userid', $this->userId)
            ->first();
        $this->assertNotNull($assignment);
        $this->assertEquals(0, (int) $assignment->is_active);
    }
}
