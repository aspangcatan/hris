<?php

namespace Tests\Unit;

use App\Services\LeaveCreditService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeaveCreditServiceTest extends TestCase
{
    // RefreshDatabase runs migrate:fresh once per PHPUnit process, dropping
    // and recreating every table in the shared hris database with no reseed -
    // this was silently wiping all real data (payroll_tax_rules,
    // payroll_components, etc.) every time the suite ran. Every other test in
    // this project uses DatabaseTransactions against the shared live-ish
    // database; this test needs the same.
    use DatabaseTransactions;

    protected $leaveCreditService;
    protected $originalPoints;
    protected $originalDeductions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->leaveCreditService = app(LeaveCreditService::class);

        // monthly_earned_points/minute_conversions are seeded with real BIR/CSC
        // data (present/minute are unique columns), so this test overwrites the
        // specific rows it needs and restores them in tearDown, rather than
        // inserting duplicates alongside the real rows.
        $this->originalPoints = DB::table('monthly_earned_points')
            ->whereIn('lwop', [8, 0])
            ->pluck('points', 'lwop');

        DB::table('monthly_earned_points')->updateOrInsert(['lwop' => 8], ['points' => 1.5]);
        DB::table('monthly_earned_points')->updateOrInsert(['lwop' => 0], ['points' => 2.0]);

        $this->originalDeductions = DB::table('minute_conversions')
            ->whereIn('minute', [60, 15])
            ->pluck('points_deduction', 'minute');

        DB::table('minute_conversions')->updateOrInsert(['minute' => 60], ['points_deduction' => 0.1]);
        DB::table('minute_conversions')->updateOrInsert(['minute' => 15], ['points_deduction' => 0.025]);
    }

    protected function tearDown(): void
    {
        // monthly_earned_points and minute_conversions are MyISAM, so
        // RefreshDatabase's transaction wrapping is a silent no-op for them -
        // restore the real values this test overwrote in setUp.
        foreach ($this->originalPoints as $present => $points) {
            DB::table('monthly_earned_points')->where('lwop', $present)->update(['points' => $points]);
        }
        foreach ($this->originalDeductions as $minute => $deduction) {
            DB::table('minute_conversions')->where('minute', $minute)->update(['points_deduction' => $deduction]);
        }
        parent::tearDown();
    }

    /** @test */
    public function it_computes_full_points_for_perfect_attendance()
    {
        $result = $this->leaveCreditService->computeEarnedCredits(22, 0);

        $this->assertEquals(1.5, $result['monthly_points_earned']);
        $this->assertEquals(0, $result['total_deductions']);
        $this->assertEquals(1.5, $result['net_points']);
    }

    /** @test */
    public function it_deducts_points_based_on_late_and_undertime()
    {
        // 75 minutes = 1 hour (0.1) + 15 minutes (0.025) = 0.125 deduction
        $result = $this->leaveCreditService->computeEarnedCredits(22, 75);

        $this->assertEquals(1.5, $result['monthly_points_earned']);
        $this->assertEquals(0.125, $result['total_deductions']);
        $this->assertEquals(1.375, $result['net_points']);
    }

    /** @test */
    public function full_attendance_earns_full_points_regardless_of_month_length()
    {
        // 20/20 working days present in a short month scales to the "30" lookup key.
        $result = $this->leaveCreditService->computeEarnedCredits(20, 0, 20);

        $this->assertEquals(2.0, $result['monthly_points_earned']);
    }

    /** @test */
    public function partial_attendance_scales_to_a_30_day_equivalent_lookup_key()
    {
        // 16/22 present scales to round((16/22)*30) = 22, not raw present (16).
        $result = $this->leaveCreditService->computeEarnedCredits(16, 0, 22);

        $this->assertEquals(1.5, $result['monthly_points_earned']);
    }

    /** @test */
    public function zero_working_days_falls_back_to_raw_present_lookup()
    {
        $result = $this->leaveCreditService->computeEarnedCredits(22, 0, 0);

        $this->assertEquals(1.5, $result['monthly_points_earned']);
    }
}
