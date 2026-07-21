<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeaveHrComplianceStepTest extends TestCase
{
    use DatabaseTransactions;

    private int $employeeId = 9171;
    private int $shUserId   = 9172;
    private int $dhUserId   = 9173;
    private int $hrUserId   = 9174;
    private int $sectionId  = 9271;
    private int $divisionId = 9272;
    private int $leaveTypeId;

    private string $sampleSignature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->cleanup();

        DB::table('tdh_user.users')->insertOrIgnore([
            ['id' => $this->shUserId,   'fname' => 'Hrc', 'lname' => 'ShUser', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'hrc_sh_test', 'password' => 'x'],
            ['id' => $this->dhUserId,   'fname' => 'Hrc', 'lname' => 'DhUser', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'hrc_dh_test', 'password' => 'x'],
            ['id' => $this->employeeId, 'fname' => 'Hrc', 'lname' => 'EmpUser', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'hrc_emp_test', 'password' => 'x'],
            ['id' => $this->hrUserId,   'fname' => 'Hrc', 'lname' => 'HrUser', 'section' => 0, 'division' => 0, 'username' => 'hrc_hr_test', 'password' => 'x'],
        ]);
        DB::table('tdh_user.division')->insertOrIgnore([
            ['id' => $this->divisionId, 'description' => 'Hrc Test Division', 'head' => $this->dhUserId, 'code' => 'HRCDIV'],
        ]);
        DB::table('tdh_user.section')->insertOrIgnore([
            ['id' => $this->sectionId, 'description' => 'Hrc Test Section', 'head' => $this->shUserId, 'division' => $this->divisionId, 'code' => 'HRCSEC'],
        ]);

        DB::table('hris.leave_types')->updateOrInsert(
            ['code' => 'HRCTEST'],
            ['description' => 'Hrc Test Leave', 'monthly_earned' => 0, 'status' => 'active']
        );
        $this->leaveTypeId = DB::table('hris.leave_types')->where('code', 'HRCTEST')->value('id');

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
        DB::table('hris.leave_application_attachments')->whereIn('leave_application_id', $appIds)->delete();
        DB::table('hris.leave_date_applied')->whereIn('leave_application_id', $appIds)->delete();
        DB::table('hris.leave_application_approvals')->whereIn('leave_application_id', $appIds)->delete();
        DB::table('hris.leave_applications')->whereIn('id', $appIds)->delete();
        DB::table('hris.leave_credits')->where('user_id', $this->employeeId)->delete();
    }

    private function actAsEmployee(): void
    {
        Auth::shouldReceive('id')->andReturn($this->employeeId);
        Auth::shouldReceive('user')->andReturn((object) [
            'id' => $this->employeeId, 'section' => $this->sectionId, 'division' => $this->divisionId,
        ]);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'employee', 'is_section_head' => false, 'is_division_head' => false]);
    }

    private function actAsSh(): void
    {
        Auth::shouldReceive('id')->andReturn($this->shUserId);
        Auth::shouldReceive('user')->andReturn((object) [
            'id' => $this->shUserId, 'section' => $this->sectionId, 'division' => $this->divisionId,
        ]);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'employee', 'is_section_head' => true, 'is_division_head' => false]);
    }

    private function actAsHr(): void
    {
        Auth::shouldReceive('id')->andReturn($this->hrUserId);
        Auth::shouldReceive('user')->andReturn((object) [
            'id' => $this->hrUserId, 'section' => 0, 'division' => 0,
        ]);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'hr', 'is_section_head' => false, 'is_division_head' => false]);
    }

    private function fileWithAttachment(): int
    {
        $this->actAsEmployee();
        $payload = [
            'data' => json_encode([
                'commutation' => 'Not Requested',
                'dates' => [
                    ['date_applied' => '2027-03-01', 'hours' => 8, 'leave_type_id' => $this->leaveTypeId],
                ],
                'applicant_signature' => $this->sampleSignature,
            ]),
            'attachments' => [UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf')],
        ];

        $response = $this->withoutMiddleware()->post('/insert_leave_applications', $payload);
        $response->assertOk();

        return DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->value('id');
    }

    /** @test */
    public function filing_with_an_attachment_creates_a_pending_hr_compliance_step_first(): void
    {
        $appId = $this->fileWithAttachment();

        $roles = DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $appId)
            ->orderBy('order_no')
            ->pluck('role')
            ->toArray();

        $this->assertEquals(['HR_COMPLIANCE', 'SH', 'DH', 'HR'], $roles);

        $complianceOrderNo = DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $appId)->where('role', 'HR_COMPLIANCE')->value('order_no');
        $this->assertEquals(1, $complianceOrderNo);
    }

    /** @test */
    public function sh_cannot_act_before_hr_compliance_approves(): void
    {
        $appId = $this->fileWithAttachment();

        $this->actAsSh();
        $response = $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $appId, 'status' => 'Approved', 'remarks' => 'ok', 'signature' => $this->sampleSignature,
        ]);

        $response->assertStatus(500);
        $this->assertEquals('pending', DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $appId)->where('role', 'SH')->value('status'));
    }

    /** @test */
    public function hr_approving_compliance_lets_sh_act_next(): void
    {
        $appId = $this->fileWithAttachment();

        $this->actAsHr();
        $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $appId, 'status' => 'Approved', 'remarks' => 'Compliant',
        ])->assertOk();

        $this->assertEquals('approved', DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $appId)->where('role', 'HR_COMPLIANCE')->value('status'));

        $this->actAsSh();
        $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $appId, 'status' => 'Approved', 'remarks' => 'ok', 'signature' => $this->sampleSignature,
        ])->assertOk();

        $this->assertEquals('approved', DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $appId)->where('role', 'SH')->value('status'));
    }

    /** @test */
    public function hr_rejecting_compliance_rejects_the_whole_application_and_restores_credits(): void
    {
        $appId = $this->fileWithAttachment();

        $before = DB::table('hris.leave_credits')->where('user_id', $this->employeeId)->where('leave_id', $this->leaveTypeId)->value('based_credit');

        $this->actAsHr();
        $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $appId, 'status' => 'Rejected', 'remarks' => 'Attachment unreadable',
        ])->assertOk();

        $this->assertEquals('Rejected', DB::table('hris.leave_applications')->where('id', $appId)->value('status'));

        $after = DB::table('hris.leave_credits')->where('user_id', $this->employeeId)->where('leave_id', $this->leaveTypeId)->value('based_credit');
        $this->assertGreaterThan($before, $after);
    }

    /** @test */
    public function applications_without_attachments_keep_the_three_step_chain(): void
    {
        $this->actAsEmployee();
        $payload = [
            'data' => json_encode([
                'commutation' => 'Not Requested',
                'dates' => [
                    ['date_applied' => '2027-03-05', 'hours' => 8, 'leave_type_id' => $this->leaveTypeId],
                ],
                'applicant_signature' => $this->sampleSignature,
            ]),
        ];
        $this->withoutMiddleware()->post('/insert_leave_applications', $payload)->assertOk();
        $appId = DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->value('id');

        $roles = DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $appId)->orderBy('order_no')->pluck('role')->toArray();

        $this->assertEquals(['SH', 'DH', 'HR'], $roles);
    }
}
