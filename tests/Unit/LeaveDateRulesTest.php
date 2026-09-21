<?php

namespace Tests\Unit;

use App\Services\LeaveApplicationService;
use App\Services\MinuteConversionService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class LeaveDateRulesTest extends TestCase
{
    private LeaveApplicationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LeaveApplicationService(new MinuteConversionService());
    }

    /** @test */
    public function advance_notice_counts_working_days_only()
    {
        // Mon 2027-03-01 + 3 working days = Thu 2027-03-04
        $this->assertSame('2027-03-04', $this->service->earliestLeaveDate(3, Carbon::parse('2027-03-01'))->toDateString());
        // Thu 2027-03-04 + 3 working days = Tue 2027-03-09 (weekend skipped)
        $this->assertSame('2027-03-09', $this->service->earliestLeaveDate(3, Carbon::parse('2027-03-04'))->toDateString());
        // Fri 2027-03-05 + 1 working day = Mon 2027-03-08
        $this->assertSame('2027-03-08', $this->service->earliestLeaveDate(1, Carbon::parse('2027-03-05'))->toDateString());
    }

    /** @test */
    public function advance_notice_started_on_a_weekend_counts_from_the_next_working_day()
    {
        // Sat 2027-03-06 + 1 working day = Mon 2027-03-08
        $this->assertSame('2027-03-08', $this->service->earliestLeaveDate(1, Carbon::parse('2027-03-06'))->toDateString());
        // Sun 2027-03-07 + 2 working days = Tue 2027-03-09
        $this->assertSame('2027-03-09', $this->service->earliestLeaveDate(2, Carbon::parse('2027-03-07'))->toDateString());
    }

    /** @test */
    public function zero_days_prior_means_today()
    {
        $this->assertSame('2027-03-01', $this->service->earliestLeaveDate(0, Carbon::parse('2027-03-01'))->toDateString());
    }

    /** @test */
    public function weekend_dates_are_rejected()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('weekend');

        $this->service->assertValidLeaveDates(
            [1 => (object) ['description' => 'Vacation Leave', 'days_prior' => 0]],
            [1 => collect([['date_applied' => '2027-03-06', 'hours' => 8, 'leave_type_id' => 1]])],
            Carbon::parse('2027-03-01')
        );
    }

    /** @test */
    public function dates_before_the_working_day_notice_are_rejected()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Earliest allowed date is 2027-03-09');

        // Thu 2027-03-04 + 3 working days => Tue 03-09; Mon 03-08 is too early
        $this->service->assertValidLeaveDates(
            [1 => (object) ['description' => 'Vacation Leave', 'days_prior' => 3]],
            [1 => collect([['date_applied' => '2027-03-08', 'hours' => 8, 'leave_type_id' => 1]])],
            Carbon::parse('2027-03-04')
        );
    }

    /** @test */
    public function negative_days_prior_accepts_past_weekdays()
    {
        // Sick-leave style: filed on return (Thu 03-04) for Mon-Tue 03-01/03-02
        $this->service->assertValidLeaveDates(
            [1 => (object) ['description' => 'Sick Leave', 'days_prior' => -1]],
            [1 => collect([
                ['date_applied' => '2027-03-01', 'hours' => 8, 'leave_type_id' => 1],
                ['date_applied' => '2027-03-02', 'hours' => 8, 'leave_type_id' => 1],
            ])],
            Carbon::parse('2027-03-04')
        );

        $this->assertTrue(true);
    }

    /** @test */
    public function negative_days_prior_still_rejects_weekends()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('weekend');

        $this->service->assertValidLeaveDates(
            [1 => (object) ['description' => 'Sick Leave', 'days_prior' => -1]],
            [1 => collect([['date_applied' => '2027-03-06', 'hours' => 8, 'leave_type_id' => 1]])],
            Carbon::parse('2027-03-08')
        );
    }

    /** @test */
    public function weekday_dates_on_or_after_the_notice_are_accepted()
    {
        $this->service->assertValidLeaveDates(
            [1 => (object) ['description' => 'Vacation Leave', 'days_prior' => 3]],
            [1 => collect([['date_applied' => '2027-03-09', 'hours' => 8, 'leave_type_id' => 1]])],
            Carbon::parse('2027-03-04')
        );

        $this->assertTrue(true);
    }
}
