<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A payroll must name its tax purpose, and the purpose must have active tax
 * rules - otherwise withholding tax would silently be 0.
 */
class PayrollTaxPurposeRequiredTest extends TestCase
{
    use DatabaseTransactions;

    private int $cutoffId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');

        Auth::shouldReceive('id')->andReturn(9791);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn(null);
        session(['access_rights' => 'admin']);

        $this->cutoffId = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title' => 'Tax Purpose ' . uniqid(), 'status' => 'finalized', 'snapshot_version' => 1,
            'period_start' => '2027-09-01', 'period_end' => '2027-09-30', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function create(array $input)
    {
        return $this->withoutMiddleware()->postJson('/save_payroll', array_merge(['title' => 'Tax Purpose Test', 'cutoff_id' => $this->cutoffId], $input));
    }

    /** @test */
    public function a_payroll_needs_a_tax_purpose()
    {
        $this->create([])->assertStatus(422)->assertJsonValidationErrors('tax_purpose', 'errors');
        $this->create(['tax_purpose' => ''])->assertStatus(422);
        $this->assertFalse(DB::table('hris.payrolls')->where('cutoff_id', $this->cutoffId)->exists());
    }

    /** @test */
    public function the_tax_purpose_must_have_active_rules()
    {
        DB::table('hris.payroll_tax_rules')->insert([
            'purpose' => 'TEST INACTIVE PURPOSE', 'min_annual' => 0, 'rate_percent' => 10, 'tax_mode' => 'flat', 'basis' => 'monthly', 'is_active' => 0,
        ]);

        $this->create(['tax_purpose' => 'NO SUCH PURPOSE'])->assertStatus(422);
        $this->create(['tax_purpose' => 'TEST INACTIVE PURPOSE'])->assertStatus(422);

        $this->create(['tax_purpose' => $this->testTaxPurpose()])->assertOk();
        $this->assertSame($this->testTaxPurpose(), DB::table('hris.payrolls')->where('cutoff_id', $this->cutoffId)->value('tax_purpose'));
    }
}
