<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MonthlyLeaveCreditsTest extends TestCase
{
    use DatabaseTransactions;

    private int $userId = 9601;

    /** @var int[] */
    private array $seededMasterIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        // APP_URL contains a subfolder (/hris) which breaks test HTTP client path matching.
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);
    }

    protected function tearDown(): void
    {
        // These tables are MyISAM, so DatabaseTransactions rollback is a no-op — clean up explicitly.
        foreach ($this->seededMasterIds as $masterId) {
            DB::table('hris.cutoff_summaries')->where('cutoff_id', $masterId)->delete();
            DB::table('hris.cutoff_summaries_master')->where('id', $masterId)->delete();
        }
        DB::table('hris.leave_credits')->where('user_id', $this->userId)->delete();
        DB::table('credit_earned_logs')->where('user_id', $this->userId)->delete();
        parent::tearDown();
    }

    private function actAsAdmin(): void
    {
        Auth::shouldReceive('id')->andReturn($this->userId);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn(null);
        session(['access_rights' => 'admin']);
    }

    private function seedCutoffMaster(string $start, string $end, string $title): int
    {
        DB::table('hris.cutoff_summaries_master')
            ->where('period_start', $start)
            ->where('period_end', $end)
            ->where('title', $title)
            ->delete();

        $id = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title'        => $title,
            'period_start' => $start,
            'period_end'   => $end,
            'generated_by' => $this->userId,
            'generated_at' => now(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $this->seededMasterIds[] = $id;
        return $id;
    }

    private function seedCutoffSummary(int $cutoffId, string $start, string $end, int $daysPresent, int $totalWorkingDays, int $lateMinutes = 0, int $undertimeMinutes = 0): void
    {
        DB::table('hris.cutoff_summaries')->updateOrInsert(
            ['employee_id' => $this->userId, 'cutoff_id' => $cutoffId],
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
    }

    /** @test */
    public function preview_and_apply_compute_aggregated_net_points_for_a_complete_month()
    {
        $this->actAsAdmin();

        $firstHalf = $this->seedCutoffMaster('2027-03-01', '2027-03-15', 'March 1st Half');
        $secondHalf = $this->seedCutoffMaster('2027-03-16', '2027-03-31', 'March 2nd Half');
        $this->seedCutoffSummary($firstHalf, '2027-03-01', '2027-03-15', 10, 11);
        $this->seedCutoffSummary($secondHalf, '2027-03-16', '2027-03-31', 10, 11);

        // Aggregated: present=20, workingDays=22 -> lookup key round((20/22)*30) = 27.
        $expectedPoints = DB::table('monthly_earned_points')->where('present', 27)->value('points');
        $this->assertNotNull($expectedPoints, 'Expected a seeded monthly_earned_points row for present=27.');

        $preview = $this->withoutMiddleware()
            ->getJson('/cutoff-leave-credits-preview?' . http_build_query(['year' => 2027, 'month' => 3]));
        $preview->assertOk();

        $row = collect($preview->json('data'))->firstWhere('employee_id', $this->userId);
        $this->assertNotNull($row, 'Expected a preview row for the test employee.');
        $this->assertEquals('complete', $row['status']);
        $this->assertEquals(20, $row['total_present']);
        $this->assertEquals(0, $row['total_late_undertime']);
        $this->assertEqualsWithDelta((float) $expectedPoints, (float) $row['net_points'], 0.001);

        $apply = $this->withoutMiddleware()->postJson('/cutoff-leave-credits-apply', [
            'year' => 2027,
            'month' => 3,
            'employees' => [$this->userId],
        ]);
        $apply->assertOk();

        $vlCredit = DB::table('hris.leave_credits')
            ->where('user_id', $this->userId)->where('leave_id', 2)->value('based_credit');
        $this->assertEqualsWithDelta((float) $expectedPoints, (float) $vlCredit, 0.001);

        $logCount = DB::table('credit_earned_logs')
            ->where('user_id', $this->userId)->where('year', 2027)->where('month', 3)->count();
        $this->assertEquals(2, $logCount, 'Expected one credit_earned_logs row per monthly-earned leave type (VL + SL).');
    }

    /** @test */
    public function preview_flags_and_apply_skips_an_employee_missing_half_the_month()
    {
        $this->actAsAdmin();

        $firstHalf = $this->seedCutoffMaster('2027-04-01', '2027-04-15', 'April 1st Half');
        $this->seedCutoffMaster('2027-04-16', '2027-04-30', 'April 2nd Half');
        $this->seedCutoffSummary($firstHalf, '2027-04-01', '2027-04-15', 10, 11);
        // Second half cutoff master exists, but no cutoff_summaries row for this employee — incomplete.

        $preview = $this->withoutMiddleware()
            ->getJson('/cutoff-leave-credits-preview?' . http_build_query(['year' => 2027, 'month' => 4]));
        $preview->assertOk();

        $row = collect($preview->json('data'))->firstWhere('employee_id', $this->userId);
        $this->assertNotNull($row, 'Expected an incomplete preview row for the test employee.');
        $this->assertEquals('incomplete', $row['status']);

        $apply = $this->withoutMiddleware()->postJson('/cutoff-leave-credits-apply', [
            'year' => 2027,
            'month' => 4,
            'employees' => [$this->userId],
        ]);
        $apply->assertOk();

        $exists = DB::table('hris.leave_credits')
            ->where('user_id', $this->userId)->where('last_updated_month', '2027-4')->exists();
        $this->assertFalse($exists, 'Incomplete-month employee must not receive credits.');
    }

    /** @test */
    public function reapplying_the_same_month_does_not_double_credit()
    {
        $this->actAsAdmin();

        $firstHalf = $this->seedCutoffMaster('2027-05-01', '2027-05-15', 'May 1st Half');
        $secondHalf = $this->seedCutoffMaster('2027-05-16', '2027-05-31', 'May 2nd Half');
        $this->seedCutoffSummary($firstHalf, '2027-05-01', '2027-05-15', 11, 11);
        $this->seedCutoffSummary($secondHalf, '2027-05-16', '2027-05-31', 10, 11);

        $payload = ['year' => 2027, 'month' => 5, 'employees' => [$this->userId]];
        $this->withoutMiddleware()->postJson('/cutoff-leave-credits-apply', $payload)->assertOk();

        $firstRunCredit = DB::table('hris.leave_credits')
            ->where('user_id', $this->userId)->where('leave_id', 2)->value('based_credit');

        $this->withoutMiddleware()->postJson('/cutoff-leave-credits-apply', $payload)->assertOk();

        $secondRunCredit = DB::table('hris.leave_credits')
            ->where('user_id', $this->userId)->where('leave_id', 2)->value('based_credit');

        $this->assertEqualsWithDelta((float) $firstRunCredit, (float) $secondRunCredit, 0.001);
    }
}
