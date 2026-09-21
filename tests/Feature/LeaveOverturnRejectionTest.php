<?php

namespace Tests\Feature;

use App\Services\LeaveApplicationService;
use App\Services\LeaveApprovalService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeaveOverturnRejectionTest extends TestCase
{
    use DatabaseTransactions;

    private int $employeeId = 9181;
    private int $shUserId   = 9182;
    private int $dhUserId   = 9183;
    private int $hrUserId   = 9184;
    private int $sectionId  = 9281;
    private int $divisionId = 9282;
    private int $leaveTypeId;

    private string $sampleSignature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanup();

        DB::table('tdh_user.users')->insertOrIgnore([
            ['id' => $this->shUserId,   'fname' => 'Ovt', 'lname' => 'ShUser', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'ovt_sh_test', 'password' => 'x'],
            ['id' => $this->dhUserId,   'fname' => 'Ovt', 'lname' => 'DhUser', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'ovt_dh_test', 'password' => 'x'],
            ['id' => $this->employeeId, 'fname' => 'Ovt', 'lname' => 'EmpUser', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'ovt_emp_test', 'password' => 'x'],
            ['id' => $this->hrUserId,   'fname' => 'Ovt', 'lname' => 'HrUser', 'section' => 0, 'division' => 0, 'username' => 'ovt_hr_test', 'password' => 'x'],
        ]);
        DB::table('tdh_user.division')->insertOrIgnore([
            ['id' => $this->divisionId, 'description' => 'Ovt Test Division', 'head' => $this->dhUserId, 'code' => 'OVTDIV'],
        ]);
        DB::table('tdh_user.section')->insertOrIgnore([
            ['id' => $this->sectionId, 'description' => 'Ovt Test Section', 'head' => $this->shUserId, 'division' => $this->divisionId, 'code' => 'OVTSEC'],
        ]);

        DB::table('hris.leave_types')->updateOrInsert(
            ['code' => 'OVTTEST'],
            ['description' => 'Ovt Test Leave', 'monthly_earned' => 0, 'status' => 'active']
        );
        $this->leaveTypeId = DB::table('hris.leave_types')->where('code', 'OVTTEST')->value('id');

        DB::table('hris.leave_credits')->insertOrIgnore([
            'user_id' => $this->employeeId,
            'leave_id' => $this->leaveTypeId,
            'based_credit' => 10.000,
            'used_credit' => 0.000,
            'last_updated_month' => '2027-1',
        ]);
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        $appIds = DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->pluck('id');
        DB::table('hris.leave_details')->whereIn('leave_application_id', $appIds)->delete();
        DB::table('hris.leave_date_applied')->whereIn('leave_application_id', $appIds)->delete();
        DB::table('hris.leave_application_approvals')->whereIn('leave_application_id', $appIds)->delete();
        DB::table('hris.leave_applications')->whereIn('id', $appIds)->delete();
        DB::table('hris.leave_credits')->where('user_id', $this->employeeId)->delete();
    }

    private function balance(): float
    {
        $c = DB::table('hris.leave_credits')->where('user_id', $this->employeeId)->where('leave_id', $this->leaveTypeId)->first();
        return round($c->based_credit - $c->used_credit, 3);
    }

    /** Files an 8-hour (1.0 credit) leave as the employee, then has the SH reject it. */
    private function fileAndRejectBySh(): int
    {
        Auth::shouldReceive('id')->andReturn($this->employeeId);
        Auth::shouldReceive('user')->andReturn((object) ['id' => $this->employeeId, 'section' => $this->sectionId, 'division' => $this->divisionId]);
        Auth::shouldReceive('check')->andReturn(true);

        $appId = app(LeaveApplicationService::class)->insertLeaveApplication([
            'commutation' => 'Not Requested',
            'dates' => [['date_applied' => '2027-03-01', 'hours' => 8, 'leave_type_id' => $this->leaveTypeId]],
            'applicant_signature' => $this->sampleSignature,
        ]);
        $appId = is_array($appId) ? ($appId['id'] ?? reset($appId)) : $appId;

        $this->assertEquals(9.0, $this->balance(), 'filing deducts 1.0 credit');

        app(LeaveApprovalService::class)->updateApprovalStatus($appId, 'SH', 'Not justified', 'rejected');

        $this->assertEquals('Rejected', $this->appStatus($appId));
        $this->assertEquals(10.0, $this->balance(), 'SH rejection restores the credit');

        return $appId;
    }

    private function appStatus(int $appId): string
    {
        return DB::table('hris.leave_applications')->where('id', $appId)->value('status');
    }

    private function stepStatus(int $appId, string $role): string
    {
        return strtolower(DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $appId)->where('role', $role)->value('status'));
    }

    /** @test */
    public function dh_approving_a_sh_rejected_leave_without_confirmation_is_refused(): void
    {
        $appId = $this->fileAndRejectBySh();

        try {
            app(LeaveApprovalService::class)->updateApprovalStatus($appId, 'DH', null, 'approved', [], $this->sampleSignature);
            $this->fail('Expected the approval to be refused');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('overturn', $e->getMessage());
        }

        $this->assertEquals('Rejected', $this->appStatus($appId));
        $this->assertEquals('pending', $this->stepStatus($appId, 'DH'));
        $this->assertEquals(10.0, $this->balance());
    }

    /** @test */
    public function confirmed_dh_approval_overturns_the_rejection_and_the_leave_continues_to_hr(): void
    {
        $appId = $this->fileAndRejectBySh();

        app(LeaveApprovalService::class)->updateApprovalStatus($appId, 'DH', 'Approved despite SH', 'approved', [], $this->sampleSignature, true);

        $this->assertEquals('Pending', $this->appStatus($appId));
        $this->assertEquals('rejected', $this->stepStatus($appId, 'SH'), 'SH rejection stays in the history');
        $this->assertEquals('approved', $this->stepStatus($appId, 'DH'));
        $this->assertEquals(9.0, $this->balance(), 'credits are deducted again');

        app(LeaveApprovalService::class)->updateApprovalStatus($appId, 'HR', null, 'approved');

        $this->assertEquals('Approved', $this->appStatus($appId));
        $this->assertEquals(9.0, $this->balance());
    }

    /** @test */
    public function overturning_fails_when_the_employee_no_longer_has_enough_credits(): void
    {
        $appId = $this->fileAndRejectBySh();
        DB::table('hris.leave_credits')
            ->where('user_id', $this->employeeId)->where('leave_id', $this->leaveTypeId)
            ->update(['based_credit' => 0.500]);

        try {
            app(LeaveApprovalService::class)->updateApprovalStatus($appId, 'DH', null, 'approved', [], $this->sampleSignature, true);
            $this->fail('Expected the overturn to fail on insufficient credits');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('credits', $e->getMessage());
        }

        $this->assertEquals('Rejected', $this->appStatus($appId));
        $this->assertEquals('pending', $this->stepStatus($appId, 'DH'));
        $this->assertEquals(0.5, $this->balance());
    }

    /** Files a leave, SH approves it, DH rejects it. Returns the application id. */
    private function fileApproveByShAndRejectByDh(): int
    {
        Auth::shouldReceive('id')->andReturn($this->employeeId);
        Auth::shouldReceive('user')->andReturn((object) ['id' => $this->employeeId, 'section' => $this->sectionId, 'division' => $this->divisionId]);
        Auth::shouldReceive('check')->andReturn(true);

        $appId = app(LeaveApplicationService::class)->insertLeaveApplication([
            'commutation' => 'Not Requested',
            'dates' => [['date_applied' => '2027-03-01', 'hours' => 8, 'leave_type_id' => $this->leaveTypeId]],
            'applicant_signature' => $this->sampleSignature,
        ]);
        $appId = is_array($appId) ? ($appId['id'] ?? reset($appId)) : $appId;

        $service = app(LeaveApprovalService::class);
        $service->updateApprovalStatus($appId, 'SH', null, 'approved', [], $this->sampleSignature);
        $service->updateApprovalStatus($appId, 'DH', '  ', 'rejected');

        $this->assertEquals('Rejected', $this->appStatus($appId));
        $this->assertEquals(10.0, $this->balance(), 'DH rejection gives the credit back');

        return $appId;
    }

    /** @test */
    public function nobody_can_overturn_a_division_head_rejection(): void
    {
        $appId = $this->fileApproveByShAndRejectByDh();

        foreach ([false, true] as $confirmed) {
            try {
                app(LeaveApprovalService::class)->updateApprovalStatus($appId, 'HR', null, 'approved', [], null, $confirmed);
                $this->fail('Expected HR approval of a DH-rejected leave to be refused');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('final', $e->getMessage());
            }
        }

        $this->assertEquals('Rejected', $this->appStatus($appId));
        $this->assertEquals('pending', $this->stepStatus($appId, 'HR'));
        $this->assertEquals(10.0, $this->balance());
    }

    /** @test */
    public function hr_can_acknowledge_a_dh_rejected_leave_without_changing_its_outcome(): void
    {
        $appId = $this->fileApproveByShAndRejectByDh();

        app(LeaveApprovalService::class)->updateApprovalStatus($appId, 'HR', 'Noted', 'acknowledged');

        $this->assertEquals('acknowledged', $this->stepStatus($appId, 'HR'));
        $this->assertEquals('rejected', $this->stepStatus($appId, 'DH'), 'DH rejection stays as is');
        $this->assertEquals('Rejected', $this->appStatus($appId), 'the leave stays Rejected');
        $this->assertEquals(10.0, $this->balance(), 'credits stay returned');
    }

    /** @test */
    public function acknowledging_repairs_a_dh_rejected_leave_that_was_left_pending_with_credits_deducted(): void
    {
        // Left over from before DH rejections were final: HR overturned, then undid, leaving the
        // leave Pending with its credit deducted although the DH rejected it.
        $appId = $this->fileApproveByShAndRejectByDh();
        DB::table('hris.leave_applications')->where('id', $appId)->update(['status' => 'Pending']);
        DB::table('hris.leave_credits')->where('user_id', $this->employeeId)->where('leave_id', $this->leaveTypeId)->decrement('based_credit', 1);
        $this->assertEquals(9.0, $this->balance());

        // Still can't be approved
        try {
            app(LeaveApprovalService::class)->updateApprovalStatus($appId, 'HR', null, 'approved', [], null, true);
            $this->fail('Expected the approval to be refused');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('final', $e->getMessage());
        }

        app(LeaveApprovalService::class)->updateApprovalStatus($appId, 'HR', 'Noted', 'acknowledged');

        $this->assertEquals('acknowledged', $this->stepStatus($appId, 'HR'));
        $this->assertEquals('Rejected', $this->appStatus($appId), 'the leave is put back to Rejected');
        $this->assertEquals(10.0, $this->balance(), 'the credit is returned');
    }

    private function stepRemarks(int $appId, string $role): ?string
    {
        return DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $appId)->where('role', $role)->value('remarks');
    }

    /** @test */
    public function blank_remarks_default_to_the_action_taken(): void
    {
        $appId = $this->fileApproveByShAndRejectByDh();   // SH approved with no remarks, DH rejected with '  '
        $service = app(LeaveApprovalService::class);

        $this->assertSame('Approved', $this->stepRemarks($appId, 'SH'));
        $this->assertSame('Rejected', $this->stepRemarks($appId, 'DH'));

        $service->updateApprovalStatus($appId, 'HR', "  \n ", 'acknowledged');
        $this->assertSame('Acknowledged', $this->stepRemarks($appId, 'HR'));
    }

    /** @test */
    public function typed_remarks_are_kept(): void
    {
        $appId = $this->fileAndRejectBySh();   // SH rejected with 'Not justified'
        $this->assertSame('Not justified', $this->stepRemarks($appId, 'SH'));

        app(LeaveApprovalService::class)->updateApprovalStatus($appId, 'DH', ' Approved anyway ', 'approved', [], $this->sampleSignature, true);
        $this->assertSame('Approved anyway', $this->stepRemarks($appId, 'DH'));
    }

    /** @test */
    public function acknowledging_is_only_for_hr_on_a_dh_rejected_leave(): void
    {
        $appId = $this->fileAndRejectBySh();   // SH-rejected, not DH-rejected

        try {
            app(LeaveApprovalService::class)->updateApprovalStatus($appId, 'DH', null, 'acknowledged');
            $this->fail('Expected acknowledge to be refused');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('acknowledge', $e->getMessage());
        }
        $this->assertEquals('pending', $this->stepStatus($appId, 'DH'));
    }

    /** @test */
    public function dh_cannot_reject_a_leave_the_sh_already_rejected(): void
    {
        $appId = $this->fileAndRejectBySh();

        $this->expectException(\RuntimeException::class);
        app(LeaveApprovalService::class)->updateApprovalStatus($appId, 'DH', 'Also no', 'rejected');
    }

    /** @test */
    public function a_pending_prior_step_still_blocks_a_later_approver(): void
    {
        Auth::shouldReceive('id')->andReturn($this->employeeId);
        Auth::shouldReceive('user')->andReturn((object) ['id' => $this->employeeId, 'section' => $this->sectionId, 'division' => $this->divisionId]);
        Auth::shouldReceive('check')->andReturn(true);

        $appId = app(LeaveApplicationService::class)->insertLeaveApplication([
            'commutation' => 'Not Requested',
            'dates' => [['date_applied' => '2027-03-01', 'hours' => 8, 'leave_type_id' => $this->leaveTypeId]],
            'applicant_signature' => $this->sampleSignature,
        ]);
        $appId = is_array($appId) ? ($appId['id'] ?? reset($appId)) : $appId;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('prior approval step must be completed');
        app(LeaveApprovalService::class)->updateApprovalStatus($appId, 'DH', null, 'approved', [], $this->sampleSignature, true);
    }
}
