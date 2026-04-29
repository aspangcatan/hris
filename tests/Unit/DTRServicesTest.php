<?php

namespace Tests\Unit;

use App\Services\DTRServices;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\TestCase;

class DTRServicesTest extends TestCase
{
    protected DTRServices $dtrService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dtrService = new DTRServices();
    }

    /** @test */
    public function it_counts_present_days_from_expected_schedules_only()
    {
        DB::shouldReceive('table->whereBetween->pluck->map->toArray')
            ->once()
            ->andReturn([]);

        DB::shouldReceive('table->join->where->whereRaw->whereBetween->pluck->map->toArray')
            ->once()
            ->andReturn([]);

        DB::shouldReceive('table->where->where->whereBetween->pluck->map->toArray')
            ->once()
            ->andReturn([]);

        DB::shouldReceive('table->where->where->whereBetween->pluck->map->toArray')
            ->once()
            ->andReturn([]);

        DB::shouldReceive('table->where->whereRaw->whereRaw->whereBetween->pluck->map->unique->values->toArray')
            ->once()
            ->andReturn([]);

        DB::shouldReceive('table->join->where->whereBetween->get')
            ->once()
            ->andReturn(collect([
                (object) [
                    'schedule_date' => '2025-09-01',
                    'type' => 'FIXED',
                    'time_in' => '08:00:00',
                    'time_out' => '17:00:00',
                    'time_in_latest' => '09:00:00',
                    'time_out_latest' => '18:00:00',
                ],
            ]));

        DB::shouldReceive('table->where->whereBetween->orderBy->get')
            ->once()
            ->andReturn(collect([
                (object) ['created_at' => '2025-09-01 08:00:00', 'status' => 'IN'],
                (object) ['created_at' => '2025-09-01 17:00:00', 'status' => 'OUT'],
            ]));

        $result = $this->dtrService->getTotalPresentByRange(1, '2025-09-01', '2025-09-30');

        $this->assertSame(1, $result);
    }

    /** @test */
    public function it_returns_late_and_absence_totals_using_plotted_schedule_dates()
    {
        DB::shouldReceive('table->whereBetween->pluck->map->toArray')
            ->twice()
            ->andReturn([]);

        DB::shouldReceive('table->join->where->whereRaw->whereBetween->pluck->map->toArray')
            ->twice()
            ->andReturn([]);

        DB::shouldReceive('table->where->where->whereBetween->pluck->map->toArray')
            ->twice()
            ->andReturn([]);

        DB::shouldReceive('table->where->where->whereBetween->pluck->map->toArray')
            ->twice()
            ->andReturn([]);

        DB::shouldReceive('table->where->whereRaw->whereRaw->whereBetween->pluck->map->unique->values->toArray')
            ->once()
            ->andReturn([]);

        DB::shouldReceive('table->join->where->whereBetween->get')
            ->twice()
            ->andReturn(collect([
                (object) [
                    'schedule_date' => '2025-09-01',
                    'type' => 'FIXED',
                    'time_in' => '08:00:00',
                    'time_out' => '17:00:00',
                    'time_in_latest' => '09:00:00',
                    'time_out_latest' => '18:00:00',
                ],
            ]));

        DB::shouldReceive('table->where->whereBetween->orderBy->get')
            ->twice()
            ->andReturn(collect([
                (object) ['created_at' => '2025-09-01 09:15:00', 'status' => 'IN'],
                (object) ['created_at' => '2025-09-01 17:00:00', 'status' => 'OUT'],
            ]));

        DB::shouldReceive('table->where->whereRaw->whereBetween->get')
            ->once()
            ->andReturn(collect([]));

        $result = $this->dtrService->getTotalLateAbsent(1, '2025-09-01', '2025-09-30');

        $this->assertSame(15, $result['late_minutes']);
        $this->assertSame(0, $result['undertime_minutes']);
        $this->assertSame(0, $result['absences']);
    }
}
