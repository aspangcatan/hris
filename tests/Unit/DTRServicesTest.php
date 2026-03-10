<?php

namespace Tests\Unit;

use App\Services\DTRServices;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\TestCase;

class DTRServicesTest extends TestCase
{
    protected  $dtrService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dtrService = new DTRServices();
    }

    /** @test */
    public function it_counts_present_days_excluding_holidays()
    {
        DB::shouldReceive('table->whereMonth->whereYear->pluck->toArray')
            ->andReturn(['2025-09-10']); // 1 holiday

        DB::shouldReceive('table->join->where->whereRaw->whereYear->whereMonth->pluck->toArray')
            ->andReturn([]);

        DB::shouldReceive('table->where->where->whereMonth->whereYear->pluck->toArray')
            ->zeroOrMoreTimes()
            ->andReturn([]);

        DB::shouldReceive('table->where->whereDate->where->orderBy->value')
            ->zeroOrMoreTimes()
            ->andReturn(null);

        $result = $this->dtrService->getTotalPresent(1, 9, 2025);
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    /** @test */
    public function it_returns_total_late_and_undertime_minutes()
    {
        DB::shouldReceive('table->whereBetween->pluck->map->toArray')->andReturn([]);

        DB::shouldReceive('table->join->where->whereRaw->whereBetween->pluck->map->toArray')->andReturn([]);

        DB::shouldReceive('table->where->whereRaw->whereBetween->get')->andReturn(collect([]));

        DB::shouldReceive('table->join->where->whereBetween->get')->andReturn(collect([
            (object)[
                'schedule_date' => '2025-09-01',
                'time_in' => '08:00:00',
                'time_out' => '17:00:00',
                'time_in_latest' => '09:00:00',
                'time_out_latest' => '18:00:00'
            ]
        ]));

        DB::shouldReceive('table->where->whereDate->where->orderBy->value')
            ->andReturn(Carbon::parse('2025-09-01 09:15:00'), Carbon::parse('2025-09-01 17:00:00'));

        $result = $this->dtrService->getTotalLateAbsent(1, '2025-09-01', '2025-09-30');

        $this->assertArrayHasKey('late_minutes', $result);
        $this->assertArrayHasKey('undertime_minutes', $result);
        $this->assertGreaterThanOrEqual(0, $result['late_minutes']);
    }
}
