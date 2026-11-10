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
        $this->originalPoints = DB::table('monthly_earned_points')->whereIn('present', [25])->pluck('points', 'present');
        DB::table('monthly_earned_points')->updateOrInsert(['present' => 25], ['points' => 1.7]);

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
            DB::table('monthly_earned_points')->where('present', $present)->update(['points' => $points]);
        }
        foreach ($this->originalDeductions as $minute => $deduction) {
            DB::table('minute_conversions')->where('minute', $minute)->update(['points_deduction' => $deduction]);
        }
        \App\Services\LwopRuleService::override(null);
        \App\Services\LwopRuleService::flush();

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
        $fullPoints = (float) DB::table('monthly_earned_points')->where('present', 30)->value('points');
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
    public function without_pay_days_do_not_count_the_weekend_around_a_short_absence()
    {
        // June 2026 (Jun 12 = holiday). LWOP Fri Jun 5 + unexcused absence Mon Jun 8:
        // a 4-day span, so Sat/Sun Jun 6-7 are NOT without pay (CSC Sec. 33).
        $this->logWeekdays('2026-05-18', '2026-07-10', ['2026-06-05', '2026-06-08']);
        $this->fileApprovedLwop(['2026-06-05' => 8]);

        $days = app(\App\Services\DTRServices::class)->getCreditWithoutPayDaysByRange($this->userId, '2026-06-01', '2026-06-30');

        $this->assertSame(2.0, $days);
    }

    /** @test */
    public function without_pay_days_absorb_the_weekend_inside_a_run_of_lwop_and_absences_over_seven_days()
    {
        // LWOP Mon Jun 1 - Fri Jun 5, then unexcused absences Mon Jun 8 - Thu Jun 11:
        // one 11-day run -> Sat/Sun Jun 6-7 count too: 5 + 2 + 4 = 11.
        $this->logWeekdays('2026-05-18', '2026-07-10', [
            '2026-06-01', '2026-06-02', '2026-06-03', '2026-06-04', '2026-06-05',
            '2026-06-08', '2026-06-09', '2026-06-10', '2026-06-11',
        ]);
        $this->fileApprovedLwop([
            '2026-06-01' => 8, '2026-06-02' => 8, '2026-06-03' => 8, '2026-06-04' => 8, '2026-06-05' => 8,
        ]);

        $days = app(\App\Services\DTRServices::class)->getCreditWithoutPayDaysByRange($this->userId, '2026-06-01', '2026-06-30');

        $this->assertSame(11.0, $days);
    }

    /** @test */
    public function a_whole_month_without_logs_is_thirty_days_without_pay()
    {
        $days = app(\App\Services\DTRServices::class)->getCreditWithoutPayDaysByRange($this->userId, '2026-06-01', '2026-06-30');

        $this->assertSame(30.0, $days);
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
    public function generated_cutoff_stores_without_pay_days_and_credits_use_thirty_minus_them()
    {
        $this->actAsAdmin();

        // June 2026: LWOP Fri Jun 5 + unexcused absence Mon Jun 8, everything else worked.
        $this->logWeekdays('2026-05-18', '2026-07-10', ['2026-06-05', '2026-06-08']);
        $this->fileApprovedLwop(['2026-06-05' => 8]);

        $summary = app(\App\Services\PayrollServices::class)->generateCutoffSummary(
            $this->userId, '2026-06-01', '2026-06-30', 'Sec 33 E2E Test Cutoff', $this->userId
        );
        $this->masterId = (int) $summary->cutoff_id;

        $this->assertEquals(1.0, (float) $summary->lwop_days, 'Only the filed LWOP date; the weekend is not absorbed.');
        $this->assertSame(1, (int) $summary->days_absent);
        $this->assertNotNull($summary->credit_without_pay_days, 'Column must be filled at generation.');
        $this->assertEquals(2.0, (float) $summary->credit_without_pay_days);

        $preview = $this->withoutMiddleware()
            ->getJson('/cutoff-leave-credits-preview?' . http_build_query(['cutoff_id' => $this->masterId]));
        $preview->assertOk();
        $row = collect($preview->json('data'))->firstWhere('employee_id', $this->userId);
        $this->assertNotNull($row);

        // Key 30 - 2 = 28 -> HR's row for 28 days.
        $expected = (float) DB::table('monthly_earned_points')->where('present', 28)->value('points');
        $this->assertEquals(2.0, (float) $row['without_pay_days']);
        $this->assertEqualsWithDelta($expected, (float) $row['earned_points'], 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $row['deducted_points'], 0.001);

        $this->withoutMiddleware()->postJson('/cutoff-leave-credits-apply', [
            'cutoff_id' => $this->masterId,
            'employees' => [$this->userId],
        ])->assertOk();

        $vl = DB::table('hris.leave_credits')->where('user_id', $this->userId)->where('leave_id', 2)->value('based_credit');
        $this->assertEqualsWithDelta($expected, (float) $vl, 0.001);
    }

    /** LWOP Fri Jun 5 + absent Mon Jun 8 (a 4-day span), everything else worked. */
    private function seedShortAbsenceAroundWeekend(): void
    {
        $this->logWeekdays('2026-05-18', '2026-07-10', ['2026-06-05', '2026-06-08']);
        $this->fileApprovedLwop(['2026-06-05' => 8]);
    }

    /** @test */
    public function lwop_settings_run_threshold_drives_weekend_absorption()
    {
        $this->seedShortAbsenceAroundWeekend();
        $dtr = app(\App\Services\DTRServices::class);

        // Base rules (> 7 days): weekend not counted.
        \App\Services\LwopRuleService::override([]);
        $this->assertSame(2.0, $dtr->getCreditWithoutPayDaysByRange($this->userId, '2026-06-01', '2026-06-30'));

        // A rule set with "longer than 3 days": the 4-day span now absorbs Sat/Sun.
        \App\Services\LwopRuleService::override(['long_run_min_days' => 3]);
        $this->assertSame(4.0, $dtr->getCreditWithoutPayDaysByRange($this->userId, '2026-06-01', '2026-06-30'));
    }

    /** @test */
    public function lwop_settings_can_switch_off_rest_day_counting_and_absences()
    {
        // LWOP Mon Jun 1 - Fri Jun 5 + absences Mon Jun 8 - Thu Jun 11 (11 with base rules).
        $this->logWeekdays('2026-05-18', '2026-07-10', [
            '2026-06-01', '2026-06-02', '2026-06-03', '2026-06-04', '2026-06-05',
            '2026-06-08', '2026-06-09', '2026-06-10', '2026-06-11',
        ]);
        $this->fileApprovedLwop([
            '2026-06-01' => 8, '2026-06-02' => 8, '2026-06-03' => 8, '2026-06-04' => 8, '2026-06-05' => 8,
        ]);
        $dtr = app(\App\Services\DTRServices::class);
        $june = fn() => $dtr->getCreditWithoutPayDaysByRange($this->userId, '2026-06-01', '2026-06-30');

        \App\Services\LwopRuleService::override(['count_rest_days_in_long_runs' => false]);
        $this->assertSame(9.0, $june(), 'No rest days absorbed: 5 LWOP + 4 absences.');

        \App\Services\LwopRuleService::override(['absences_count_without_pay' => false]);
        $this->assertSame(5.0, $june(), 'Only the 5 LWOP days (a 5-day run, nothing absorbed).');

        \App\Services\LwopRuleService::override(['absences_join_runs' => false]);
        $this->assertSame(9.0, $june(), 'Absences counted on their own: the LWOP run is only 5 days.');
    }

    /** @test */
    public function lwop_settings_partial_day_mode_whole_counts_a_half_day_as_one()
    {
        $this->logWeekdays('2026-05-18', '2026-07-10', ['2026-06-10']);
        DB::table('hris.dtr_logs')->insert([
            ['userid' => $this->userId, 'status' => 'IN', 'created_at' => '2026-06-10 08:00:00'],
            ['userid' => $this->userId, 'status' => 'OUT', 'created_at' => '2026-06-10 12:00:00'],
        ]);
        $this->fileApprovedLwop(['2026-06-10' => 4]);
        $dtr = app(\App\Services\DTRServices::class);

        \App\Services\LwopRuleService::override(['partial_day_mode' => 'whole']);
        $this->assertSame(1.0, $dtr->getCreditWithoutPayDaysByRange($this->userId, '2026-06-01', '2026-06-30'));
        $this->assertSame(1.0, $dtr->getCutoffBreakdown($this->userId, '2026-06-01', '2026-06-30')['lwop_days']);
    }

    /** @test */
    public function lwop_settings_month_basis_and_fractional_key_drive_earned_credits()
    {
        $service = app(\App\Services\LeaveCreditService::class);
        $points = DB::table('monthly_earned_points')->pluck('points', 'present')
            ->mapWithKeys(fn($v, $k) => [(int) round((float) $k) => (float) $v]);

        \App\Services\LwopRuleService::override(['fractional_key_mode' => 'floor']);
        $this->assertEquals($points[29], $service->computeEarnedCredits(21, 0, 22, 6, 2026, 0.5)['monthly_points_earned']);

        \App\Services\LwopRuleService::override(['fractional_key_mode' => 'nearest']);
        $this->assertEquals($points[29], $service->computeEarnedCredits(21, 0, 22, 6, 2026, 1.5)['monthly_points_earned'], '28.5 -> 29');

        \App\Services\LwopRuleService::override(['month_basis_days' => 26]);
        $this->assertEquals($points[24], $service->computeEarnedCredits(21, 0, 22, 6, 2026, 2.0)['monthly_points_earned'], '26 - 2 = 24');
    }

    /** @test */
    public function earned_credits_use_thirty_minus_without_pay_days()
    {
        $service = app(\App\Services\LeaveCreditService::class);

        // setUp sets present 25 = 1.7; the other rows are HR's real table.
        $this->assertEquals(1.7, $service->computeEarnedCredits(20, 0, 22, 6, 2026, 5.0)['monthly_points_earned']);
        $this->assertEquals(0.0, $service->computeEarnedCredits(0, 0, 22, 6, 2026, 30.0)['monthly_points_earned']);
        $this->assertEquals(0.0, $service->computeEarnedCredits(0, 0, 22, 6, 2026, 31.0)['monthly_points_earned']);

        // Half key: halfway between the 25 and 26 rows.
        $p26 = (float) DB::table('monthly_earned_points')->where('present', 26)->value('points');
        $this->assertEquals(round(1.7 + 0.5 * ($p26 - 1.7), 3), $service->computeEarnedCredits(21, 0, 22, 6, 2026, 4.5)['monthly_points_earned']);

        // NULL (old cutoff) keeps the present / work days formula: 16/22 x 30 = 21.8 -> 22.
        $p22 = (float) DB::table('monthly_earned_points')->where('present', 22)->value('points');
        $this->assertEquals($p22, $service->computeEarnedCredits(16, 0, 22, 6, 2026, null)['monthly_points_earned']);
    }
}
