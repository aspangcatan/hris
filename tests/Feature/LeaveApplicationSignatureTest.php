<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeaveApplicationSignatureTest extends TestCase
{
    use DatabaseTransactions;
    use \Tests\Concerns\SwitchesAuthUser;

    private int $employeeId = 9161;
    private int $shUserId   = 9162;
    private int $dhUserId   = 9163;
    private int $sectionId  = 9261;
    private int $divisionId = 9262;
    private int $leaveTypeId;

    private string $sampleSignature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanup();

        DB::table('tdh_user.users')->insertOrIgnore([
            ['id' => $this->shUserId,   'fname' => 'Sig',  'lname' => 'ShUser', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'sig_sh_test',  'password' => 'x'],
            ['id' => $this->dhUserId,   'fname' => 'Sig',  'lname' => 'DhUser', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'sig_dh_test',  'password' => 'x'],
            ['id' => $this->employeeId, 'fname' => 'Sig',  'lname' => 'EmpUser', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'sig_emp_test', 'password' => 'x'],
        ]);
        DB::table('tdh_user.division')->insertOrIgnore([
            ['id' => $this->divisionId, 'description' => 'Sig Test Division', 'head' => $this->dhUserId, 'code' => 'SIGDIV'],
        ]);
        DB::table('tdh_user.section')->insertOrIgnore([
            ['id' => $this->sectionId, 'description' => 'Sig Test Section', 'head' => $this->shUserId, 'division' => $this->divisionId, 'code' => 'SIGSEC'],
        ]);

        DB::table('hris.leave_types')->updateOrInsert(
            ['code' => 'SIGTEST'],
            ['description' => 'Sig Test Leave', 'monthly_earned' => 0, 'status' => 'active']
        );
        $this->leaveTypeId = DB::table('hris.leave_types')->where('code', 'SIGTEST')->value('id');

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
        DB::table('hris.leave_date_applied')->whereIn('leave_application_id', $appIds)->delete();
        DB::table('hris.leave_application_approvals')->whereIn('leave_application_id', $appIds)->delete();
        DB::table('hris.leave_applications')->whereIn('id', $appIds)->delete();
        DB::table('hris.leave_credits')->where('user_id', $this->employeeId)->delete();
    }

    private function actAsEmployee(): void
    {
        $this->actAsUser((object) [
            'id' => $this->employeeId, 'section' => $this->sectionId, 'division' => $this->divisionId,
        ]);
        session(['access_rights' => 'employee', 'is_section_head' => false, 'is_division_head' => false]);
    }

    private function actAsSh(): void
    {
        $this->actAsUser((object) [
            'id' => $this->shUserId, 'section' => $this->sectionId, 'division' => $this->divisionId,
        ]);
        session(['access_rights' => 'employee', 'is_section_head' => true, 'is_division_head' => false]);
    }

    private function actAsDh(): void
    {
        $this->actAsUser((object) [
            'id' => $this->dhUserId, 'section' => $this->sectionId, 'division' => $this->divisionId,
        ]);
        session(['access_rights' => 'employee', 'is_section_head' => false, 'is_division_head' => true]);
    }

    private function filePayload(): array
    {
        return [
            'data' => json_encode([
                'commutation' => 'Not Requested',
                'dates' => [
                    ['date_applied' => '2027-02-01', 'hours' => 8, 'leave_type_id' => $this->leaveTypeId],
                ],
                'applicant_signature' => $this->sampleSignature,
            ]),
        ];
    }

    /** @test */
    public function filing_without_a_signature_is_rejected(): void
    {
        $this->actAsEmployee();

        $payload = json_decode($this->filePayload()['data'], true);
        unset($payload['applicant_signature']);

        $response = $this->withoutMiddleware()->post('/insert_leave_applications', ['data' => json_encode($payload)]);

        $response->assertStatus(422);
        $this->assertEquals(0, DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->count());
    }

    /** @test */
    public function filing_with_a_signature_stores_it(): void
    {
        $this->actAsEmployee();

        $response = $this->withoutMiddleware()->post('/insert_leave_applications', $this->filePayload());

        $response->assertOk();
        $appId = DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->value('id');
        $this->assertNotNull($appId);
        $this->assertEquals($this->sampleSignature, DB::table('hris.leave_applications')->where('id', $appId)->value('applicant_signature'));
    }

    /** @test */
    public function editing_a_pending_application_requires_and_overwrites_the_signature(): void
    {
        $this->actAsEmployee();
        $this->withoutMiddleware()->post('/insert_leave_applications', $this->filePayload())->assertOk();
        $appId = DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->value('id');

        $newSignature = 'data:image/png;base64,ANOTHERONE==';
        $updateWithoutSignature = [
            'commutation' => 'Not Requested',
            'dates' => [
                ['date_applied' => '2027-02-02', 'hours' => 8, 'leave_type_id' => $this->leaveTypeId],
            ],
        ];

        $this->withoutMiddleware()->putJson("/leave-applications/{$appId}", $updateWithoutSignature)
            ->assertStatus(422);

        $updateWithSignature = $updateWithoutSignature + ['applicant_signature' => $newSignature];
        $this->withoutMiddleware()->putJson("/leave-applications/{$appId}", $updateWithSignature)
            ->assertOk();

        $this->assertEquals($newSignature, DB::table('hris.leave_applications')->where('id', $appId)->value('applicant_signature'));
    }

    /** @test */
    public function sh_approving_without_a_signature_is_rejected(): void
    {
        $this->actAsEmployee();
        $this->withoutMiddleware()->post('/insert_leave_applications', $this->filePayload())->assertOk();
        $appId = DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->value('id');

        $this->actAsSh();
        $response = $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $appId,
            'status' => 'Approved',
            'remarks' => 'Fine',
        ]);

        $response->assertStatus(500);
        $this->assertNull(DB::table('hris.leave_application_approvals')->where('leave_application_id', $appId)->where('role', 'SH')->value('signature'));
    }

    /** @test */
    public function sh_approving_with_a_signature_stores_it_on_the_sh_row_only(): void
    {
        $this->actAsEmployee();
        $this->withoutMiddleware()->post('/insert_leave_applications', $this->filePayload())->assertOk();
        $appId = DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->value('id');

        $this->actAsSh();
        $shSignature = 'data:image/png;base64,SHSIGNATURE==';
        $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $appId,
            'status' => 'Approved',
            'remarks' => 'Fine',
            'signature' => $shSignature,
        ])->assertOk();

        $this->assertEquals($shSignature, DB::table('hris.leave_application_approvals')->where('leave_application_id', $appId)->where('role', 'SH')->value('signature'));
        $this->assertNull(DB::table('hris.leave_application_approvals')->where('leave_application_id', $appId)->where('role', 'DH')->value('signature'));
    }

    /** @test */
    public function rejecting_requires_no_signature(): void
    {
        $this->actAsEmployee();
        $this->withoutMiddleware()->post('/insert_leave_applications', $this->filePayload())->assertOk();
        $appId = DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->value('id');

        $this->actAsSh();
        $response = $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $appId,
            'status' => 'Rejected',
            'remarks' => 'No good',
        ]);

        $response->assertOk();
    }

    /** @test */
    public function hr_approving_requires_no_signature_and_never_stores_one(): void
    {
        $this->actAsEmployee();
        $this->withoutMiddleware()->post('/insert_leave_applications', $this->filePayload())->assertOk();
        $appId = DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->value('id');

        $this->actAsSh();
        $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $appId, 'status' => 'Approved', 'remarks' => 'ok', 'signature' => 'data:image/png;base64,SH==',
        ])->assertOk();

        $this->actAsDh();
        $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $appId, 'status' => 'Approved', 'remarks' => 'ok', 'signature' => 'data:image/png;base64,DH==',
        ])->assertOk();

        $this->actAsUser((object) ['id' => 9999, 'section' => 0, 'division' => 0]);
        session(['access_rights' => 'hr']);

        $response = $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $appId, 'status' => 'Approved', 'remarks' => 'ok',
        ]);

        $response->assertOk();
        $this->assertNull(DB::table('hris.leave_application_approvals')->where('leave_application_id', $appId)->where('role', 'HR')->value('signature'));
    }

    /** @test */
    public function undoing_an_approval_clears_the_stored_signature(): void
    {
        $this->actAsEmployee();
        $this->withoutMiddleware()->post('/insert_leave_applications', $this->filePayload())->assertOk();
        $appId = DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->value('id');

        $this->actAsSh();
        $shSignature = 'data:image/png;base64,SHSIGNATURE==';
        $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $appId,
            'status' => 'Approved',
            'remarks' => 'Fine',
            'signature' => $shSignature,
        ])->assertOk();

        $this->assertEquals($shSignature, DB::table('hris.leave_application_approvals')->where('leave_application_id', $appId)->where('role', 'SH')->value('signature'));

        $this->withoutMiddleware()->postJson('/undo_approval', ['id' => $appId])->assertOk();

        // A stale signature must not survive an undo - a printed form must
        // never show a signature for an approval step that's no longer valid.
        $this->assertNull(DB::table('hris.leave_application_approvals')->where('leave_application_id', $appId)->where('role', 'SH')->value('signature'));
        $this->assertEquals('pending', DB::table('hris.leave_application_approvals')->where('leave_application_id', $appId)->where('role', 'SH')->value('status'));
    }
}
