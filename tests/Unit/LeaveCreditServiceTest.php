<?php

namespace Tests\Unit;

use App\Services\LeaveCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeaveCreditServiceTest extends TestCase
{
    use RefreshDatabase;

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
