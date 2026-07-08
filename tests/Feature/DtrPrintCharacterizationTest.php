<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class DtrPrintCharacterizationTest extends TestCase
{
    use DatabaseTransactions;

    private int $userId = 9501;
    private int $scheduleMasterId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        DB::table('hris.dtr_logs')->where('userid', $this->userId)->delete();
        DB::table('hris.schedules')->where('employee_id', $this->userId)->delete();
        DB::table('hris.schedule_master_lists')->where('code', 'CTESTSCH')->delete();
        DB::table('tdh_user.users')->where('id', $this->userId)->delete();
        DB::table('hris.calendar_holidays')->where('description', 'Characterization Test Holiday')->delete();
        DB::table('hris.leave_date_applied')->whereIn(
            'leave_application_id',
            DB::table('hris.leave_applications')->where('user_id', $this->userId)->pluck('id')
        )->delete();
        DB::table('hris.leave_applications')->where('user_id', $this->userId)->delete();
        DB::table('hris.leave_types')->where('description', 'Characterization Test Leave Type')->delete();
    }

    private function actAsAdmin(): void
    {
        Auth::shouldReceive('id')->andReturn($this->userId);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'admin']);
    }

    private function seedUser(): void
    {
        DB::table('tdh_user.users')->where('id', $this->userId)->delete();
        DB::table('tdh_user.users')->insert([
            'id' => $this->userId,
            'fname' => 'Charz',
            'lname' => 'Tester',
            'username' => 'chartester' . $this->userId,
            'password' => 'x',
            'division' => 2,
            'section' => 21,
            'designation' => 52,
        ]);
    }

    /**
     * @param string $timeIn e.g. '08:00:00'
     * @param string $timeOut e.g. '17:00:00'
     * @param string|null $breakOut e.g. '12:00:00'
     * @param string|null $breakIn e.g. '13:00:00'
     * @param string $type 'FIXED' or 'GLIDING'
     */
    private function seedSchedule(string $date, string $timeIn, string $timeOut, ?string $breakOut = null, ?string $breakIn = null, string $type = 'FIXED', ?string $timeInLatest = null, ?string $timeOutLatest = null): void
    {
        DB::table('hris.schedule_master_lists')->where('code', 'CTESTSCH')->delete();
        $this->scheduleMasterId = DB::table('hris.schedule_master_lists')->insertGetId([
            'code' => 'CTESTSCH',
            'label' => 'Characterization Test Schedule',
            'type' => $type,
            'time_in' => $timeIn,
            'time_in_latest' => $timeInLatest ?? $timeIn,
            'time_out' => $timeOut,
            'time_out_latest' => $timeOutLatest ?? $timeOut,
            'break_out' => $breakOut,
            'break_in' => $breakIn,
        ]);

        DB::table('hris.schedules')->updateOrInsert(
            ['employee_id' => $this->userId, 'schedule_date' => $date],
            ['schedule_id' => $this->scheduleMasterId, 'updated_by' => $this->userId, 'created_at' => now()]
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
     * Calls printDTR for the given month/year and captures the $dtrData array
     * that would have been handed to the PDF view, without actually rendering
     * a PDF (wkhtmltopdf is slow and we don't need the binary output).
     *
     * @return array The 'employees' data array (one entry per requested user)
     */
    private function capturePrintDtrData(int $month, int $year): array
    {
        $captured = null;

        View::composer('pdf.old_dtr', function ($view) use (&$captured) {
            $captured = $view->getData()['employees'];
            throw new \RuntimeException('__captured__');
        });

        try {
            $this->withoutMiddleware()->get("/print_dtr?userid={$this->userId}&month={$month}&year={$year}&cutoff=full&enable_automation=yes");
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== '__captured__') {
                throw $e;
            }
        } finally {
            View::composer('pdf.old_dtr', function () {});
        }

        $this->assertNotNull($captured, 'printDTR never reached PDF rendering - check the route/permissions.');

        return $captured;
    }

    private function dayResult(array $employeeData, int $day): array
    {
        $match = collect($employeeData['dtrs'])->firstWhere('day', $day);
        $this->assertNotNull($match, "Day {$day} not found in printDTR output.");
        return $match;
    }

    /** @test */
    public function plain_late_arrival_and_early_departure_deduct_break_time_correctly(): void
    {
        $this->actAsAdmin();
        $this->seedUser();
        // 08:00-17:00 schedule, 12:00-13:00 break. Arrives 13:07 (late arrival
        // spans the break), leaves 16:30 (undertime, no break overlap).
        $this->seedSchedule('2027-04-05', '08:00:00', '17:00:00', '12:00:00', '13:00:00');
        $this->seedLog('2027-04-05 13:07:00', 'IN');
        $this->seedLog('2027-04-05 16:30:00', 'OUT');

        $data = $this->capturePrintDtrData(4, 2027);
        $day = $this->dayResult($data[0], 5);

        // Raw late diff is 13:07 - 08:00 = 307 min; minus the 60-min break
        // overlap = 247 min (4h 7m), matching the documented dtr-computation-rules example.
        $this->assertEquals(247, $day['late_minutes']);
        // Undertime: 17:00 - 16:30 = 30 min, no break overlap in that window.
        $this->assertEquals(30, (int) $day['undertime_hours'] * 60 + (int) $day['undertime_minutes'] - 247);
    }

    /** @test */
    public function holiday_with_actual_timelogs_still_computes_penalty(): void
    {
        $this->actAsAdmin();
        $this->seedUser();
        DB::table('hris.calendar_holidays')->updateOrInsert(
            ['date' => '2027-04-09'],
            ['type' => 'Regular', 'description' => 'Characterization Test Holiday']
        );
        $this->seedSchedule('2027-04-09', '08:00:00', '17:00:00', '12:00:00', '13:00:00');
        $this->seedLog('2027-04-09 08:30:00', 'IN');
        $this->seedLog('2027-04-09 17:00:00', 'OUT');

        $data = $this->capturePrintDtrData(4, 2027);
        $day = $this->dayResult($data[0], 9);

        // 30 min late despite the holiday - holidays with logs still get penalized.
        $this->assertEquals(30, $day['late_minutes']);
    }

    /** @test */
    public function half_day_missing_am_charges_flat_half_plus_precise_pm_math(): void
    {
        $this->actAsAdmin();
        $this->seedUser();
        // Full-day 08:00-17:00 schedule (540 rendered minutes), break 12-1.
        // No AM logs at all; arrives for PM only at 13:10, leaves on time.
        $this->seedSchedule('2027-04-12', '08:00:00', '17:00:00', '12:00:00', '13:00:00');
        $this->seedLog('2027-04-12 13:10:00', 'IN');
        $this->seedLog('2027-04-12 17:00:00', 'OUT');

        $data = $this->capturePrintDtrData(4, 2027);
        $day = $this->dayResult($data[0], 12);

        // Half of rendered_minutes (480 non-break minutes / 2 = 240) added to
        // late, plus 10 min precise lateness against break_in (13:00) = 250.
        $this->assertEquals(250, $day['late_minutes']);
    }

    /** @test */
    public function gliding_schedule_shifts_required_out_by_the_late_arrival_offset(): void
    {
        $this->actAsAdmin();
        $this->seedUser();
        // Gliding 07:00(-08:00 latest)/16:00(-17:00 latest), no break.
        $this->seedSchedule('2027-04-14', '07:00:00', '16:00:00', null, null, 'GLIDING', '08:00:00', '17:00:00');
        // Arrives 07:30 (within gliding window, 30 min after core time_in) - required out shifts to 16:30.
        $this->seedLog('2027-04-14 07:30:00', 'IN');
        $this->seedLog('2027-04-14 16:15:00', 'OUT');

        $data = $this->capturePrintDtrData(4, 2027);
        $day = $this->dayResult($data[0], 14);

        // No lateness (07:30 is within the gliding IN window). Required out
        // shifted to 16:30 (16:00 + 30min offset); left at 16:15 = 15 min undertime.
        $this->assertEquals(0, $day['late_minutes']);
        $this->assertEquals(15, (int) $day['undertime_hours'] * 60 + (int) $day['undertime_minutes']);
    }

    /** @test */
    public function overnight_schedule_matches_out_log_on_the_following_calendar_day(): void
    {
        $this->actAsAdmin();
        $this->seedUser();
        // Overnight 22:00-06:00 (out is on the next calendar day).
        $this->seedSchedule('2027-04-16', '22:00:00', '06:00:00');
        $this->seedLog('2027-04-16 22:00:00', 'IN');
        $this->seedLog('2027-04-17 06:00:00', 'OUT');

        $data = $this->capturePrintDtrData(4, 2027);
        $day = $this->dayResult($data[0], 16);

        $this->assertEquals(0, $day['late_minutes']);
        $this->assertEquals('', $day['undertime_hours']);
        $this->assertEquals('', $day['undertime_minutes']);
    }

    /** @test */
    public function approved_leave_waives_late_undertime_computation_entirely(): void
    {
        $this->actAsAdmin();
        $this->seedUser();
        $this->seedSchedule('2027-04-19', '08:00:00', '17:00:00', '12:00:00', '13:00:00');
        // Arrives very late - would normally be a large penalty.
        $this->seedLog('2027-04-19 11:00:00', 'IN');
        $this->seedLog('2027-04-19 17:00:00', 'OUT');

        // The live DB's hris.leave_types has no pre-seeded 'VL' row in this
        // environment - self-seed a dedicated test-only leave type (any code
        // works for the exemption check; DTRController only requires the
        // leave_type_id join to resolve, not a specific code) rather than
        // assuming production reference data exists.
        $leaveTypeId = DB::table('hris.leave_types')->where('code', 'VL')->value('id');
        if (!$leaveTypeId) {
            $leaveTypeId = DB::table('hris.leave_types')->insertGetId([
                'description' => 'Characterization Test Leave Type',
                'code' => 'VL',
                'monthly_earned' => 0,
                'status' => 'active',
            ]);
        }
        $this->assertNotNull($leaveTypeId, 'Seed a VL leave type before running this test.');

        $applicationId = DB::table('hris.leave_applications')->insertGetId([
            'user_id' => $this->userId,
            'status' => 'Approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('hris.leave_date_applied')->insert([
            'leave_application_id' => $applicationId,
            'leave_type_id' => $leaveTypeId,
            'date_applied' => '2027-04-19',
            'hours' => 8,
        ]);

        $data = $this->capturePrintDtrData(4, 2027);
        $day = $this->dayResult($data[0], 19);

        // Waived entirely despite the late arrival - late_minutes stays 0.
        $this->assertEquals(0, $day['late_minutes']);
    }
}
