<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OvertimeApprovalTest extends TestCase
{
    use DatabaseTransactions;

    private int $employeeId = 9001;
    private int $shUserId   = 9002;
    private int $dhUserId   = 9003;
    private int $hrUserId   = 15;
    private int $sectionId  = 801;
    private int $divisionId = 901;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tdh_user.users')->insertOrIgnore([
            ['id' => $this->shUserId,   'fname' => 'SH',   'lname' => 'User',   'section' => $this->sectionId,  'division' => $this->divisionId, 'username' => 'sh_test',   'password' => 'x'],
            ['id' => $this->dhUserId,   'fname' => 'DH',   'lname' => 'User',   'section' => $this->sectionId,  'division' => $this->divisionId, 'username' => 'dh_test',   'password' => 'x'],
            ['id' => $this->employeeId, 'fname' => 'Emp',  'lname' => 'Test',   'section' => $this->sectionId,  'division' => $this->divisionId, 'username' => 'emp_test',  'password' => 'x'],
        ]);

        DB::table('tdh_user.division')->insertOrIgnore([
            ['id' => $this->divisionId, 'description' => 'TestDivision', 'head' => $this->dhUserId, 'code' => 'TDIV'],
        ]);

        DB::table('tdh_user.section')->insertOrIgnore([
            ['id' => $this->sectionId, 'description' => 'TestSection', 'head' => $this->shUserId, 'division' => $this->divisionId, 'code' => 'TSEC'],
        ]);
    }

    private function actAsEmployee(): void
    {
        Auth::shouldReceive('id')->andReturn($this->employeeId);
        Auth::shouldReceive('user')->andReturn((object)[
            'id'       => $this->employeeId,
            'section'  => $this->sectionId,
            'division' => $this->divisionId,
        ]);
        Auth::shouldReceive('check')->andReturn(true);
    }

    /** @test */
    public function filing_creates_three_approval_rows_with_correct_approver_ids()
    {
        $this->actAsEmployee();
        $service = app(\App\Services\OvertimeServices::class);

        $service->fileForSelf('2026-07-01', '17:00', '19:00', 'Test OT');

        $requestId = DB::table('hris.overtime_requests')
            ->where('userid', $this->employeeId)
            ->value('id');

        $approvals = DB::table('hris.overtime_request_approvals')
            ->where('overtime_request_id', $requestId)
            ->orderBy('order_no')
            ->get(['role', 'order_no', 'approver_id', 'status']);

        $this->assertCount(3, $approvals);

        $sh = $approvals->firstWhere('role', 'SH');
        $dh = $approvals->firstWhere('role', 'DH');
        $hr = $approvals->firstWhere('role', 'HR');

        $this->assertEquals($this->shUserId, $sh->approver_id);
        $this->assertEquals(1, $sh->order_no);
        $this->assertEquals('pending', $sh->status);

        $this->assertEquals($this->dhUserId, $dh->approver_id);
        $this->assertEquals(2, $dh->order_no);
        $this->assertEquals('pending', $dh->status);

        $this->assertEquals($this->hrUserId, $hr->approver_id);
        $this->assertEquals(3, $hr->order_no);
        $this->assertEquals('pending', $hr->status);
    }
}
