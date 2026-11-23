<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The period a payroll pays can differ from the cutoff whose DTR it uses
 * (e.g. an October payroll from the September cutoff). The user sets it on the
 * Create Payroll form; left out, it is the cutoff's own dates.
 */
class PayrollPayPeriodTest extends TestCase
{
    use DatabaseTransactions;

    private int $userId = 9761;
    private int $cutoffId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');

        Auth::shouldReceive('id')->andReturn($this->userId);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn(null);
        session(['access_rights' => 'admin']);

        $this->cutoffId = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title' => 'September DTR ' . uniqid(), 'status' => 'finalized', 'snapshot_version' => 1,
            'period_start' => '2027-09-01', 'period_end' => '2027-09-30', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function create(array $input = [])
    {
        return $this->withoutMiddleware()->postJson('/save_payroll', array_merge([
            'title' => 'Pay Period Test', 'cutoff_id' => $this->cutoffId,
        ], $input));
    }

    private function latest(): ?object
    {
        return DB::table('hris.payrolls')->where('cutoff_id', $this->cutoffId)->latest('id')->first();
    }

    /** @test */
    public function without_dates_the_payroll_pays_the_cutoff_period()
    {
        $this->create()->assertOk();

        $this->assertSame('2027-09-01', $this->latest()->start_date);
        $this->assertSame('2027-09-30', $this->latest()->end_date);
    }

    /** @test */
    public function the_user_can_set_the_period_paid_apart_from_the_cutoff()
    {
        $this->create(['start_date' => '2027-10-01', 'end_date' => '2027-10-31'])->assertOk();
        $payroll = $this->latest();

        $this->assertSame('2027-10-01', $payroll->start_date);
        $this->assertSame('2027-10-31', $payroll->end_date);
        // The DTR still comes from the cutoff.
        $this->assertSame('2027-09-01', $payroll->cutoff_start_date);
        $this->assertSame('2027-09-30', $payroll->cutoff_end_date);
    }

    /** @test */
    public function an_end_before_the_start_or_only_one_date_is_rejected()
    {
        $this->create(['start_date' => '2027-10-31', 'end_date' => '2027-10-01'])->assertStatus(422);
        $this->create(['start_date' => '2027-10-01'])->assertStatus(422);
        $this->create(['start_date' => 'October', 'end_date' => '2027-10-31'])->assertStatus(422);
        $this->assertNull($this->latest());
    }
}
