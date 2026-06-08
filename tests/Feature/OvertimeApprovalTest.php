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

    /** @test */
    public function award_cto_credits_calculates_correctly_for_90_minutes()
    {
        // 90 min OT = 1 full hour (0.125) + 30 remaining (0.063) = 0.188 credits
        $service = app(\App\Services\OvertimeServices::class);

        $method = new \ReflectionMethod($service, 'awardCtoCredits');
        $method->setAccessible(true);
        $method->invoke($service, $this->employeeId, '17:00:00', '18:30:00');

        $ctoLeaveId = DB::table('hris.leave_types')->where('code', 'CTO')->value('id');
        $credit = DB::table('hris.leave_credits')
            ->where('user_id', $this->employeeId)
            ->where('leave_id', $ctoLeaveId)
            ->first();

        $this->assertNotNull($credit);
        $this->assertEquals(0.188, round((float) $credit->based_credit, 3));
    }

    /** @test */
    public function award_cto_credits_increments_existing_balance()
    {
        $ctoLeaveId = DB::table('hris.leave_types')->where('code', 'CTO')->value('id');

        DB::table('hris.leave_credits')->insert([
            'user_id'      => $this->employeeId,
            'leave_id'     => $ctoLeaveId,
            'based_credit' => 1.000,
            'used_credit'  => 0,
        ]);

        $service = app(\App\Services\OvertimeServices::class);
        $method = new \ReflectionMethod($service, 'awardCtoCredits');
        $method->setAccessible(true);
        // 60 min = 0.125 credits
        $method->invoke($service, $this->employeeId, '17:00:00', '18:00:00');

        $credit = DB::table('hris.leave_credits')
            ->where('user_id', $this->employeeId)
            ->where('leave_id', $ctoLeaveId)
            ->first();

        $this->assertEquals(1.125, round((float) $credit->based_credit, 3));
    }

    // -------------------------------------------------------------------------
    // Helpers for multi-level approval tests
    // -------------------------------------------------------------------------

    private function seedFiling(string $timeFrom = '17:00:00', string $timeTo = '19:00:00'): int
    {
        $requestId = DB::table('hris.overtime_requests')->insertGetId([
            'userid'     => $this->employeeId,
            'ot_date'    => '2026-07-01',
            'time_from'  => $timeFrom,
            'time_to'    => $timeTo,
            'reason'     => 'Test',
            'status'     => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('hris.overtime_request_approvals')->insert([
            ['overtime_request_id' => $requestId, 'approver_id' => $this->shUserId, 'role' => 'SH', 'order_no' => 1, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
            ['overtime_request_id' => $requestId, 'approver_id' => $this->dhUserId, 'role' => 'DH', 'order_no' => 2, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
            ['overtime_request_id' => $requestId, 'approver_id' => $this->hrUserId, 'role' => 'HR', 'order_no' => 3, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
        ]);

        return $requestId;
    }

    private function actAs(int $userId, bool $isAdmin = false): void
    {
        Auth::shouldReceive('id')->andReturn($userId);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => $isAdmin ? 'admin' : 'employee']);
    }

    // -------------------------------------------------------------------------
    // Multi-level approval tests
    // -------------------------------------------------------------------------

    /** @test */
    public function sh_approval_marks_sh_row_approved_and_filing_stays_pending()
    {
        $requestId = $this->seedFiling();
        $this->actAs($this->shUserId);

        $service = app(\App\Services\OvertimeServices::class);
        $service->reviewFiling($requestId, 'approved', 'Looks good');

        $shRow = DB::table('hris.overtime_request_approvals')
            ->where('overtime_request_id', $requestId)->where('role', 'SH')->first();
        $this->assertEquals('approved', $shRow->status);
        $this->assertNotNull($shRow->approved_at);

        $filing = DB::table('hris.overtime_requests')->where('id', $requestId)->first();
        $this->assertEquals('pending', $filing->status);
    }

    /** @test */
    public function rejection_at_sh_level_closes_filing_immediately()
    {
        $requestId = $this->seedFiling();
        $this->actAs($this->shUserId);

        $service = app(\App\Services\OvertimeServices::class);
        $service->reviewFiling($requestId, 'rejected', 'Not justified');

        $shRow = DB::table('hris.overtime_request_approvals')
            ->where('overtime_request_id', $requestId)->where('role', 'SH')->first();
        $this->assertEquals('rejected', $shRow->status);

        $filing = DB::table('hris.overtime_requests')->where('id', $requestId)->first();
        $this->assertEquals('rejected', $filing->status);

        $ctoLeaveId = DB::table('hris.leave_types')->where('code', 'CTO')->value('id');
        $credits = DB::table('hris.leave_credits')
            ->where('user_id', $this->employeeId)->where('leave_id', $ctoLeaveId)->first();
        $this->assertNull($credits);
    }

    /** @test */
    public function full_chain_approval_awards_cto_credits_and_closes_filing()
    {
        // 60 min OT = 0.125 credits
        $requestId = $this->seedFiling('17:00:00', '18:00:00');
        $service = app(\App\Services\OvertimeServices::class);

        // SH approves — set up Auth mock with all three return values in sequence
        Auth::shouldReceive('id')->andReturnValues([$this->shUserId, $this->dhUserId, $this->hrUserId]);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'employee']);

        $service->reviewFiling($requestId, 'approved');
        $service->reviewFiling($requestId, 'approved');
        $service->reviewFiling($requestId, 'approved');

        $filing = DB::table('hris.overtime_requests')->where('id', $requestId)->first();
        $this->assertEquals('approved', $filing->status);
        $this->assertEquals($this->hrUserId, (int) $filing->reviewed_by);

        $ctoLeaveId = DB::table('hris.leave_types')->where('code', 'CTO')->value('id');
        $credit = DB::table('hris.leave_credits')
            ->where('user_id', $this->employeeId)->where('leave_id', $ctoLeaveId)->first();
        $this->assertNotNull($credit);
        $this->assertEquals(0.125, round((float) $credit->based_credit, 3));
    }

    /** @test */
    public function dh_cannot_approve_before_sh_approves()
    {
        $requestId = $this->seedFiling();
        $this->actAs($this->dhUserId);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $service = app(\App\Services\OvertimeServices::class);
        $service->reviewFiling($requestId, 'approved');
    }
}
