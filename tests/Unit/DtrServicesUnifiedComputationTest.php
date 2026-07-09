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
}
