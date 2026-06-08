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
    }

    protected function tearDown(): void
    {
        if ($this->seededMasterId) {
            DB::table('hris.cutoff_summaries_master')->where('id', $this->seededMasterId)->delete();
        }
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
}
