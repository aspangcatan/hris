<?php

namespace Tests\Unit;

use App\Services\DtrDayComputationService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class DtrDayComputationServiceTest extends TestCase
{
    private DtrDayComputationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DtrDayComputationService();
    }

    private function schedule(string $timeIn, string $timeOut, ?string $breakOut = null, ?string $breakIn = null, string $type = 'FIXED', ?string $timeInLatest = null, ?string $timeOutLatest = null): object
    {
        return (object) [
            'time_in' => $timeIn,
            'time_in_latest' => $timeInLatest ?? $timeIn,
            'time_out' => $timeOut,
            'time_out_latest' => $timeOutLatest ?? $timeOut,
            'break_out' => $breakOut,
            'break_in' => $breakIn,
            'type' => $type,
            'code' => 'TESTSCHED',
        ];
    }

    private function log(string $dateTime, string $status): object
    {
        return (object) ['created_at' => $dateTime, 'status' => $status];
    }

    /** @test */
    public function break_time_is_subtracted_from_late_minutes(): void
    {
        $schedules = collect(['2027-04-05' => $this->schedule('08:00:00', '17:00:00', '12:00:00', '13:00:00')]);
        $logs = collect([
            $this->log('2027-04-05 13:07:00', 'IN'),
            $this->log('2027-04-05 16:30:00', 'OUT'),
        ]);

        $result = $this->service->computeRange(9501, '2027-04-05', '2027-04-05', $schedules, $logs, []);

        $this->assertEquals(247, $result['2027-04-05']['late_minutes']);
        $this->assertEquals(30, $result['2027-04-05']['undertime_minutes']);
    }

    /** @test */
    public function holiday_with_logs_still_computes_penalty(): void
    {
        $schedules = collect(['2027-04-09' => $this->schedule('08:00:00', '17:00:00', '12:00:00', '13:00:00')]);
        $logs = collect([
            $this->log('2027-04-09 08:30:00', 'IN'),
            $this->log('2027-04-09 17:00:00', 'OUT'),
        ]);
        $exemptions = ['holidays' => ['2027-04-09' => ['type' => 'Regular', 'description' => 'Test Holiday']]];

        $result = $this->service->computeRange(9501, '2027-04-09', '2027-04-09', $schedules, $logs, $exemptions);

        $this->assertEquals(30, $result['2027-04-09']['late_minutes']);
        $this->assertTrue($result['2027-04-09']['is_holiday']);
    }

    /** @test */
    public function leave_exemption_waives_computation_entirely(): void
    {
        $schedules = collect(['2027-04-19' => $this->schedule('08:00:00', '17:00:00', '12:00:00', '13:00:00')]);
        $logs = collect([
            $this->log('2027-04-19 11:00:00', 'IN'),
            $this->log('2027-04-19 17:00:00', 'OUT'),
        ]);
        $exemptions = ['leaves' => ['2027-04-19' => true]];

        $result = $this->service->computeRange(9501, '2027-04-19', '2027-04-19', $schedules, $logs, $exemptions);

        $this->assertEquals(0, $result['2027-04-19']['late_minutes']);
        $this->assertEquals('leave', $result['2027-04-19']['exemption_reason']);
    }

    /** @test */
    public function fixed_arrival_at_or_before_time_in_requires_only_time_out(): void
    {
        $schedules = collect(['2027-05-03' => $this->schedule('08:00:00', '17:00:00', null, null, 'FIXED', '08:30:00', '17:30:00')]);
        $logs = collect([
            $this->log('2027-05-03 08:00:00', 'IN'),
            $this->log('2027-05-03 17:00:00', 'OUT'),
        ]);

        $result = $this->service->computeRange(9501, '2027-05-03', '2027-05-03', $schedules, $logs, []);

        $this->assertEquals(0, $result['2027-05-03']['late_minutes']);
        $this->assertEquals(0, $result['2027-05-03']['undertime_minutes']);
    }

    /** @test */
    public function fixed_arrival_past_time_in_but_within_grace_window_jumps_required_out_to_time_out_latest(): void
    {
        $schedules = collect(['2027-05-04' => $this->schedule('08:00:00', '17:00:00', null, null, 'FIXED', '08:30:00', '17:30:00')]);
        $logs = collect([
            $this->log('2027-05-04 08:10:00', 'IN'),
            $this->log('2027-05-04 17:00:00', 'OUT'),
        ]);

        $result = $this->service->computeRange(9501, '2027-05-04', '2027-05-04', $schedules, $logs, []);

        // Still within the time_in..time_in_latest grace window, so not late -
        // but required-out already jumped to time_out_latest (17:30), so
        // leaving at the "old" required time of 17:00 now shows undertime.
        $this->assertEquals(0, $result['2027-05-04']['late_minutes']);
        $this->assertEquals(30, $result['2027-05-04']['undertime_minutes']);
    }

    /** @test */
    public function fixed_arrival_past_time_in_latest_is_late_and_still_requires_time_out_latest(): void
    {
        $schedules = collect(['2027-05-05' => $this->schedule('08:00:00', '17:00:00', null, null, 'FIXED', '08:30:00', '17:30:00')]);
        $logs = collect([
            $this->log('2027-05-05 08:45:00', 'IN'),
            $this->log('2027-05-05 17:30:00', 'OUT'),
        ]);

        $result = $this->service->computeRange(9501, '2027-05-05', '2027-05-05', $schedules, $logs, []);

        $this->assertEquals(15, $result['2027-05-05']['late_minutes']);
        $this->assertEquals(0, $result['2027-05-05']['undertime_minutes']);
    }

    /** @test */
    public function fixed_schedule_with_matching_min_and_max_bounds_is_unaffected_by_the_bracket_logic(): void
    {
        // Regression check: when time_in == time_in_latest and
        // time_out == time_out_latest (the shape of every real FIXED
        // schedule in this app today except one unrelated anomaly),
        // the bracket logic must produce identical results to the old
        // flat "required-out is always time_out" behavior.
        $schedules = collect(['2027-05-06' => $this->schedule('08:00:00', '17:00:00')]);
        $logs = collect([
            $this->log('2027-05-06 08:07:00', 'IN'),
            $this->log('2027-05-06 16:30:00', 'OUT'),
        ]);

        $result = $this->service->computeRange(9501, '2027-05-06', '2027-05-06', $schedules, $logs, []);

        $this->assertEquals(7, $result['2027-05-06']['late_minutes']);
        $this->assertEquals(30, $result['2027-05-06']['undertime_minutes']);
    }

    /** @test */
    public function afternoon_shift_full_day_is_not_misclassified_as_half_day_missing(): void
    {
        // 2:00 PM - 10:00 PM schedule with a 30-min dinner break. Every
        // punch on a shift like this falls after 12:00 noon, so the old
        // wall-clock-noon AM/PM bucketing saw "no AM attendance" on every
        // single fully-worked day and misfired the half-day-missing-half
        // flat ~half-shift penalty. Employee here worked the whole shift
        // (in before the break, out after the break) - just 7 min late,
        // 10 min undertime, no half-day penalty should apply at all.
        $schedules = collect(['2027-06-01' => $this->schedule('14:00:00', '22:00:00', '18:00:00', '18:30:00')]);
        $logs = collect([
            $this->log('2027-06-01 14:07:00', 'IN'),
            $this->log('2027-06-01 21:50:00', 'OUT'),
        ]);

        $result = $this->service->computeRange(9501, '2027-06-01', '2027-06-01', $schedules, $logs, []);

        $this->assertEquals(7, $result['2027-06-01']['late_minutes']);
        $this->assertEquals(10, $result['2027-06-01']['undertime_minutes']);
    }

    /** @test */
    public function halfday_missing_check_disabled_via_config_falls_back_to_plain_full_day_math(): void
    {
        // Asymmetric full-day schedule (08:00-18:00, 30-min break at
        // 12:00-12:30 - AM segment 240min, PM segment 330min) with only
        // the PM half worked. With the flag on, this legitimately triggers
        // isHalfDayMissingHalf: 15min precise late (vs break_in, no grace)
        // + a flat half-shift charge of round(570/2)=285 = 300 total late.
        // With the flag off, no half-day special-casing applies at all -
        // late is just the plain arrival-vs-time_in boundary calc (285),
        // minus the break it fully skipped over (30) = 255.
        config(['hris.dtr_halfday_missing_check_enabled' => false]);

        $schedules = collect(['2027-06-02' => $this->schedule('08:00:00', '18:00:00', '12:00:00', '12:30:00')]);
        $logs = collect([
            $this->log('2027-06-02 12:45:00', 'IN'),
            $this->log('2027-06-02 18:00:00', 'OUT'),
        ]);

        $result = $this->service->computeRange(9501, '2027-06-02', '2027-06-02', $schedules, $logs, []);

        $this->assertEquals(255, $result['2027-06-02']['late_minutes']);
        $this->assertEquals(0, $result['2027-06-02']['undertime_minutes']);
    }
}
