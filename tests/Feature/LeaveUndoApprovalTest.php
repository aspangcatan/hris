<?php

namespace Tests\Feature;

use App\Services\LeaveApprovalService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeaveUndoApprovalTest extends TestCase
{
    use \Tests\Concerns\SwitchesAuthUser;

    private int $employeeId = 9191;
    private int $shUserId   = 9192;
    private int $dhUserId   = 9193;
    private int $sectionId  = 9291;
    private int $divisionId = 9292;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanup();

        // Approvers are matched by who actually heads the section/division
        DB::table('tdh_user.division')->insert(['id' => $this->divisionId, 'description' => 'Undo Test Division', 'head' => $this->dhUserId, 'code' => 'UNDODIV']);
        DB::table('tdh_user.section')->insert(['id' => $this->sectionId, 'description' => 'Undo Test Section', 'head' => $this->shUserId, 'division' => $this->divisionId, 'code' => 'UNDOSEC']);
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        $appIds = DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->pluck('id');
        DB::table('hris.leave_date_applied')->whereIn('leave_application_id', $appIds)->delete();
        DB::table('hris.leave_application_approvals')->whereIn('leave_application_id', $appIds)->delete();
        DB::table('hris.leave_applications')->whereIn('id', $appIds)->delete();
        DB::table('tdh_user.section')->where('id', $this->sectionId)->delete();
        DB::table('tdh_user.division')->where('id', $this->divisionId)->delete();
    }

    /** Seeds an application whose SH/DH/HR steps have the given statuses. */
    private function seedApplication(string $appStatus, string $sh, string $dh, string $hr): int
    {
        $appId = DB::table('hris.leave_applications')->insertGetId([
            'user_id' => $this->employeeId, 'status' => $appStatus, 'commutation' => 'Not Requested',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $step = fn($role, $order, $approver, $status) => [
            'leave_application_id' => $appId, 'approver_id' => $approver, 'role' => $role, 'order_no' => $order,
            'status' => $status, 'approved_at' => $status === 'pending' ? null : now(),
            'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('hris.leave_application_approvals')->insert([
            $step('SH', 1, $this->sectionId, $sh),
            $step('DH', 2, $this->divisionId, $dh),
            $step('HR', 3, 15, $hr),
        ]);

        return $appId;
    }

    private function actAsSh(): void
    {
        $this->actAsUser((object) ['id' => $this->shUserId, 'section' => $this->sectionId, 'division' => $this->divisionId]);
        session(['access_rights' => 'employee', 'is_section_head' => true, 'is_division_head' => false]);
    }

    private function actAsDh(): void
    {
        $this->actAsUser((object) ['id' => $this->dhUserId, 'section' => $this->sectionId, 'division' => $this->divisionId]);
        session(['access_rights' => 'employee', 'is_section_head' => false, 'is_division_head' => true]);
    }

    private function stepStatus(int $appId, string $role): string
    {
        return DB::table('hris.leave_application_approvals')->where('leave_application_id', $appId)->where('role', $role)->value('status');
    }

    /** @test */
    public function an_approver_can_undo_when_only_pending_steps_follow(): void
    {
        $this->actAsSh();
        $appId = $this->seedApplication('Pending', 'approved', 'pending', 'pending');

        $service = app(LeaveApprovalService::class);
        $this->assertTrue($service->canUndoApproval($appId));

        $service->undoApproval($appId);
        $this->assertEquals('pending', $this->stepStatus($appId, 'SH'));
    }

    /** @test */
    public function undoing_an_approval_clears_its_remarks(): void
    {
        $this->actAsSh();
        $appId = $this->seedApplication('Pending', 'approved', 'pending', 'pending');
        DB::table('hris.leave_application_approvals')->where('leave_application_id', $appId)->where('role', 'SH')->update(['remarks' => 'Approved']);

        app(LeaveApprovalService::class)->undoApproval($appId);

        $this->assertNull(DB::table('hris.leave_application_approvals')->where('leave_application_id', $appId)->where('role', 'SH')->value('remarks'));
    }

    /** @test */
    public function hr_can_undo_an_acknowledgement(): void
    {
        $this->actAsUser((object) ['id' => $this->shUserId, 'section' => 0, 'division' => 0]);
        session(['access_rights' => 'HR', 'is_section_head' => false, 'is_division_head' => false]);

        $appId = $this->seedApplication('Approved', 'approved', 'approved', 'acknowledged');
        DB::table('hris.leave_application_approvals')->where('leave_application_id', $appId)->where('role', 'HR')->update(['remarks' => 'Acknowledged']);

        $service = app(LeaveApprovalService::class);
        $this->assertTrue($service->canUndoApproval($appId));

        $service->undoApproval($appId);

        $this->assertEquals('pending', $this->stepStatus($appId, 'HR'));
        $this->assertEquals('Pending', DB::table('hris.leave_applications')->where('id', $appId)->value('status'));
    }

    /** @test */
    public function an_approver_cannot_undo_after_a_later_step_approved(): void
    {
        $this->actAsSh();
        $appId = $this->seedApplication('Pending', 'approved', 'approved', 'pending');

        $this->assertFalse(app(LeaveApprovalService::class)->canUndoApproval($appId));
    }

    /** @test */
    public function sh_cannot_undo_after_the_dh_rejected_the_leave(): void
    {
        $this->actAsSh();
        $appId = $this->seedApplication('Rejected', 'approved', 'rejected', 'pending');

        $service = app(LeaveApprovalService::class);
        $this->assertFalse($service->canUndoApproval($appId));

        try {
            $service->undoApproval($appId);
            $this->fail('Expected the undo to be refused');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('later stage', $e->getMessage());
        }

        $this->assertEquals('approved', $this->stepStatus($appId, 'SH'), 'SH approval must stay in place');
        $this->assertEquals('rejected', $this->stepStatus($appId, 'DH'));
    }

    /** @test */
    public function dh_cannot_undo_after_hr_rejected_the_leave(): void
    {
        $this->actAsDh();
        $appId = $this->seedApplication('Rejected', 'approved', 'approved', 'rejected');

        $this->assertFalse(app(LeaveApprovalService::class)->canUndoApproval($appId));
    }
}
