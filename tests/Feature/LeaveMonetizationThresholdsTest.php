<?php

namespace Tests\Feature;

use App\Services\LeaveMonetizationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeaveMonetizationThresholdsTest extends TestCase
{
    use DatabaseTransactions;

    private int $employeeId = 9371;
    private int $leaveTypeId;
    private LeaveMonetizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LeaveMonetizationService::class);
        $this->cleanup();

        DB::table('tdh_user.users')->insertOrIgnore([
            'id' => $this->employeeId, 'fname' => 'Thresh', 'lname' => 'EmpUser',
            'username' => 'thresh_emp_test', 'password' => 'x',
        ]);

        // leave_types is MyISAM, so DatabaseTransactions rollback is a
        // silent no-op for it - cleaned up manually in cleanup() below,
        // same pattern as LeaveApplicationMinuteDeductionTest.
        DB::table('hris.leave_types')->updateOrInsert(
            ['code' => 'MONTEST'],
            [
                'description'       => 'Monetization Threshold Test Leave',
                'monthly_earned'    => 0,
                'status'            => 'active',
                'monetizable'       => 1,
                'min_monetize_days' => 10,
                'min_retained_days' => 5,
            ]
        );
        $this->leaveTypeId = DB::table('hris.leave_types')->where('code', 'MONTEST')->value('id');
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        $typeId = DB::table('hris.leave_types')->where('code', 'MONTEST')->value('id');
        if ($typeId) {
            $monIds = DB::table('hris.leave_monetizations')->where('leave_type_id', $typeId)->pluck('id');
            DB::table('hris.leave_monetization_approvals')->whereIn('leave_monetization_id', $monIds)->delete();
            DB::table('hris.leave_monetizations')->whereIn('id', $monIds)->delete();
        }
        DB::table('hris.leave_credits')->where('user_id', $this->employeeId)->delete();
        DB::table('hris.leave_types')->where('code', 'MONTEST')->delete();
        DB::table('tdh_user.users')->where('id', $this->employeeId)->delete();
    }

    private function setBalanceDays(float $days): void
    {
        DB::table('hris.leave_credits')->updateOrInsert(
            ['user_id' => $this->employeeId, 'leave_id' => $this->leaveTypeId],
            ['based_credit' => $days * 1.25, 'used_credit' => 0, 'last_updated_month' => '2027-1']
        );
    }

    private function actAsEmployee(): void
    {
        Auth::shouldReceive('id')->andReturn($this->employeeId);
        // section/division only need to be non-null here (leave_monetization_approvals.approver_id
        // is NOT NULL) - store()'s own approval-chain insert doesn't validate they reference real rows.
        Auth::shouldReceive('user')->andReturn((object) ['id' => $this->employeeId, 'section' => 1, 'division' => 1]);
    }

    /** @test */
    public function rejects_a_request_below_the_leave_types_minimum_monetize_days(): void
    {
        $this->actAsEmployee();
        $this->setBalanceDays(20); // plenty of balance, well above the request

        $this->expectExceptionMessageMatches('/minimum of 10 day/');

        $this->service->store([
            'leave_type_id'  => $this->leaveTypeId,
            'days_requested' => 5, // below the 10-day minimum
        ]);
    }

    /** @test */
    public function rejects_a_request_that_would_leave_less_than_the_minimum_retained_balance(): void
    {
        $this->actAsEmployee();
        $this->setBalanceDays(12); // 12 - 10 = 2 left, below the 5-day retained minimum

        $this->expectExceptionMessageMatches('/retain at least 5 day/');

        $this->service->store([
            'leave_type_id'  => $this->leaveTypeId,
            'days_requested' => 10,
        ]);
    }

    /** @test */
    public function allows_a_request_that_exactly_meets_both_thresholds(): void
    {
        $this->actAsEmployee();
        $this->setBalanceDays(15); // 15 - 10 = 5 left, exactly the retained minimum

        $id = $this->service->store([
            'leave_type_id'  => $this->leaveTypeId,
            'days_requested' => 10,
        ]);

        $this->assertEquals(10, DB::table('hris.leave_monetizations')->where('id', $id)->value('days_requested'));
    }
}
