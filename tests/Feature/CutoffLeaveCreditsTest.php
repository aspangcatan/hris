<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CutoffLeaveCreditsTest extends TestCase
{
    use DatabaseTransactions;

    private int $userId = 9601;

    private ?int $masterId = null;

    /** @var \Illuminate\Support\Collection|null */
    private $originalPoints;

    /** @var \Illuminate\Support\Collection|null */
    private $originalDeductions;

    protected function setUp(): void
    {
        parent::setUp();
        // APP_URL contains a subfolder (/hris) which breaks test HTTP client path matching.
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);

        // monthly_earned_points/minute_conversions are seeded with real CSC
        // data (present/minute are unique columns) - overwrite the specific
        // rows this test needs and restore them in tearDown, so assertions
        // don't depend on production lookup-table values.
        $this->originalPoints = DB::table('monthly_earned_points')->whereIn('lwop', [5])->pluck('points', 'lwop');
        DB::table('monthly_earned_points')->updateOrInsert(['lwop' => 5], ['points' => 1.7]);

        $this->originalDeductions = DB::table('minute_conversions')->whereIn('minute', [60, 15])->pluck('points_deduction', 'minute');
        DB::table('minute_conversions')->updateOrInsert(['minute' => 60], ['points_deduction' => 0.1]);
        DB::table('minute_conversions')->updateOrInsert(['minute' => 15], ['points_deduction' => 0.025]);
    }

    protected function tearDown(): void
    {
        // These tables are MyISAM, so DatabaseTransactions rollback is a no-op — clean up explicitly.
        if ($this->masterId) {
            DB::table('hris.cutoff_summary_days')->where('cutoff_id', $this->masterId)->delete();
            DB::table('hris.cutoff_summaries')->where('cutoff_id', $this->masterId)->delete();
            DB::table('hris.cutoff_summaries_master')->where('id', $this->masterId)->delete();
        }
        DB::table('hris.leave_credits')->where('user_id', $this->userId)->delete();
        DB::table('credit_earned_logs')->where('user_id', $this->userId)->delete();
        $appIds = DB::table('hris.leave_applications')->where('user_id', $this->userId)->pluck('id');
        DB::table('hris.leave_date_applied')->whereIn('leave_application_id', $appIds)->delete();
        DB::table('hris.leave_applications')->whereIn('id', $appIds)->delete();
        DB::table('hris.schedules')->where('employee_id', $this->userId)->delete();
        DB::table('hris.dtr_logs')->where('userid', $this->userId)->delete();
        DB::table('hris.pass_slips')->where('user_id', $this->userId)->delete();

        foreach ($this->originalPoints as $present => $points) {
            DB::table('monthly_earned_points')->where('lwop', $present)->update(['points' => $points]);
        }
        foreach ($this->originalDeductions as $minute => $deduction) {
            DB::table('minute_conversions')->where('minute', $minute)->update(['points_deduction' => $deduction]);
        }

        parent::tearDown();
    }

    private function actAsAdmin(): void
    {
        Auth::shouldReceive('id')->andReturn($this->userId);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn(null);
        session(['access_rights' => 'admin']);
    }

    private function seedCutoff(string $start, string $end, string $title, int $daysPresent, int $totalWorkingDays, int $lateMinutes = 0, int $undertimeMinutes = 0): int
    {
        DB::table('hris.cutoff_summaries_master')
            ->where('period_start', $start)->where('period_end', $end)->where('title', $title)
            ->delete();

        $masterId = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title'        => $title,
            'period_start' => $start,
            'period_end'   => $end,
            'generated_by' => $this->userId,
            'generated_at' => now(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        $this->masterId = $masterId;

        DB::table('hris.cutoff_summaries')->updateOrInsert(
            ['employee_id' => $this->userId, 'cutoff_id' => $masterId],
            [
                'period_start'       => $start,
                'period_end'         => $end,
                'total_working_days' => $totalWorkingDays,
                'days_present'       => $daysPresent,
                'days_absent'        => 0,
                'late_minutes'       => $lateMinutes,
                'undertime_minutes'  => $undertimeMinutes,
                'generated_by'       => $this->userId,
                'generated_at'       => now(),
                'created_at'         => now(),
                'updated_at'         => now(),
            ]
        );

        return $masterId;
    }

    /** @test */
    public function preview_and_apply_compute_net_points_scaled_to_the_cutoffs_own_working_days()
    {
        $this->actAsAdmin();

        // 18/22 present -> lookup key round((18/22)*30) = 25, not raw present (18).
        $masterId = $this->seedCutoff('2027-06-01', '2027-06-30', 'June Test Cutoff', 18, 22, 75, 0);

        // 75 minutes = 1 hour (0.1) + 15 minutes (0.025) = 0.125 deduction.
        $expectedPoints = 1.7;
        $expectedDeduction = 0.125;
        $expectedNet = 1.575;

        $preview = $this->withoutMiddleware()
            ->getJson('/cutoff-leave-credits-preview?' . http_build_query(['cutoff_id' => $masterId]));
        $preview->assertOk();

        $row = collect($preview->json('data'))->firstWhere('employee_id', $this->userId);
        $this->assertNotNull($row, 'Expected a preview row for the test employee.');
        $this->assertEquals(18, $row['total_present']);
        $this->assertEqualsWithDelta((float) $expectedPoints, (float) $row['earned_points'], 0.001);
        $this->assertEqualsWithDelta((float) $expectedDeduction, (float) $row['deducted_points'], 0.001);
        $this->assertEqualsWithDelta($expectedNet, (float) $row['net_points'], 0.001);

        $apply = $this->withoutMiddleware()->postJson('/cutoff-leave-credits-apply', [
            'cutoff_id' => $masterId,
            'employees' => [$this->userId],
        ]);
        $apply->assertOk();

        // Fresh synthetic employee has no prior leave_credits row, so based_credit
        // should equal the applied net figure exactly (not the gross figure).
        $vlCredit = DB::table('hris.leave_credits')
            ->where('user_id', $this->userId)->where('leave_id', 2)->value('based_credit');
        $this->assertEqualsWithDelta($expectedNet, (float) $vlCredit, 0.001);
        $this->assertNotEquals($expectedPoints, round((float) $vlCredit, 3), 'Applied credit must be net, not gross.');
    }

    /** @test */
    public function reapplying_the_same_cutoff_does_not_double_credit()
    {
        $this->actAsAdmin();

        $masterId = $this->seedCutoff('2027-07-01', '2027-07-31', 'July Test Cutoff', 20, 22);
        $payload = ['cutoff_id' => $masterId, 'employees' => [$this->userId]];

        $this->withoutMiddleware()->postJson('/cutoff-leave-credits-apply', $payload)->assertOk();
        $firstRunCredit = DB::table('hris.leave_credits')
            ->where('user_id', $this->userId)->where('leave_id', 2)->value('based_credit');

        // Second Save: nothing left to credit -> refused with a clear message.
        $second = $this->withoutMiddleware()->postJson('/cutoff-leave-credits-apply', $payload);
        $second->assertStatus(422);
        $this->assertStringContainsString('already credited for July 2027', $second->json('message'));
        $secondRunCredit = DB::table('hris.leave_credits')
            ->where('user_id', $this->userId)->where('leave_id', 2)->value('based_credit');

        $this->assertEqualsWithDelta((float) $firstRunCredit, (float) $secondRunCredit, 0.001);
    }

    /** @test */
    public function reapplying_an_older_month_after_a_newer_one_does_not_double_credit()
    {
        $this->actAsAdmin();

        $june = $this->seedCutoff('2027-06-01', '2027-06-30', 'June Order Test', 22, 22);
        $juneMaster = $this->masterId;
        $july = $this->seedCutoff('2027-07-01', '2027-07-31', 'July Order Test', 22, 22);

        try {
            $apply = fn($id) => $this->withoutMiddleware()->postJson('/cutoff-leave-credits-apply', [
                'cutoff_id' => $id, 'employees' => [$this->userId],
            ]);

            $first = $apply($june);
            $first->assertOk();
            $this->assertStringContainsString('posted to 1 employee', $first->json('message'));
            $apply($july)->assertOk();
            $afterBoth = (float) DB::table('hris.leave_credits')
                ->where('user_id', $this->userId)->where('leave_id', 2)->value('based_credit');

            // Used to credit June a second time (last_updated_month was July).
            $apply($june)->assertStatus(422);
            $afterRetry = (float) DB::table('hris.leave_credits')
                ->where('user_id', $this->userId)->where('leave_id', 2)->value('based_credit');

            $this->assertEqualsWithDelta($afterBoth, $afterRetry, 0.001);
            $this->assertSame(1, DB::table('credit_earned_logs')
                ->where('user_id', $this->userId)->where('year', 2027)->where('month', 6)->where('leave_type_id', 2)->count());
        } finally {
            DB::table('hris.cutoff_summaries')->where('cutoff_id', $juneMaster)->delete();
            DB::table('hris.cutoff_summaries_master')->where('id', $juneMaster)->delete();
        }
    }

    /** @test */
    public function a_cutoff_with_no_attendance_cannot_be_credited()
    {
        $this->actAsAdmin();

        $masterId = $this->seedCutoff('2027-08-01', '2027-08-31', 'Empty Attendance Test', 0, 22);

        $preview = $this->withoutMiddleware()
            ->getJson('/cutoff-leave-credits-preview?' . http_build_query(['cutoff_id' => $masterId]));
        $preview->assertOk();
        $this->assertSame(1, $preview->json('to_credit'));
        $this->assertStringContainsString('has any attendance', (string) $preview->json('message'));

        $apply = $this->withoutMiddleware()->postJson('/cutoff-leave-credits-apply', [
            'cutoff_id' => $masterId, 'employees' => [$this->userId],
        ]);
        $apply->assertStatus(422);
        $this->assertFalse(DB::table('credit_earned_logs')->where('user_id', $this->userId)->exists(),
            'An empty cutoff must not post zeros (that would lock the month).');
    }

    /** @test */
    public function job_order_employees_are_left_out_of_preview_and_apply()
    {
        $this->actAsAdmin();

        $masterId = $this->seedCutoff('2027-08-01', '2027-08-31', 'August Test Cutoff', 20, 22);
        // employees is InnoDB, so DatabaseTransactions rolls this row back.
        DB::table('hris.employees')->insert(['user_id' => $this->userId, 'employee_type' => 'Job Order']);

        $preview = $this->withoutMiddleware()
            ->getJson('/cutoff-leave-credits-preview?' . http_build_query(['cutoff_id' => $masterId]));
        $preview->assertOk();
        $this->assertNull(
            collect($preview->json('data'))->firstWhere('employee_id', $this->userId),
            'A Job Order employee must not appear in the earned-credits preview.'
        );

        // Even if the client sends the JO employee, the server must not credit
        // them - with no one else in the cutoff there's nothing to post.
        $this->withoutMiddleware()->postJson('/cutoff-leave-credits-apply', [
            'cutoff_id' => $masterId,
            'employees' => [$this->userId],
        ])->assertStatus(422);

        $this->assertFalse(
            DB::table('hris.leave_credits')->where('user_id', $this->userId)->exists(),
            'A Job Order employee must not receive earned credits.'
        );
    }

    /** @test */
    public function cancelled_leave_dates_are_not_counted_when_a_cutoff_is_generated()
    {
        // 2027-09-06..10 is Mon-Fri. Paid leave on Mon (active) + Tue (cancelled);
        // LWOP on Wed (active) + Thu (cancelled).
        $paidId = DB::table('hris.leave_applications')->insertGetId([
            'user_id' => $this->userId, 'status' => 'Approved', 'is_lwop' => 0, 'created_at' => now(),
        ]);
        $lwopId = DB::table('hris.leave_applications')->insertGetId([
            'user_id' => $this->userId, 'status' => 'Approved', 'is_lwop' => 1, 'created_at' => now(),
        ]);
        DB::table('hris.leave_date_applied')->insert([
            ['leave_application_id' => $paidId, 'date_applied' => '2027-09-06', 'hours' => 8, 'status' => 'active'],
            ['leave_application_id' => $paidId, 'date_applied' => '2027-09-07', 'hours' => 8, 'status' => 'cancelled'],
            ['leave_application_id' => $lwopId, 'date_applied' => '2027-09-08', 'hours' => 8, 'status' => 'active'],
            ['leave_application_id' => $lwopId, 'date_applied' => '2027-09-09', 'hours' => 8, 'status' => 'cancelled'],
        ]);

        $dtr = app(\App\Services\DTRServices::class);
        $breakdown = $dtr->getCutoffBreakdown($this->userId, '2027-09-06', '2027-09-10');

        $this->assertSame(1, $breakdown['approved_leave_days'], 'Cancelled paid-leave date must not count.');
        $this->assertSame(1.0, $breakdown['lwop_days'], 'Cancelled LWOP date must not count.');
        // No logs at all: only the active paid-leave Monday counts as present.
        $this->assertSame(1, $dtr->getTotalPresentByRange($this->userId, '2027-09-06', '2027-09-10'));
    }

    /** @test */
    public function partial_day_leave_plus_worked_hours_is_counted_in_cutoff_generation()
    {
        // Mon 2027-10-04 and Tue 2027-10-05 on 8S (08:00-17:00, 12-13 break).
        $scheduleId = DB::table('hris.schedule_master_lists')->where('code', '8S')->value('id');
        $this->assertNotNull($scheduleId, 'Needs the 8S schedule.');
        foreach (['2027-10-04', '2027-10-05'] as $date) {
            DB::table('hris.schedules')->insert([
                'employee_id' => $this->userId, 'schedule_id' => $scheduleId,
                'schedule_date' => $date, 'updated_by' => $this->userId, 'created_at' => now(),
            ]);
        }

        // A 4-hour leave on both days.
        $appId = DB::table('hris.leave_applications')->insertGetId([
            'user_id' => $this->userId, 'status' => 'Approved', 'is_lwop' => 0, 'created_at' => now(),
        ]);
        DB::table('hris.leave_date_applied')->insert([
            ['leave_application_id' => $appId, 'date_applied' => '2027-10-04', 'hours' => 4, 'status' => 'active'],
            ['leave_application_id' => $appId, 'date_applied' => '2027-10-05', 'hours' => 4, 'status' => 'active'],
        ]);

        // Mon: worked 08:00-12:00 (complete). Tue: worked 13:30-17:00 (30 min short).
        DB::table('hris.dtr_logs')->insert([
            ['userid' => $this->userId, 'status' => 'IN',  'created_at' => '2027-10-04 08:00:00'],
            ['userid' => $this->userId, 'status' => 'OUT', 'created_at' => '2027-10-04 12:00:00'],
            ['userid' => $this->userId, 'status' => 'IN',  'created_at' => '2027-10-05 13:30:00'],
            ['userid' => $this->userId, 'status' => 'OUT', 'created_at' => '2027-10-05 17:00:00'],
        ]);

        $dtr = app(\App\Services\DTRServices::class);
        $breakdown = $dtr->getCutoffBreakdown($this->userId, '2027-10-04', '2027-10-05');

        $this->assertSame(0, $breakdown['late_minutes']);
        $this->assertSame(30, $breakdown['undertime_minutes']);
        $this->assertSame(0, $breakdown['absences']);
        $this->assertSame(2, $dtr->getTotalPresentByRange($this->userId, '2027-10-04', '2027-10-05'));
    }

    /** @test */
    public function cww_employee_with_perfect_attendance_earns_the_full_credit()
    {
        $this->actAsAdmin();

        // Mon-Thu CWW, Fri-Sun OFF (2027-11-08..14). Weekdays = 5, but the
        // employee's own work days = 4.
        $cww = DB::table('hris.schedule_master_lists')->where('code', 'CWW')->value('id');
        $off = DB::table('hris.schedule_master_lists')->where('code', 'OFF')->value('id');
        $this->assertNotNull($cww, 'Needs the CWW schedule.');
        $this->assertNotNull($off, 'Needs the OFF schedule.');

        $plan = [
            '2027-11-08' => $cww, '2027-11-09' => $cww, '2027-11-10' => $cww, '2027-11-11' => $cww,
            '2027-11-12' => $off, '2027-11-13' => $off, '2027-11-14' => $off,
        ];
        foreach ($plan as $date => $scheduleId) {
            DB::table('hris.schedules')->insert([
                'employee_id' => $this->userId, 'schedule_id' => $scheduleId,
                'schedule_date' => $date, 'updated_by' => $this->userId, 'created_at' => now(),
            ]);
        }

        // Perfect attendance on all 4 CWW days, plus a visit on the Friday rest
        // day (must not count as an extra present day).
        $logs = [];
        foreach (['2027-11-08', '2027-11-09', '2027-11-10', '2027-11-11', '2027-11-12'] as $date) {
            $logs[] = ['userid' => $this->userId, 'status' => 'IN',  'created_at' => "$date 07:00:00"];
            $logs[] = ['userid' => $this->userId, 'status' => 'OUT', 'created_at' => "$date 18:00:00"];
        }
        DB::table('hris.dtr_logs')->insert($logs);

        $summary = app(\App\Services\PayrollServices::class)->generateCutoffSummary(
            $this->userId, '2027-11-08', '2027-11-14', 'CWW Test Cutoff', $this->userId
        );
        $this->masterId = (int) $summary->cutoff_id;

        $this->assertSame(5, (int) $summary->total_working_days, 'Plain weekday count is unchanged (payroll uses it).');
        $this->assertSame(4, (int) $summary->scheduled_work_days);
        $this->assertSame(4, (int) $summary->days_present, 'The rest-day visit is not a present day.');
        $this->assertSame(0, (int) $summary->days_absent);

        $preview = $this->withoutMiddleware()
            ->getJson('/cutoff-leave-credits-preview?' . http_build_query(['cutoff_id' => $this->masterId]));
        $preview->assertOk();
        $row = collect($preview->json('data'))->firstWhere('employee_id', $this->userId);
        $this->assertNotNull($row);

        // 4 of 4 scheduled days -> lookup key 30 (full credit), not round(4/5*30) = 24.
        $fullPoints = (float) DB::table('monthly_earned_points')->where('lwop', 0)->value('points');
        $this->assertSame(4, $row['work_days']);
        $this->assertEqualsWithDelta($fullPoints, (float) $row['earned_points'], 0.001);
    }

    /** @test */
    public function half_plotted_range_counts_unplotted_weekdays_as_work_days_everywhere()
    {
        // Week 1 (2027-11-08..14) plotted: CWW Mon-Thu, OFF Fri-Sun.
        // Week 2 (2027-11-15..19) NOT plotted (scheduler forgot).
        $cww = DB::table('hris.schedule_master_lists')->where('code', 'CWW')->value('id');
        $off = DB::table('hris.schedule_master_lists')->where('code', 'OFF')->value('id');
        $plan = [
            '2027-11-08' => $cww, '2027-11-09' => $cww, '2027-11-10' => $cww, '2027-11-11' => $cww,
            '2027-11-12' => $off, '2027-11-13' => $off, '2027-11-14' => $off,
        ];
        foreach ($plan as $date => $scheduleId) {
            DB::table('hris.schedules')->insert([
                'employee_id' => $this->userId, 'schedule_id' => $scheduleId,
                'schedule_date' => $date, 'updated_by' => $this->userId, 'created_at' => now(),
            ]);
        }

        // Worked all 4 CWW days, plus Mon-Tue of the unplotted week; absent Wed-Fri.
        $logs = [];
        foreach (['2027-11-08', '2027-11-09', '2027-11-10', '2027-11-11'] as $date) {
            $logs[] = ['userid' => $this->userId, 'status' => 'IN',  'created_at' => "$date 07:00:00"];
            $logs[] = ['userid' => $this->userId, 'status' => 'OUT', 'created_at' => "$date 18:00:00"];
        }
        foreach (['2027-11-15', '2027-11-16'] as $date) {
            $logs[] = ['userid' => $this->userId, 'status' => 'IN',  'created_at' => "$date 08:00:00"];
            $logs[] = ['userid' => $this->userId, 'status' => 'OUT', 'created_at' => "$date 17:00:00"];
        }
        DB::table('hris.dtr_logs')->insert($logs);

        $dtr = app(\App\Services\DTRServices::class);
        $start = '2027-11-08';
        $end = '2027-11-21';

        // 4 plotted CWW days + 5 unplotted weekdays (not just the 4 plotted ones).
        $this->assertSame(9, $dtr->getScheduledWorkDaysByRange($this->userId, $start, $end));
        $this->assertSame(6, $dtr->getTotalPresentByRange($this->userId, $start, $end));
        $this->assertSame(3, $dtr->getCutoffBreakdown($this->userId, $start, $end)['absences']);
    }

    /** @test */
    public function personal_pass_slip_minutes_are_deducted_from_vl_credits_but_not_payroll_undertime()
    {
        $this->actAsAdmin();

        // Mon 2027-12-06 on 8S, worked 08:30-17:00 (30 min late), personal slip 14:00-15:00.
        $scheduleId = DB::table('hris.schedule_master_lists')->where('code', '8S')->value('id');
        DB::table('hris.schedules')->insert([
            'employee_id' => $this->userId, 'schedule_id' => $scheduleId,
            'schedule_date' => '2027-12-06', 'updated_by' => $this->userId, 'created_at' => now(),
        ]);
        DB::table('hris.dtr_logs')->insert([
            ['userid' => $this->userId, 'status' => 'IN',  'created_at' => '2027-12-06 08:30:00'],
            ['userid' => $this->userId, 'status' => 'OUT', 'created_at' => '2027-12-06 17:00:00'],
        ]);
        DB::table('hris.pass_slips')->insert([
            'user_id' => $this->userId, 'slip_date' => '2027-12-06', 'time_out' => '14:00:00',
            'time_in' => '15:00:00', 'type' => 'personal', 'status' => 'approved', 'created_at' => now(),
        ]);

        $summary = app(\App\Services\PayrollServices::class)->generateCutoffSummary(
            $this->userId, '2027-12-06', '2027-12-06', 'Pass Slip Test Cutoff', $this->userId
        );
        $this->masterId = (int) $summary->cutoff_id;

        // Payroll reads late + undertime only: the slip is not salary undertime.
        $this->assertSame(30, (int) $summary->late_minutes);
        $this->assertSame(0, (int) $summary->undertime_minutes);
        $this->assertSame(60, (int) $summary->approved_pass_slips_minutes);

        $row = collect($this->withoutMiddleware()
            ->getJson('/cutoff-leave-credits-preview?' . http_build_query(['cutoff_id' => $this->masterId]))
            ->assertOk()->json('data'))->firstWhere('employee_id', $this->userId);

        // VL deduction = 30 late + 60 slip = 90 minutes.
        $this->assertSame(90, $row['total_late_undertime']);
        $this->assertSame(30, $row['late_undertime_minutes']);
        $this->assertSame(60, $row['pass_slip_minutes']);
        $expected = app(\App\Services\MinuteConversionService::class)->computeDeduction(90);
        $this->assertEqualsWithDelta((float) $expected, (float) $row['deducted_points'], 0.001);
    }

    /** @test */
    public function a_whole_day_of_day_based_leave_covers_a_ten_hour_day_in_cutoff_generation()
    {
        // CWW day (10 work hours), a whole day of Wellness (day-based), no logs.
        $cww = DB::table('hris.schedule_master_lists')->where('code', 'CWW')->value('id');
        $wellness = DB::table('hris.leave_types')->where('deduction_unit', 'days')->value('id');
        $this->assertNotNull($cww, 'Needs the CWW schedule.');
        $this->assertNotNull($wellness, 'Needs a day-based leave type.');

        DB::table('hris.schedules')->insert([
            'employee_id' => $this->userId, 'schedule_id' => $cww,
            'schedule_date' => '2027-12-13', 'updated_by' => $this->userId, 'created_at' => now(),
        ]);
        $appId = DB::table('hris.leave_applications')->insertGetId([
            'user_id' => $this->userId, 'status' => 'Approved', 'is_lwop' => 0, 'created_at' => now(),
        ]);
        DB::table('hris.leave_date_applied')->insert([
            'leave_application_id' => $appId, 'leave_type_id' => $wellness,
            'date_applied' => '2027-12-13', 'hours' => 8, 'status' => 'active',
        ]);

        $breakdown = app(\App\Services\DTRServices::class)->getCutoffBreakdown($this->userId, '2027-12-13', '2027-12-13');

        // Filed as "8" (whole day) but the day is 10 hours: still no undertime, no absence.
        $this->assertSame(0, $breakdown['undertime_minutes']);
        $this->assertSame(0, $breakdown['absences']);
    }

    /** @test */
    public function preview_rejects_a_nonexistent_cutoff_id()
    {
        $this->actAsAdmin();

        $response = $this->withoutMiddleware()
            ->getJson('/cutoff-leave-credits-preview?' . http_build_query(['cutoff_id' => 999999999]));

        $response->assertStatus(422);
    }

    /** @test */
    public function leave_credits_earned_page_renders_for_a_signed_in_user()
    {
        // The layout header needs a real user record (the mocked admin in actAsAdmin has none).
        $userId = DB::table('tdh_user.users')->value('id');
        $model = config('auth.providers.users.model');
        $this->actingAs($model::find($userId));
        session(['access_rights' => 'admin']);

        $this->withoutMiddleware()->get('/leave-credits-earned')
            ->assertOk()
            ->assertSee('Leave Credits Earned')
            ->assertSee(route('leave_credits_earned.cutoffs'), false);
    }

    /** @test */
    public function leave_credits_earned_page_lists_cutoffs_and_their_credited_count()
    {
        $this->actAsAdmin();
        $masterId = $this->seedCutoff('2027-08-01', '2027-08-31', 'Sidebar Page Test Cutoff', 22, 22);

        $listed = fn() => collect($this->withoutMiddleware()->getJson('/leave-credits-earned/cutoffs')->assertOk()->json('data'))
            ->firstWhere('id', $masterId);
        $this->assertSame(['August 2027', 1, 0], [$listed()['month_label'], $listed()['employees'], $listed()['credited']]);

        $this->withoutMiddleware()->postJson('/cutoff-leave-credits-apply', [
            'cutoff_id' => $masterId,
            'employees' => [$this->userId],
        ])->assertOk();

        $this->assertSame(1, $listed()['credited'], 'Posted employees show as credited.');
    }

    /** Complete 08:00-17:00 logs on every non-holiday weekday from..to, except $skip. */
    private function logWeekdays(string $from, string $to, array $skip = []): void
    {
        $holidays = DB::table('hris.calendar_holidays')->whereBetween('date', [$from, $to])->pluck('date')
            ->map(fn($d) => \Carbon\Carbon::parse($d)->toDateString())->all();
        $logs = [];
        foreach (\Carbon\CarbonPeriod::create($from, $to) as $day) {
            $date = $day->toDateString();
            if ($day->isWeekend() || in_array($date, $holidays, true) || in_array($date, $skip, true)) {
                continue;
            }
            $logs[] = ['userid' => $this->userId, 'status' => 'IN', 'created_at' => "$date 08:00:00"];
            $logs[] = ['userid' => $this->userId, 'status' => 'OUT', 'created_at' => "$date 17:00:00"];
        }
        DB::table('hris.dtr_logs')->insert($logs);
    }

    /** @param array<string, int> $hoursByDate */
    private function fileApprovedLwop(array $hoursByDate): void
    {
        $appId = DB::table('hris.leave_applications')->insertGetId([
            'user_id' => $this->userId, 'status' => 'Approved', 'is_lwop' => 1, 'created_at' => now(),
        ]);
        foreach ($hoursByDate as $date => $hours) {
            DB::table('hris.leave_date_applied')->insert([
                'leave_application_id' => $appId, 'date_applied' => $date, 'hours' => $hours, 'status' => 'active',
            ]);
        }
    }

    /** @test */
    public function credit_lwop_days_are_strictly_filed_and_approved_lwop()
    {
        // June 2026: LWOP Fri Jun 5 + unexcused absence Mon Jun 8: credits count the filed day only.
        $this->logWeekdays('2026-05-18', '2026-07-10', ['2026-06-05', '2026-06-08']);
        $this->fileApprovedLwop(['2026-06-05' => 8]);

        $days = app(\App\Services\DTRServices::class)->getCreditWithoutPayDaysByRange($this->userId, '2026-06-01', '2026-06-30');

        $this->assertSame(1.0, $days, 'Only the filed Friday: no weekend, no absence.');
    }

    /** @test */
    public function a_whole_month_absent_without_filing_has_no_lwop_days_for_credits()
    {
        // No logs and nothing filed: 21 absences, but no filed LWOP -> key 0 -> full 1.250.
        $days = app(\App\Services\DTRServices::class)->getCreditWithoutPayDaysByRange($this->userId, '2026-06-01', '2026-06-30');

        $this->assertSame(0.0, $days);
    }

    /** @test */
    public function a_half_day_lwop_is_half_a_day_without_pay()
    {
        $this->logWeekdays('2026-05-18', '2026-07-10', ['2026-06-10']);
        DB::table('hris.dtr_logs')->insert([
            ['userid' => $this->userId, 'status' => 'IN', 'created_at' => '2026-06-10 08:00:00'],
            ['userid' => $this->userId, 'status' => 'OUT', 'created_at' => '2026-06-10 12:00:00'],
        ]);
        $this->fileApprovedLwop(['2026-06-10' => 4]);

        $days = app(\App\Services\DTRServices::class)->getCreditWithoutPayDaysByRange($this->userId, '2026-06-01', '2026-06-30');

        $this->assertSame(0.5, $days);
    }

    /** @test */
    public function generated_cutoff_reads_table_iii_by_filed_lwop_days()
    {
        $this->actAsAdmin();

        // June 2026: LWOP Fri Jun 5, present Mon Jun 8, everything else worked.
        $this->logWeekdays('2026-05-18', '2026-07-10', ['2026-06-05']);
        $this->fileApprovedLwop(['2026-06-05' => 8]);

        $summary = app(\App\Services\PayrollServices::class)->generateCutoffSummary(
            $this->userId, '2026-06-01', '2026-06-30', 'Table III E2E Test Cutoff', $this->userId
        );
        $this->masterId = (int) $summary->cutoff_id;

        $this->assertEquals(1.0, (float) $summary->lwop_days, 'Cutoff LWOP days: the filed Friday only.');
        $this->assertSame(0, (int) $summary->days_absent);
        $this->assertNotNull($summary->credit_without_pay_days, 'Column must be filled at generation.');
        $this->assertEquals(1.0, (float) $summary->credit_without_pay_days, 'Credits: the filed Friday only.');

        $preview = $this->withoutMiddleware()
            ->getJson('/cutoff-leave-credits-preview?' . http_build_query(['cutoff_id' => $this->masterId]));
        $preview->assertOk();
        $row = collect($preview->json('data'))->firstWhere('employee_id', $this->userId);
        $this->assertNotNull($row);

        // Table III: 1.00 day on LWOP -> 1.208.
        $expected = (float) DB::table('monthly_earned_points')->where('lwop', 1)->value('points');
        $this->assertEqualsWithDelta(1.208, $expected, 0.0005);
        $this->assertEquals(1.0, (float) $row['without_pay_days']);
        $this->assertEqualsWithDelta($expected, (float) $row['earned_points'], 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $row['deducted_points'], 0.001);

        $this->withoutMiddleware()->postJson('/cutoff-leave-credits-apply', [
            'cutoff_id' => $this->masterId,
            'employees' => [$this->userId],
        ])->assertOk();

        $vl = DB::table('hris.leave_credits')->where('user_id', $this->userId)->where('leave_id', 2)->value('based_credit');
        $this->assertEqualsWithDelta($expected, (float) $vl, 0.001);
    }

    /** @test */
    public function cutoff_lwop_days_are_the_filed_lwop_days_only()
    {
        // LWOP Fri Jun 5 + Mon Jun 8 and Thu Jun 11 (Fri Jun 12 is a holiday): weekends and
        // holidays are never LWOP, so the cutoff counts exactly the 3 filed days.
        $this->logWeekdays('2026-05-18', '2026-07-10', ['2026-06-05', '2026-06-08', '2026-06-11']);
        $this->fileApprovedLwop(['2026-06-05' => 8, '2026-06-08' => 8, '2026-06-11' => 8]);
        $dtr = app(\App\Services\DTRServices::class);

        $breakdown = $dtr->getCutoffBreakdown($this->userId, '2026-06-01', '2026-06-30');
        $this->assertSame(3.0, $breakdown['lwop_days']);
        $this->assertSame(0, $breakdown['absences'], 'LWOP days are not absences.');
        $this->assertSame(3.0, $dtr->getCreditWithoutPayDaysByRange($this->userId, '2026-06-01', '2026-06-30'));
    }

    /** @test */
    public function earned_credits_are_read_from_table_iii_by_lwop_days()
    {
        $service = app(\App\Services\LeaveCreditService::class);
        $row = fn($lwop) => (float) DB::table('monthly_earned_points')->where('lwop', $lwop)->value('points');

        // setUp sets lwop 5 = 1.7; the other rows are Table III.
        $this->assertEquals(1.7, $service->computeEarnedCredits(20, 0, 22, 6, 2026, 5.0)['monthly_points_earned']);
        $this->assertEquals(1.250, $service->computeEarnedCredits(22, 0, 22, 6, 2026, 0.0)['monthly_points_earned']);
        $this->assertEquals($row(2.5), $service->computeEarnedCredits(20, 0, 22, 6, 2026, 2.5)['monthly_points_earned'], 'Half-day row read directly.');
        $this->assertEquals(0.0, $service->computeEarnedCredits(0, 0, 22, 6, 2026, 30.0)['monthly_points_earned']);
        $this->assertEquals(0.0, $service->computeEarnedCredits(0, 0, 22, 6, 2026, 31.0)['monthly_points_earned']);

        // Between rows (0.4 day = 4 h of a 10-hour shift): interpolated between 0.00 and 0.50.
        $this->assertEquals(round($row(0) + 0.8 * ($row(0.5) - $row(0)), 3), $service->computeEarnedCredits(22, 0, 22, 6, 2026, 0.4)['monthly_points_earned']);

        // NULL (old cutoff) keeps the present / work days formula: 16/22 x 30 = 21.8 -> 22 present = 8 LWOP.
        $this->assertEquals($row(8), $service->computeEarnedCredits(16, 0, 22, 6, 2026, null)['monthly_points_earned']);
    }
}
