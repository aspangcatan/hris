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
}
