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

    protected function setUp(): void
    {
        parent::setUp();
        $this->leaveCreditService = new LeaveCreditService();

        // Seed dummy data
        DB::table('monthly_earned_points')->insert([
            ['present' => 22, 'points' => 1.5],
            ['present' => 30, 'points' => 2.0],
        ]);

        DB::table('minute_conversions')->insert([
            ['minute' => 60, 'points_deduction' => 0.1],
            ['minute' => 15, 'points_deduction' => 0.025],
        ]);
    }

    protected function tearDown(): void
    {
        // monthly_earned_points and minute_conversions are MyISAM, so
        // RefreshDatabase's transaction wrapping is a silent no-op for them -
        // without this cleanup, every test run permanently leaks these rows
        // into the shared database (this is how the real minute_conversions
        // table ended up with duplicate/incorrect rows in the first place,
        // which fed wrong values into OvertimeApprovalTest's CTO credit math).
        DB::table('monthly_earned_points')->where('present', 22)->where('points', 1.5)->delete();
        DB::table('monthly_earned_points')->where('present', 30)->where('points', 2.0)->delete();
        DB::table('minute_conversions')->where('minute', 60)->where('points_deduction', 0.1)->delete();
        DB::table('minute_conversions')->where('minute', 15)->where('points_deduction', 0.025)->delete();
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
}
