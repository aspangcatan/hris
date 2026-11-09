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

    /** @test */
    public function a_plotted_buffer_day_outside_the_range_does_not_erase_the_ranges_absences(): void
    {
        // Only Jul 31 is plotted (the buffer day before an August range); August itself
        // is unplotted and has no logs. Mon Aug 2 - Fri Aug 6, 2027 must be absences.
        $schedules = collect(['2027-07-31' => $this->schedule('08:00:00', '17:00:00', '12:00:00', '13:00:00')]);

        $result = $this->service->computeRange(9501, '2027-08-01', '2027-08-07', $schedules, collect(), []);

        $absent = collect($result)->filter(fn($d) => $d['is_absent'])->keys()->all();
        $this->assertSame(['2027-08-02', '2027-08-03', '2027-08-04', '2027-08-05', '2027-08-06'], $absent);
    }

    /** @test */
    public function unplotted_weekdays_inside_a_partly_plotted_range_are_work_days(): void
    {
        // Mon-Tue plotted (worked), Wed-Fri forgotten by the scheduler, no logs.
        $schedules = collect([
            '2027-08-02' => $this->schedule('08:00:00', '17:00:00', '12:00:00', '13:00:00'),
            '2027-08-03' => $this->schedule('08:00:00', '17:00:00', '12:00:00', '13:00:00'),
        ]);
        $logs = collect([
            $this->log('2027-08-02 08:00:00', 'IN'), $this->log('2027-08-02 17:00:00', 'OUT'),
            $this->log('2027-08-03 08:00:00', 'IN'), $this->log('2027-08-03 17:00:00', 'OUT'),
        ]);

        $result = $this->service->computeRange(9501, '2027-08-02', '2027-08-08', $schedules, $logs, []);

        $absent = collect($result)->filter(fn($d) => $d['is_absent'])->keys()->all();
        $this->assertSame(['2027-08-04', '2027-08-05', '2027-08-06'], $absent);
    }

    /** @test */
    public function an_unplotted_weekday_with_a_full_day_of_logs_is_not_absent(): void
    {
        $logs = collect([$this->log('2027-08-02 08:00:00', 'IN'), $this->log('2027-08-02 17:00:00', 'OUT')]);

        $result = $this->service->computeRange(9501, '2027-08-02', '2027-08-02', collect(), $logs, []);

        $this->assertFalse($result['2027-08-02']['is_absent']);
    }

    /** @test */
    public function an_unplotted_weekday_with_only_a_time_in_is_still_absent(): void
    {
        $logs = collect([$this->log('2027-08-02 08:00:00', 'IN')]);

        $result = $this->service->computeRange(9501, '2027-08-02', '2027-08-02', collect(), $logs, []);

        $this->assertTrue($result['2027-08-02']['is_absent']);
    }

    /** @test */
    public function off_days_unplotted_weekends_and_holidays_are_never_absences(): void
    {
        $off = (object) array_merge((array) $this->schedule('00:00:00', '00:00:00'), ['code' => 'OFF']);
        $schedules = collect(['2027-08-06' => $off]); // Friday rest day (CWW)
        $exemptions = ['holidays' => ['2027-08-05' => ['type' => 'Regular', 'description' => 'Test Holiday']]];

        // Thu holiday, Fri OFF, Sat/Sun unplotted - all without logs.
        $result = $this->service->computeRange(9501, '2027-08-05', '2027-08-08', $schedules, collect(), $exemptions);

        $this->assertEmpty(collect($result)->filter(fn($d) => $d['is_absent'])->all());
    }

    /** 8S day (08:00-17:00, 12-13 break) with the given logs and pass slip entry. */
    private function computeSlipDay(array $logs, array $slipEntry): array
    {
        $day = '2027-05-10';
        $schedules = collect([$day => $this->schedule('08:00:00', '17:00:00', '12:00:00', '13:00:00')]);

        return $this->service->computeRange(9501, $day, $day, $schedules, collect($logs), [
            'passSlips' => [$day => $slipEntry],
        ])[$day];
    }

    private function personalSlip(string $out, string $in): array
    {
        return ['official' => false, 'personal' => [['time_out' => $out, 'time_in' => $in]]];
    }

    /** @test */
    public function personal_pass_slip_minutes_are_returned_separately_and_do_not_erase_lateness(): void
    {
        // 30 min late + a 14:00-15:00 personal slip.
        $result = $this->computeSlipDay([
            $this->log('2027-05-10 08:30:00', 'IN'),
            $this->log('2027-05-10 17:00:00', 'OUT'),
        ], $this->personalSlip('14:00:00', '15:00:00'));

        $this->assertEquals(30, $result['late_minutes'], 'A personal slip must not erase the day\'s lateness.');
        $this->assertEquals(0, $result['undertime_minutes'], 'Slip minutes are not salary undertime.');
        $this->assertEquals(60, $result['pass_slip_minutes']);
    }

    /** @test */
    public function personal_pass_slip_counts_only_inside_working_hours(): void
    {
        // The real slip on file: 16:49-19:50, but work ends at 17:00 -> 11 minutes.
        $result = $this->computeSlipDay([
            $this->log('2027-05-10 08:00:00', 'IN'),
            $this->log('2027-05-10 17:00:00', 'OUT'),
        ], $this->personalSlip('16:49:00', '19:50:00'));

        $this->assertEquals(11, $result['pass_slip_minutes']);
    }

    /** @test */
    public function personal_pass_slip_excludes_the_lunch_break(): void
    {
        $result = $this->computeSlipDay([
            $this->log('2027-05-10 08:00:00', 'IN'),
            $this->log('2027-05-10 17:00:00', 'OUT'),
        ], $this->personalSlip('11:30:00', '13:30:00'));

        $this->assertEquals(60, $result['pass_slip_minutes']);
    }

    /** @test */
    public function personal_pass_slip_is_not_double_counted_with_undertime(): void
    {
        // Slip 16:00-17:00 and the employee simply left at 16:00: that hour is
        // already undertime, so the slip adds nothing on top.
        $result = $this->computeSlipDay([
            $this->log('2027-05-10 08:00:00', 'IN'),
            $this->log('2027-05-10 16:00:00', 'OUT'),
        ], $this->personalSlip('16:00:00', '17:00:00'));

        $this->assertEquals(60, $result['undertime_minutes']);
        $this->assertEquals(0, $result['pass_slip_minutes']);
    }

    /** @test */
    public function official_pass_slip_still_excuses_the_whole_day(): void
    {
        $result = $this->computeSlipDay([
            $this->log('2027-05-10 09:00:00', 'IN'),
            $this->log('2027-05-10 15:00:00', 'OUT'),
        ], ['official' => true, 'personal' => []]);

        $this->assertEquals(0, $result['late_minutes']);
        $this->assertEquals(0, $result['undertime_minutes']);
        $this->assertEquals(0, $result['pass_slip_minutes']);
        $this->assertSame('pass_slip', $result['exemption_reason']);
    }

    /**
     * Partial-day leave on an 8S day (08:00-17:00, 12-13 break = 480 work minutes).
     * Returns the computed day for a 4-hour leave plus the given logs.
     */
    private function computePartialLeaveDay(array $logs, $leaveEntry = ['hours' => 4], array $extraExemptions = []): array
    {
        $day = '2027-05-03';
        $schedules = collect([$day => $this->schedule('08:00:00', '17:00:00', '12:00:00', '13:00:00')]);
        $exemptions = array_merge([
            'leaves' => [$day => $leaveEntry],
            'absenceExemptDates' => [$day => true],
        ], $extraExemptions);

        return $this->service->computeRange(9501, $day, $day, $schedules, collect($logs), $exemptions)[$day];
    }

    /** @test */
    public function four_hour_leave_plus_four_worked_hours_is_a_complete_day(): void
    {
        // Morning worked, afternoon on leave.
        $am = $this->computePartialLeaveDay([
            $this->log('2027-05-03 08:00:00', 'IN'),
            $this->log('2027-05-03 12:00:00', 'OUT'),
        ]);
        $this->assertEquals(0, $am['late_minutes']);
        $this->assertEquals(0, $am['undertime_minutes']);
        $this->assertFalse($am['is_absent']);
        $this->assertSame('partial_leave', $am['exemption_reason']);

        // Morning on leave, afternoon worked.
        $pm = $this->computePartialLeaveDay([
            $this->log('2027-05-03 13:00:00', 'IN'),
            $this->log('2027-05-03 17:00:00', 'OUT'),
        ]);
        $this->assertEquals(0, $pm['late_minutes']);
        $this->assertEquals(0, $pm['undertime_minutes']);
    }

    /** @test */
    public function partial_leave_shortfall_becomes_undertime(): void
    {
        // 4h leave + 13:30-17:00 (3.5h worked) = 30 minutes short.
        $result = $this->computePartialLeaveDay([
            $this->log('2027-05-03 13:30:00', 'IN'),
            $this->log('2027-05-03 17:00:00', 'OUT'),
        ]);

        $this->assertEquals(0, $result['late_minutes']);
        $this->assertEquals(30, $result['undertime_minutes']);
    }

    /** @test */
    public function partial_leave_with_no_logs_charges_the_uncovered_hours_as_undertime_not_absence(): void
    {
        $result = $this->computePartialLeaveDay([]);

        $this->assertEquals(240, $result['undertime_minutes']);
        $this->assertFalse($result['is_absent']);
    }

    /** @test */
    public function work_outside_the_schedule_window_does_not_fill_a_partial_leave(): void
    {
        // 06:00-10:00: only 08:00-10:00 is inside the schedule -> 2h worked, 2h short.
        $result = $this->computePartialLeaveDay([
            $this->log('2027-05-03 06:00:00', 'IN'),
            $this->log('2027-05-03 10:00:00', 'OUT'),
        ]);

        $this->assertEquals(120, $result['undertime_minutes']);
    }

    /** @test */
    public function day_based_half_day_covers_half_of_the_schedule(): void
    {
        // 8S = 480 work minutes; a day-based half day covers 240 of them.
        $worked = $this->computePartialLeaveDay([
            $this->log('2027-05-03 08:00:00', 'IN'),
            $this->log('2027-05-03 12:00:00', 'OUT'),
        ], ['fraction' => 0.5]);
        $this->assertEquals(0, $worked['undertime_minutes']);

        $noLogs = $this->computePartialLeaveDay([], ['fraction' => 0.5]);
        $this->assertEquals(240, $noLogs['undertime_minutes']);
        $this->assertFalse($noLogs['is_absent']);
    }

    /** @test */
    public function day_based_half_day_on_a_ten_hour_day_covers_five_hours(): void
    {
        // CWW-like 07:00-18:00 with 12-13 break = 600 work minutes; half day = 300 minutes,
        // so 5 hours worked (07:00-12:00) completes the day.
        $day = '2027-05-04';
        $schedules = collect([$day => $this->schedule('07:00:00', '18:00:00', '12:00:00', '13:00:00')]);
        $result = $this->service->computeRange(9501, $day, $day, $schedules, collect([
            $this->log("$day 07:00:00", 'IN'),
            $this->log("$day 12:00:00", 'OUT'),
        ]), ['leaves' => [$day => ['fraction' => 0.5]], 'absenceExemptDates' => [$day => true]])[$day];

        $this->assertEquals(0, $result['undertime_minutes']);
    }

    /** @test */
    public function full_day_leave_lwop_and_holiday_keep_the_whole_day_exemption(): void
    {
        $fullDay = $this->computePartialLeaveDay([], ['hours' => 8]);
        $this->assertEquals(0, $fullDay['undertime_minutes']);
        $this->assertSame('leave', $fullDay['exemption_reason']);

        $lwop = $this->computePartialLeaveDay([], true);
        $this->assertEquals(0, $lwop['undertime_minutes']);

        $holiday = $this->computePartialLeaveDay([], ['hours' => 4], [
            'holidays' => ['2027-05-03' => ['type' => 'Regular', 'description' => 'Test Holiday']],
        ]);
        $this->assertEquals(0, $holiday['undertime_minutes']);
    }
}
