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
            DB::table('hris.cutoff_summaries')->where('cutoff_id', $this->masterId)->delete();
            DB::table('hris.cutoff_summaries_master')->where('id', $this->masterId)->delete();
        }
        DB::table('hris.leave_credits')->where('user_id', $this->userId)->delete();
        DB::table('credit_earned_logs')->where('user_id', $this->userId)->delete();

        foreach ($this->originalPoints as $present => $points) {
            DB::table('monthly_earned_points')->where('present', $present)->update(['points' => $points]);
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

        $this->withoutMiddleware()->postJson('/cutoff-leave-credits-apply', $payload)->assertOk();
        $secondRunCredit = DB::table('hris.leave_credits')
            ->where('user_id', $this->userId)->where('leave_id', 2)->value('based_credit');

        $this->assertEqualsWithDelta((float) $firstRunCredit, (float) $secondRunCredit, 0.001);
    }

    /** @test */
    public function preview_rejects_a_nonexistent_cutoff_id()
    {
        $this->actAsAdmin();

        $response = $this->withoutMiddleware()
            ->getJson('/cutoff-leave-credits-preview?' . http_build_query(['cutoff_id' => 999999999]));

        $response->assertStatus(422);
    }
}
