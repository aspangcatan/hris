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
        // No weekend cases and no database lookup (the DB facade is mocked here).
        \App\Services\LwopCaseService::override([]);
    }

    protected function tearDown(): void
    {
        \App\Services\LwopCaseService::override(null);
        // Plain PHPUnit TestCase: reset the DB facade mock so one test's
        // query expectations don't answer the next test's queries.
        \Mockery::close();
        DB::clearResolvedInstances();
        parent::tearDown();
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
        DB::shouldReceive('table->whereBetween->get')
            ->once()
            ->andReturn(collect([]));

        DB::shouldReceive('table->join->where->whereRaw->whereBetween->pluck->map->toArray')
            ->once()
            ->andReturn([]);

        // getApprovedSpecialDutyDatesByRange is called twice by getTotalLateAbsent
        // (once for the hospitalOrders exemption, once for absenceExemptDates), and
        // each call issues two sub-queries of this same shape (hospital orders, overtime).
        DB::shouldReceive('table->where->where->whereBetween->pluck->map->toArray')
            ->times(4)
            ->andReturn([]);

        DB::shouldReceive('table->where->whereRaw->whereBetween->get')
            ->once()
            ->andReturn(collect([]));

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
                    'break_out' => null,
                    'break_in' => null,
                ],
            ]));

        DB::shouldReceive('table->where->whereBetween->orderBy->get')
            ->once()
            ->andReturn(collect([
                (object) ['created_at' => '2025-09-01 09:15:00', 'status' => 'IN'],
                (object) ['created_at' => '2025-09-01 17:00:00', 'status' => 'OUT'],
            ]));

        $result = $this->dtrService->getTotalLateAbsent(1, '2025-09-01', '2025-09-30');

        $this->assertSame(15, $result['late_minutes']);
        $this->assertSame(0, $result['undertime_minutes']);
        $this->assertSame(0, $result['absences']);
    }

    private function invokePrivateMethod(string $method, array $args)
    {
        $reflection = new \ReflectionMethod($this->dtrService, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($this->dtrService, $args);
    }

    /** LWOP rows the getLwopDaysByRange query returns, plus no holidays and the given plotted schedules. */
    private function mockLwopQueries(array $lwopRows, array $plottedSchedules = []): void
    {
        DB::shouldReceive('table->join->where->where->whereRaw->where->where->whereBetween->get')
            ->once()
            ->andReturn(collect(array_map(fn($row) => (object) $row, $lwopRows)));

        DB::shouldReceive('table->whereBetween->pluck->map->toArray')
            ->once()
            ->andReturn([]);

        DB::shouldReceive('table->join->where->whereBetween->get')
            ->once()
            ->andReturn(collect(array_map(fn($row) => (object) $row, $plottedSchedules)));
    }

    /** @test */
    public function it_returns_only_filed_lwop_dates_within_the_requested_range()
    {
        // No weekend cases (setUp): only the filed dates inside the range count.
        $this->mockLwopQueries(array_map(fn($date) => ['date_applied' => $date, 'hours' => 8], [
            '2025-08-29', // Friday before the range - outside it
            '2025-09-01', '2025-09-02', '2025-09-05', '2025-09-08',
            '2025-09-12', // after the range - outside it
        ]));

        $dates = $this->invokePrivateMethod('getApprovedLwopDatesByRange', [1, '2025-09-01', '2025-09-09']);

        $this->assertSame(['2025-09-01', '2025-09-02', '2025-09-05', '2025-09-08'], $dates);
    }

    /** @test */
    public function a_half_day_lwop_counts_half_a_day()
    {
        $this->mockLwopQueries([
            ['date_applied' => '2025-09-04', 'hours' => 8],
            ['date_applied' => '2025-09-05', 'hours' => 4],
        ]);

        $lwop = $this->invokePrivateMethod('getLwopDaysByRange', [1, '2025-09-01', '2025-09-30']);

        $this->assertSame(['days' => 1.0, 'hours' => null], $lwop['2025-09-04']);
        $this->assertSame(['days' => 0.5, 'hours' => 4], $lwop['2025-09-05']);
    }

    /** @test */
    public function lwop_fraction_uses_the_plotted_shift_length()
    {
        // 10-hour CWW shift 07:00-18:00 with a 12:00-13:00 break: 4 h LWOP = 0.4 day; 10 h = whole day.
        $shift = ['code' => 'CWW', 'type' => 'FIXED', 'time_in' => '07:00:00', 'time_out' => '18:00:00',
            'time_in_latest' => null, 'time_out_latest' => null, 'break_out' => '12:00:00', 'break_in' => '13:00:00'];
        $this->mockLwopQueries(
            [['date_applied' => '2025-09-01', 'hours' => 4], ['date_applied' => '2025-09-02', 'hours' => 10]],
            [$shift + ['schedule_date' => '2025-09-01'], $shift + ['schedule_date' => '2025-09-02']]
        );

        $lwop = $this->invokePrivateMethod('getLwopDaysByRange', [1, '2025-09-01', '2025-09-30']);

        $this->assertSame(0.4, $lwop['2025-09-01']['days']);
        $this->assertSame(['days' => 1.0, 'hours' => null], $lwop['2025-09-02']);
    }
}
