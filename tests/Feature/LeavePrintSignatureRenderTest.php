<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeavePrintSignatureRenderTest extends TestCase
{
    private int $employeeId = 9181;
    private int $shUserId   = 9182;
    private int $sectionId  = 9281;
    private int $divisionId = 9282;
    private int $leaveTypeId;
    private int $designationId;

    private string $applicantSignature = 'data:image/png;base64,APPLICANTSAMPLE==';
    private string $shSignature = 'data:image/png;base64,SHSAMPLE==';

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanup();

        $this->designationId = DB::table('tdh_user.designation')->value('id');

        DB::table('tdh_user.users')->insertOrIgnore([
            ['id' => $this->shUserId,   'fname' => 'Print', 'lname' => 'ShUser',  'section' => $this->sectionId, 'division' => $this->divisionId, 'designation' => $this->designationId, 'username' => 'print_sh_test',  'password' => 'x'],
            ['id' => $this->employeeId, 'fname' => 'Print', 'lname' => 'EmpUser', 'section' => $this->sectionId, 'division' => $this->divisionId, 'designation' => $this->designationId, 'username' => 'print_emp_test', 'password' => 'x'],
        ]);
        DB::table('tdh_user.division')->insertOrIgnore([
            ['id' => $this->divisionId, 'description' => 'Print Test Division', 'head' => $this->shUserId, 'code' => 'PRTDIV'],
        ]);
        DB::table('tdh_user.section')->insertOrIgnore([
            ['id' => $this->sectionId, 'description' => 'Print Test Section', 'head' => $this->shUserId, 'division' => $this->divisionId, 'code' => 'PRTSEC'],
        ]);

        DB::table('hris.leave_types')->updateOrInsert(
            ['code' => 'PRTTEST'],
            ['description' => 'Print Test Leave', 'monthly_earned' => 0, 'status' => 'active']
        );
        $this->leaveTypeId = DB::table('hris.leave_types')->where('code', 'PRTTEST')->value('id');
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
    }

    private function seedApplication(?string $applicantSignature, ?string $shStatus, ?string $shSignature): int
    {
        $appId = DB::table('hris.leave_applications')->insertGetId([
            'user_id' => $this->employeeId,
            'status' => 'pending',
            'commutation' => 'Not Requested',
            'applicant_signature' => $applicantSignature,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('hris.leave_date_applied')->insert([
            'leave_application_id' => $appId,
            'leave_type_id' => $this->leaveTypeId,
            'date_applied' => '2027-04-01',
            'hours' => 8,
            'status' => 'active',
            'credits_deducted' => 1.000,
        ]);

        DB::table('hris.leave_application_approvals')->insert([
            ['leave_application_id' => $appId, 'approver_id' => $this->sectionId,  'role' => 'SH', 'order_no' => 1, 'status' => $shStatus ?? 'pending', 'signature' => $shSignature, 'created_at' => now(), 'updated_at' => now()],
            ['leave_application_id' => $appId, 'approver_id' => $this->divisionId, 'role' => 'DH', 'order_no' => 2, 'status' => 'pending', 'signature' => null, 'created_at' => now(), 'updated_at' => now()],
            ['leave_application_id' => $appId, 'approver_id' => 15,                'role' => 'HR', 'order_no' => 3, 'status' => 'pending', 'signature' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        return $appId;
    }

    private function loginAsEmployee(): void
    {
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);
        Auth::loginUsingId($this->employeeId);
    }

    /** @test */
    public function a_stored_applicant_signature_renders_as_an_image_on_print(): void
    {
        $this->loginAsEmployee();
        $appId = $this->seedApplication($this->applicantSignature, null, null);

        $response = $this->get('/print_leave?id=' . $appId);

        $response->assertOk();
        $response->assertSee('<img src="' . $this->applicantSignature . '" alt="Signature of Applicant" class="signature-img">', false);
    }

    /** @test */
    public function a_stored_sh_signature_renders_on_print_when_sh_has_approved(): void
    {
        $this->loginAsEmployee();
        $appId = $this->seedApplication($this->applicantSignature, 'approved', $this->shSignature);

        $response = $this->get('/print_leave?id=' . $appId);

        $response->assertOk();
        $response->assertSee($this->shSignature, false);
    }

    /** @test */
    public function a_leave_application_with_no_stored_signatures_prints_blank_lines_without_erroring(): void
    {
        $this->loginAsEmployee();
        $appId = $this->seedApplication(null, null, null);

        $response = $this->get('/print_leave?id=' . $appId);

        $response->assertOk();
        $response->assertSee('(Signature of Applicant)', false);
        $response->assertDontSee('<img src="" alt="Signature of Applicant"', false);
    }

    /** @test */
    public function undoing_an_sh_approval_removes_the_signature_from_the_printed_form(): void
    {
        $this->loginAsEmployee();
        $appId = $this->seedApplication($this->applicantSignature, 'approved', $this->shSignature);

        // Confirm the signature prints while the approval is still valid.
        $this->get('/print_leave?id=' . $appId)->assertSee($this->shSignature, false);

        // Undo directly at the data layer (equivalent to what
        // LeaveApprovalService::undoApproval does) to isolate what this
        // test is actually checking: the print view must not display a
        // signature for an approval step that's no longer approved.
        DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $appId)
            ->where('role', 'SH')
            ->update(['status' => 'pending', 'approved_at' => null, 'signature' => null]);

        $response = $this->get('/print_leave?id=' . $appId);
        $response->assertOk();
        $response->assertDontSee($this->shSignature, false);
    }
}
