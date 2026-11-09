<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CutoffSummaryDaysTest extends TestCase
{
    use DatabaseTransactions;

    private int $userId = 9503;
    private ?int $masterId = null;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);
    }

    protected function tearDown(): void
    {
        if ($this->masterId) {
            DB::table('hris.cutoff_summary_days')->where('cutoff_id', $this->masterId)->delete();
            DB::table('hris.cutoff_summaries')->where('cutoff_id', $this->masterId)->delete();
            DB::table('hris.cutoff_summaries_master')->where('id', $this->masterId)->delete();
        }
        DB::table('hris.dtr_logs')->where('userid', $this->userId)->delete();
        DB::table('hris.schedules')->where('employee_id', $this->userId)->delete();
        DB::table('hris.schedule_master_lists')->where('code', 'CSDTESTSC')->delete();
        parent::tearDown();
    }

    private function actAsAdmin(): void
    {
        Auth::shouldReceive('id')->andReturn($this->userId);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'admin']);
    }

    /** @test */
    public function generating_a_cutoff_summary_persists_matching_per_day_rows(): void
    {
        $this->actAsAdmin();

        DB::table('hris.schedule_master_lists')->where('code', 'CSDTESTSC')->delete();
        $schedId = DB::table('hris.schedule_master_lists')->insertGetId([
            'code' => 'CSDTESTSC', 'label' => 'CSD Test', 'type' => 'FIXED',
            'time_in' => '08:00:00', 'time_in_latest' => '08:00:00',
            'time_out' => '17:00:00', 'time_out_latest' => '17:00:00',
            'break_out' => '12:00:00', 'break_in' => '13:00:00',
        ]);
        DB::table('hris.schedules')->updateOrInsert(
            ['employee_id' => $this->userId, 'schedule_date' => '2027-06-01'],
            ['schedule_id' => $schedId, 'updated_by' => $this->userId, 'created_at' => now()]
        );
        DB::table('hris.dtr_logs')->insert([
            ['userid' => $this->userId, 'status' => 'IN', 'type' => 2, 'created_at' => '2027-06-01 09:15:00', 'updated_at' => '2027-06-01 09:15:00'],
            ['userid' => $this->userId, 'status' => 'OUT', 'type' => 2, 'created_at' => '2027-06-01 17:00:00', 'updated_at' => '2027-06-01 17:00:00'],
        ]);

        // Cutoffs can only be generated for the previous month: pretend it's
        // July 10, 2027, when the team would generate June's cutoff.
        \Illuminate\Support\Carbon::setTestNow('2027-07-10 09:00:00');
        try {
            $response = $this->withoutMiddleware()->postJson('/cutoff-summaries', [
                'title' => 'CSD Test Cutoff',
                'start_date' => '2027-06-01',
                'end_date' => '2027-06-01',
                'employees' => [$this->userId],
            ]);
        } finally {
            \Illuminate\Support\Carbon::setTestNow();
        }
        $response->assertOk();

        $this->masterId = DB::table('hris.cutoff_summaries_master')
            ->where('title', 'CSD Test Cutoff')->value('id');
        $this->assertNotNull($this->masterId);

        $dayRow = DB::table('hris.cutoff_summary_days')
            ->where('cutoff_id', $this->masterId)
            ->where('employee_id', $this->userId)
            ->where('date', '2027-06-01')
            ->first();

        $this->assertNotNull($dayRow, 'Expected a persisted cutoff_summary_days row.');
        $this->assertEquals(75, $dayRow->late_minutes); // 09:15 - 08:00
        $this->assertEquals(0, $dayRow->undertime_minutes);
        $this->assertEquals(0, (int) $dayRow->is_absent);
    }
}
