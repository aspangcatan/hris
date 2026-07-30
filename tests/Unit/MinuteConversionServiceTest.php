<?php

namespace Tests\Unit;

use App\Services\MinuteConversionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MinuteConversionServiceTest extends TestCase
{
    // minute_conversions is MyISAM, so DatabaseTransactions rollback is a
    // silent no-op for it - restore the real rows this test overwrites,
    // same pattern as LeaveCreditServiceTest.
    use DatabaseTransactions;

    protected $service;
    protected $originalDeductions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MinuteConversionService();

        $this->originalDeductions = DB::table('minute_conversions')
            ->whereIn('minute', [60, 15])
            ->pluck('points_deduction', 'minute');

        DB::table('minute_conversions')->updateOrInsert(['minute' => 60], ['points_deduction' => 0.1]);
        DB::table('minute_conversions')->updateOrInsert(['minute' => 15], ['points_deduction' => 0.025]);
    }

    protected function tearDown(): void
    {
        foreach ($this->originalDeductions as $minute => $deduction) {
            DB::table('minute_conversions')->where('minute', $minute)->update(['points_deduction' => $deduction]);
        }
        parent::tearDown();
    }

    /** @test */
    public function it_multiplies_full_hour_blocks_by_the_sixty_minute_rate()
    {
        // 480 minutes = 8 full hours, no remainder
        $this->assertEquals(0.8, $this->service->computeDeduction(480));
    }

    /** @test */
    public function it_adds_a_remainder_minute_lookup_past_the_last_full_hour()
    {
        // 75 minutes = 1 hour (0.1) + 15 minutes (0.025)
        $this->assertEquals(0.125, $this->service->computeDeduction(75));
    }

    /** @test */
    public function it_returns_zero_for_zero_minutes()
    {
        $this->assertEquals(0.0, $this->service->computeDeduction(0));
    }
}
