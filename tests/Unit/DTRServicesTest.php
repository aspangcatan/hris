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
        // Base LWOP rules without a database lookup (the DB facade is mocked here).
        \App\Services\LwopRuleService::override([]);
    }

    protected function tearDown(): void
    {
        \App\Services\LwopRuleService::override(null);
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

    /** @test */
    public function it_does_not_absorb_weekend_for_a_short_intermittent_lwop_run()
    {
        // Fri 2025-09-05 LWOP, weekend either side, no other filed date nearby.
        $effective = $this->invokePrivateMethod('calculateEffectiveLwopDates', [
            ['2025-09-05'],
            [],
        ]);

        sort($effective);
        $this->assertSame(['2025-09-05'], $effective);
    }

    /** @test */
    public function it_absorbs_bridged_weekend_when_continuous_run_exceeds_seven_days()
    {
        // Mon 09-01 .. Fri 09-05 LWOP, weekend 09-06/07, Mon 09-08 .. Tue 09-09 LWOP.
        // Span = 9 calendar days (> 7) -> weekend absorbed.
        $effective = $this->invokePrivateMethod('calculateEffectiveLwopDates', [
            ['2025-09-01', '2025-09-02', '2025-09-03', '2025-09-04', '2025-09-05', '2025-09-08', '2025-09-09'],
            [],
        ]);

        sort($effective);
        $this->assertSame([
            '2025-09-01', '2025-09-02', '2025-09-03', '2025-09-04',
            '2025-09-05', '2025-09-06', '2025-09-07', '2025-09-08', '2025-09-09',
        ], $effective);
    }

    /** @test */
    public function it_does_not_absorb_weekend_when_run_span_is_exactly_seven_days()
    {
        // Tue 09-02 .. Fri 09-05 LWOP, weekend 09-06/07, Mon 09-08 LWOP.
        // Span = 7 calendar days exactly -> NOT absorbed (rule requires > 7).
        $effective = $this->invokePrivateMethod('calculateEffectiveLwopDates', [
            ['2025-09-02', '2025-09-03', '2025-09-04', '2025-09-05', '2025-09-08'],
            [],
        ]);

        sort($effective);
        $this->assertSame([
            '2025-09-02', '2025-09-03', '2025-09-04', '2025-09-05', '2025-09-08',
        ], $effective);
    }

    /** @test */
    public function it_absorbs_weekend_when_run_span_is_eight_days()
    {
        // Same as above plus Tue 09-09 LWOP -> span = 8 calendar days (> 7) -> absorbed.
        $effective = $this->invokePrivateMethod('calculateEffectiveLwopDates', [
            ['2025-09-02', '2025-09-03', '2025-09-04', '2025-09-05', '2025-09-08', '2025-09-09'],
            [],
        ]);

        sort($effective);
        $this->assertSame([
            '2025-09-02', '2025-09-03', '2025-09-04', '2025-09-05',
            '2025-09-06', '2025-09-07', '2025-09-08', '2025-09-09',
        ], $effective);
    }

    /** @test */
    public function it_bridges_a_midweek_holiday_and_absorbs_it_when_run_exceeds_seven_days()
    {
        // Mon 09-01, Tue 09-02 LWOP, Wed 09-03 declared holiday (bridges), Thu 09-04,
        // Fri 09-05 LWOP, weekend 09-06/07, Mon 09-08, Tue 09-09 LWOP.
        // Span = 9 calendar days (> 7) -> holiday AND weekend both absorbed.
        $effective = $this->invokePrivateMethod('calculateEffectiveLwopDates', [
            ['2025-09-01', '2025-09-02', '2025-09-04', '2025-09-05', '2025-09-08', '2025-09-09'],
            ['2025-09-03'],
        ]);

        sort($effective);
        $this->assertSame([
            '2025-09-01', '2025-09-02', '2025-09-03', '2025-09-04', '2025-09-05',
            '2025-09-06', '2025-09-07', '2025-09-08', '2025-09-09',
        ], $effective);
    }

    /** @test */
    public function it_treats_an_unauthorized_absence_day_as_breaking_run_continuity()
    {
        // Mon 09-01 LWOP, then Tue/Wed/Thu (09-02..09-04) are genuine unfiled absences
        // (not LWOP, not holiday, not weekend), then Fri 09-05 LWOP.
        // The gap is NOT bridged, so these are two separate 1-day runs -> no absorption.
        $effective = $this->invokePrivateMethod('calculateEffectiveLwopDates', [
            ['2025-09-01', '2025-09-05'],
            [],
        ]);

        sort($effective);
        $this->assertSame(['2025-09-01', '2025-09-05'], $effective);
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
    public function it_pads_the_query_window_and_returns_only_dates_within_the_requested_range()
    {
        $this->mockLwopQueries(array_map(fn($date) => ['date_applied' => $date, 'hours' => 8], [
            '2025-08-29', // Friday before the requested range - buffer-only, part of the same bridged run
            '2025-09-01', '2025-09-02', '2025-09-03', '2025-09-04', '2025-09-05',
            '2025-09-08', '2025-09-09',
            '2025-10-01', // Unrelated date well after the requested range - buffer-only
        ]));

        $effective = $this->invokePrivateMethod('getApprovedLwopDatesByRange', [1, '2025-09-01', '2025-09-09']);

        sort($effective);
        $this->assertSame([
            '2025-09-01', '2025-09-02', '2025-09-03', '2025-09-04',
            '2025-09-05', '2025-09-06', '2025-09-07', '2025-09-08', '2025-09-09',
        ], $effective);
    }

    /** @test */
    public function a_plotted_weekday_off_bridges_a_run_and_is_absorbed_when_it_exceeds_seven_days()
    {
        // CWW employee: Fri 09-05 plotted OFF (plus the weekend). LWOP Mon 09-01..Thu 09-04
        // and Mon 09-08..Tue 09-09 -> one run of 9 calendar days -> Fri/Sat/Sun absorbed.
        $effective = $this->invokePrivateMethod('calculateEffectiveLwopDates', [
            ['2025-09-01', '2025-09-02', '2025-09-03', '2025-09-04', '2025-09-08', '2025-09-09'],
            [],
            ['2025-09-05' => 'OFF'],
        ]);

        sort($effective);
        $this->assertSame([
            '2025-09-01', '2025-09-02', '2025-09-03', '2025-09-04', '2025-09-05',
            '2025-09-06', '2025-09-07', '2025-09-08', '2025-09-09',
        ], $effective);
    }

    /** @test */
    public function a_plotted_saturday_work_day_breaks_the_run()
    {
        // Shift worker scheduled to work Sat 09-06 (not LWOP, not filed): the run
        // Mon 09-01..Fri 09-05 and Mon 09-08..Tue 09-09 is interrupted -> nothing absorbed.
        $effective = $this->invokePrivateMethod('calculateEffectiveLwopDates', [
            ['2025-09-01', '2025-09-02', '2025-09-03', '2025-09-04', '2025-09-05', '2025-09-08', '2025-09-09'],
            [],
            ['2025-09-06' => 'D8'],
        ]);

        sort($effective);
        $this->assertSame([
            '2025-09-01', '2025-09-02', '2025-09-03', '2025-09-04', '2025-09-05',
            '2025-09-08', '2025-09-09',
        ], $effective);
    }

    /** @test */
    public function a_half_day_lwop_counts_half_a_day_and_does_not_join_a_run()
    {
        // Whole-day LWOP Mon 09-01..Thu 09-04, half day (4 h) Fri 09-05, whole day Mon 09-08..Tue 09-09.
        // The half day interrupts the run: two runs of 4 and 2 days -> weekend not absorbed.
        $this->mockLwopQueries([
            ['date_applied' => '2025-09-01', 'hours' => 8],
            ['date_applied' => '2025-09-02', 'hours' => 8],
            ['date_applied' => '2025-09-03', 'hours' => 8],
            ['date_applied' => '2025-09-04', 'hours' => 8],
            ['date_applied' => '2025-09-05', 'hours' => 4],
            ['date_applied' => '2025-09-08', 'hours' => 8],
            ['date_applied' => '2025-09-09', 'hours' => 8],
        ]);

        $lwop = $this->invokePrivateMethod('getLwopDaysByRange', [1, '2025-09-01', '2025-09-30']);

        $this->assertSame(['2025-09-01', '2025-09-02', '2025-09-03', '2025-09-04', '2025-09-05', '2025-09-08', '2025-09-09'], array_keys($lwop));
        $this->assertSame(['days' => 0.5, 'hours' => 4], $lwop['2025-09-05']);
        $this->assertSame(6.5, array_sum(array_column($lwop, 'days')));
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
