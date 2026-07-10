<?php

namespace Tests\Unit;

use App\Services\DTRServices;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DtrServicesUnifiedComputationTest extends TestCase
{
    use DatabaseTransactions;

    private int $userId = 9502;

    protected function tearDown(): void
    {
        DB::table('hris.dtr_logs')->where('userid', $this->userId)->delete();
        DB::table('hris.schedules')->where('employee_id', $this->userId)->delete();
        DB::table('hris.schedule_master_lists')->where('code', 'UNITESTSC')->delete();
        parent::tearDown();
    }

    private function seedSchedule(string $date, string $timeIn, string $timeOut, ?string $breakOut, ?string $breakIn): void
    {
        DB::table('hris.schedule_master_lists')->where('code', 'UNITESTSC')->delete();
        $id = DB::table('hris.schedule_master_lists')->insertGetId([
            'code' => 'UNITESTSC',
            'label' => 'Unified Computation Test',
            'type' => 'FIXED',
            'time_in' => $timeIn,
            'time_in_latest' => $timeIn,
            'time_out' => $timeOut,
            'time_out_latest' => $timeOut,
            'break_out' => $breakOut,
            'break_in' => $breakIn,
        ]);
        DB::table('hris.schedules')->updateOrInsert(
            ['employee_id' => $this->userId, 'schedule_date' => $date],
            ['schedule_id' => $id, 'updated_by' => $this->userId, 'created_at' => now()]
        );
    }

    private function seedLog(string $dateTime, string $status): void
    {
        DB::table('hris.dtr_logs')->insert([
            'userid' => $this->userId,
            'status' => $status,
            'type' => 2,
            'created_at' => $dateTime,
            'updated_at' => $dateTime,
        ]);
    }

    /**
     * Points the test employee at the real, shared "OFF" schedule template
     * (schedule_master_lists.code = 'OFF') instead of inserting a new
     * template row - that table is a system-wide reusable list (e.g. one
     * "OFF" row referenced by every employee's rest days), so creating
     * another one risks colliding with production lookups. Only the
     * per-employee mapping row in hris.schedules is test-owned and gets
     * cleaned up in tearDown().
     */
    private function seedOffSchedule(string $date): void
    {
        $offScheduleId = DB::table('hris.schedule_master_lists')->where('code', 'OFF')->value('id');
        DB::table('hris.schedules')->updateOrInsert(
            ['employee_id' => $this->userId, 'schedule_date' => $date],
            ['schedule_id' => $offScheduleId, 'updated_by' => $this->userId, 'created_at' => now()]
        );
    }

    /** @test */
    public function cutoff_generation_now_subtracts_break_time_matching_printdtr(): void
    {
        // Same fixture as DtrPrintCharacterizationTest::plain_late_arrival_and_early_departure...
        // 08:00-17:00, 12:00-13:00 break, arrives 13:07 (spans the break), leaves 16:30.
        $this->seedSchedule('2027-05-05', '08:00:00', '17:00:00', '12:00:00', '13:00:00');
        $this->seedLog('2027-05-05 13:07:00', 'IN');
        $this->seedLog('2027-05-05 16:30:00', 'OUT');

        $result = app(DTRServices::class)->getTotalLateAbsent($this->userId, '2027-05-05', '2027-05-05');

        // Before this project: the old processLogs() had no break deduction
        // at all and would have returned late_minutes=307 (13:07-08:00, no
        // subtraction). Now it must match printDTR's 247.
        $this->assertEquals(247, $result['late_minutes']);
        $this->assertEquals(30, $result['undertime_minutes']);
    }

    /** @test */
    public function half_day_missing_am_matches_printdtr_instead_of_the_old_blanket_rule(): void
    {
        // Same fixture as DtrPrintCharacterizationTest::half_day_missing_am...
        $this->seedSchedule('2027-05-12', '08:00:00', '17:00:00', '12:00:00', '13:00:00');
        $this->seedLog('2027-05-12 13:10:00', 'IN');
        $this->seedLog('2027-05-12 17:00:00', 'OUT');

        $result = app(DTRServices::class)->getTotalLateAbsent($this->userId, '2027-05-12', '2027-05-12');

        // Before this project: the old blanket "checkout before 1PM" rule
        // didn't apply here (checkout is 17:00, after 1PM) - undertime alone
        // would have been 0 late + 0 undertime (no rule caught this case at
        // all under the old algorithm, since it never modeled half-day-
        // missing-AM). Now it must match printDTR's 250.
        $this->assertEquals(250, $result['late_minutes']);
    }

    /** @test */
    public function off_scheduled_day_is_not_counted_as_absent(): void
    {
        // Rest day (schedule code 'OFF'), no attendance logs at all - the
        // ordinary weekend/rest-day case. Regression for the cutoff summary
        // counting these as absences: mapSchedulesWithBreaks() used to drop
        // the 'code' field, so computeDay() could never detect $isOffSchedule,
        // and resolveExpectedWorkDates() never excluded OFF days either.
        $this->seedOffSchedule('2027-06-05');

        $result = app(DTRServices::class)->getTotalLateAbsent($this->userId, '2027-06-05', '2027-06-05');

        $this->assertEquals(0, $result['absences']);
        $this->assertEquals(0, $result['late_minutes']);
        $this->assertEquals(0, $result['undertime_minutes']);
    }

    /** @test */
    public function off_scheduled_day_with_attendance_is_not_charged_undertime(): void
    {
        // Employee actually comes in on a scheduled rest day. The OFF
        // template's time_in/time_out are both 00:00:00, so once 'code' is
        // read correctly, isOffSchedule short-circuits the schedule-window
        // matching entirely - otherwise it's misread as a 00:00-24:00 shift
        // and a real clock-in produces a huge bogus undertime charge (~1380
        // minutes in production) instead of 0.
        $this->seedOffSchedule('2027-06-06');
        $this->seedLog('2027-06-06 07:25:00', 'IN');
        $this->seedLog('2027-06-06 18:30:00', 'OUT');

        $result = app(DTRServices::class)->getTotalLateAbsent($this->userId, '2027-06-06', '2027-06-06');

        $this->assertEquals(0, $result['absences']);
        $this->assertEquals(0, $result['late_minutes']);
        $this->assertEquals(0, $result['undertime_minutes']);
    }
}
