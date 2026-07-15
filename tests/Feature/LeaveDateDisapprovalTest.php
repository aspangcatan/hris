<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeaveDateDisapprovalTest extends TestCase
{
    use DatabaseTransactions;

    private int $employeeId = 9151;
    private int $shUserId   = 9152;
    private int $dhUserId   = 9153;
    private int $sectionId  = 9251;
    private int $divisionId = 9252;
    private int $leaveTypeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanup();

        DB::table('tdh_user.users')->insertOrIgnore([
            ['id' => $this->shUserId,   'fname' => 'SH',  'lname' => 'User', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'lda_sh_test',  'password' => 'x'],
            ['id' => $this->dhUserId,   'fname' => 'DH',  'lname' => 'User', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'lda_dh_test',  'password' => 'x'],
            ['id' => $this->employeeId, 'fname' => 'Emp', 'lname' => 'Test', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'lda_emp_test', 'password' => 'x'],
        ]);
        DB::table('tdh_user.division')->insertOrIgnore([
            ['id' => $this->divisionId, 'description' => 'LDA Test Division', 'head' => $this->dhUserId, 'code' => 'LDADIV'],
        ]);
        DB::table('tdh_user.section')->insertOrIgnore([
            ['id' => $this->sectionId, 'description' => 'LDA Test Section', 'head' => $this->shUserId, 'division' => $this->divisionId, 'code' => 'LDASEC'],
        ]);

        DB::table('hris.leave_types')->updateOrInsert(
            ['code' => 'LDATEST'],
            ['description' => 'LDA Test Leave', 'monthly_earned' => 0, 'status' => 'active']
        );
        $this->leaveTypeId = DB::table('hris.leave_types')->where('code', 'LDATEST')->value('id');
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

    /**
     * Seeds a pending leave application for the test employee with 2 dates
     * (1.000 credit each), a starting leave_credits balance of
     * based_credit=5.000/used_credit=3.000, and the standard SH->DH->HR
     * approval chain (SH approver_id=section, DH approver_id=division,
     * HR approver_id=15), all pending.
     *
     * @return array{app_id: int, date_ids: \Illuminate\Support\Collection}
     */
    private function seedApplication(): array
    {
        $appId = DB::table('hris.leave_applications')->insertGetId([
            'user_id' => $this->employeeId,
            'status' => 'pending',
            'commutation' => 'Not Requested',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('hris.leave_date_applied')->insert([
            ['leave_application_id' => $appId, 'leave_type_id' => $this->leaveTypeId, 'date_applied' => '2027-01-04', 'hours' => 8, 'status' => 'active', 'credits_deducted' => 1.000],
            ['leave_application_id' => $appId, 'leave_type_id' => $this->leaveTypeId, 'date_applied' => '2027-01-05', 'hours' => 8, 'status' => 'active', 'credits_deducted' => 1.000],
        ]);
        $dateIds = DB::table('hris.leave_date_applied')->where('leave_application_id', $appId)->orderBy('date_applied')->pluck('id');

        DB::table('hris.leave_application_approvals')->insert([
            ['leave_application_id' => $appId, 'approver_id' => $this->sectionId,  'role' => 'SH', 'order_no' => 1, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
            ['leave_application_id' => $appId, 'approver_id' => $this->divisionId, 'role' => 'DH', 'order_no' => 2, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
            ['leave_application_id' => $appId, 'approver_id' => 15,                'role' => 'HR', 'order_no' => 3, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('hris.leave_credits')->insert([
            'user_id' => $this->employeeId,
            'leave_id' => $this->leaveTypeId,
            'based_credit' => 5.000,
            'used_credit' => 3.000,
            'last_updated_month' => '2027-1',
        ]);

        return ['app_id' => $appId, 'date_ids' => $dateIds];
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

    private function actAsDh(): void
    {
        Auth::shouldReceive('id')->andReturn($this->dhUserId);
        Auth::shouldReceive('user')->andReturn((object) [
            'id' => $this->dhUserId, 'section' => $this->sectionId, 'division' => $this->divisionId,
        ]);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'employee', 'is_section_head' => false, 'is_division_head' => true]);
    }

    private function creditBalance(): float
    {
        return (float) DB::table('hris.leave_credits')
            ->where('user_id', $this->employeeId)
            ->where('leave_id', $this->leaveTypeId)
            ->value('based_credit');
    }

    /** @test */
    public function sh_flagging_a_date_does_not_touch_credits_or_final_status()
    {
        $seed = $this->seedApplication();
        $this->actAsSh();

        $response = $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $seed['app_id'],
            'status' => 'Approved',
            'remarks' => 'Overall fine',
            'disapproved_dates' => [
                ['date_applied_id' => $seed['date_ids'][0], 'remark' => 'No coverage that day'],
            ],
        ]);
        $response->assertOk();

        $flagged = DB::table('hris.leave_date_applied')->where('id', $seed['date_ids'][0])->first();
        $this->assertEquals(1, (int) $flagged->sh_flagged_disapprove);
        $this->assertEquals('No coverage that day', $flagged->sh_disapproval_remark);
        $this->assertEquals(0, (int) $flagged->final_disapproved);

        $untouched = DB::table('hris.leave_date_applied')->where('id', $seed['date_ids'][1])->first();
        $this->assertEquals(0, (int) $untouched->sh_flagged_disapprove);

        $shRow = DB::table('hris.leave_application_approvals')->where('leave_application_id', $seed['app_id'])->where('role', 'SH')->first();
        $this->assertEquals('approved', $shRow->status);

        $this->assertEqualsWithDelta(5.000, $this->creditBalance(), 0.001);
    }

    /** @test */
    public function dh_upholding_an_sh_flagged_date_restores_its_credits()
    {
        $seed = $this->seedApplication();

        // SH flags date[0] first.
        DB::table('hris.leave_date_applied')->where('id', $seed['date_ids'][0])->update([
            'sh_flagged_disapprove' => 1, 'sh_disapproval_remark' => 'No coverage',
        ]);
        DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $seed['app_id'])->where('role', 'SH')
            ->update(['status' => 'approved', 'approved_at' => now()]);

        $this->actAsDh();
        $response = $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $seed['app_id'],
            'status' => 'Approved',
            'remarks' => 'Agreed',
            'disapproved_dates' => [
                ['date_applied_id' => $seed['date_ids'][0], 'remark' => 'Confirmed no coverage'],
            ],
        ]);
        $response->assertOk();

        $final = DB::table('hris.leave_date_applied')->where('id', $seed['date_ids'][0])->first();
        $this->assertEquals(1, (int) $final->final_disapproved);
        $this->assertEquals('Confirmed no coverage', $final->dh_disapproval_remark);

        // 5.000 + 1.000 restored credit for the disapproved date.
        $this->assertEqualsWithDelta(6.000, $this->creditBalance(), 0.001);
    }

    /** @test */
    public function dh_overriding_an_sh_flagged_date_back_to_approved_does_not_restore_credits()
    {
        $seed = $this->seedApplication();

        DB::table('hris.leave_date_applied')->where('id', $seed['date_ids'][0])->update([
            'sh_flagged_disapprove' => 1, 'sh_disapproval_remark' => 'No coverage',
        ]);
        DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $seed['app_id'])->where('role', 'SH')
            ->update(['status' => 'approved', 'approved_at' => now()]);

        $this->actAsDh();
        // DH submits with the date NOT in disapproved_dates - overriding SH's flag.
        $response = $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $seed['app_id'],
            'status' => 'Approved',
            'remarks' => 'Coverage was actually fine',
            'disapproved_dates' => [],
        ]);
        $response->assertOk();

        $final = DB::table('hris.leave_date_applied')->where('id', $seed['date_ids'][0])->first();
        $this->assertEquals(0, (int) $final->final_disapproved);

        $this->assertEqualsWithDelta(5.000, $this->creditBalance(), 0.001);
    }

    /** @test */
    public function dh_can_flag_a_new_date_sh_never_flagged()
    {
        $seed = $this->seedApplication();

        DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $seed['app_id'])->where('role', 'SH')
            ->update(['status' => 'approved', 'approved_at' => now()]);

        $this->actAsDh();
        $response = $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $seed['app_id'],
            'status' => 'Approved',
            'remarks' => 'Caught this one myself',
            'disapproved_dates' => [
                ['date_applied_id' => $seed['date_ids'][1], 'remark' => 'DH caught a conflict'],
            ],
        ]);
        $response->assertOk();

        $final = DB::table('hris.leave_date_applied')->where('id', $seed['date_ids'][1])->first();
        $this->assertEquals(1, (int) $final->final_disapproved);
        $this->assertEquals(0, (int) $final->sh_flagged_disapprove);

        $this->assertEqualsWithDelta(6.000, $this->creditBalance(), 0.001);
    }

    /** @test */
    public function sh_rejecting_the_whole_application_restores_all_credits_regardless_of_flags()
    {
        $seed = $this->seedApplication();
        $this->actAsSh();

        $response = $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $seed['app_id'],
            'status' => 'Rejected',
            'remarks' => 'Not approved at all',
            'disapproved_dates' => [
                ['date_applied_id' => $seed['date_ids'][0], 'remark' => 'Irrelevant on reject'],
            ],
        ]);
        $response->assertOk();

        // Whole-application rejection restores every date's credits via the
        // existing restoreCredits() path - both dates, 1.000 each = 2.000.
        $this->assertEqualsWithDelta(7.000, $this->creditBalance(), 0.001);

        $flagged = DB::table('hris.leave_date_applied')->where('id', $seed['date_ids'][0])->first();
        $this->assertEquals(0, (int) $flagged->final_disapproved);
    }

    /** @test */
    public function a_pending_status_submission_does_not_apply_date_disapprovals()
    {
        $seed = $this->seedApplication();
        $this->actAsSh();

        $response = $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $seed['app_id'],
            'status' => 'pending',
            'remarks' => 'Still deciding',
            'disapproved_dates' => [
                ['date_applied_id' => $seed['date_ids'][0], 'remark' => 'Might be an issue'],
            ],
        ]);
        $response->assertOk();

        $untouched = DB::table('hris.leave_date_applied')->where('id', $seed['date_ids'][0])->first();
        $this->assertEquals(0, (int) $untouched->sh_flagged_disapprove);
        $this->assertEquals(0, (int) $untouched->final_disapproved);

        $this->assertEqualsWithDelta(5.000, $this->creditBalance(), 0.001);
    }

    /** @test */
    public function dh_undoing_their_approval_reverses_disapproved_dates_and_credits()
    {
        $seed = $this->seedApplication();

        DB::table('hris.leave_date_applied')->where('id', $seed['date_ids'][0])->update([
            'sh_flagged_disapprove' => 1, 'sh_disapproval_remark' => 'No coverage',
        ]);
        DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $seed['app_id'])->where('role', 'SH')
            ->update(['status' => 'approved', 'approved_at' => now()]);

        $this->actAsDh();
        $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $seed['app_id'],
            'status' => 'Approved',
            'remarks' => 'Agreed',
            'disapproved_dates' => [
                ['date_applied_id' => $seed['date_ids'][0], 'remark' => 'Confirmed'],
            ],
        ])->assertOk();

        $this->assertEqualsWithDelta(6.000, $this->creditBalance(), 0.001);

        $response = $this->withoutMiddleware()->postJson('/undo_approval', ['id' => $seed['app_id']]);
        $response->assertOk();

        $reverted = DB::table('hris.leave_date_applied')->where('id', $seed['date_ids'][0])->first();
        $this->assertEquals(0, (int) $reverted->final_disapproved);
        $this->assertNull($reverted->dh_disapproval_remark);
        // SH's original suggestion survives the undo.
        $this->assertEquals(1, (int) $reverted->sh_flagged_disapprove);
        $this->assertEquals('No coverage', $reverted->sh_disapproval_remark);

        // Credit re-deducted back to the pre-DH-approval balance.
        $this->assertEqualsWithDelta(5.000, $this->creditBalance(), 0.001);

        $dhRow = DB::table('hris.leave_application_approvals')->where('leave_application_id', $seed['app_id'])->where('role', 'DH')->first();
        $this->assertEquals('pending', $dhRow->status);
    }

    /** @test */
    public function get_leave_application_exposes_per_date_flags_and_current_approval_role()
    {
        $seed = $this->seedApplication();
        DB::table('hris.leave_date_applied')->where('id', $seed['date_ids'][0])->update([
            'sh_flagged_disapprove' => 1, 'sh_disapproval_remark' => 'No coverage',
        ]);

        $this->actAsSh();
        $response = $this->withoutMiddleware()->getJson('/leave-applications/' . $seed['app_id']);
        $response->assertOk();
        $response->assertJson(['current_approval_role' => 'SH']);

        $dates = collect($response->json('dates'));
        $flagged = $dates->firstWhere('id', $seed['date_ids'][0]);
        $this->assertTrue($flagged['sh_flagged_disapprove']);
        $this->assertEquals('No coverage', $flagged['sh_disapproval_remark']);
        $this->assertFalse($flagged['final_disapproved']);
    }

    /** @test */
    public function hr_rejecting_after_a_dh_disapproval_does_not_double_credit_the_already_restored_date()
    {
        $seed = $this->seedApplication();

        DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $seed['app_id'])->where('role', 'SH')
            ->update(['status' => 'approved', 'approved_at' => now()]);

        $this->actAsDh();
        $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $seed['app_id'],
            'status' => 'Approved',
            'remarks' => 'Agreed',
            'disapproved_dates' => [
                ['date_applied_id' => $seed['date_ids'][0], 'remark' => 'Confirmed'],
            ],
        ])->assertOk();

        $this->assertEqualsWithDelta(6.000, $this->creditBalance(), 0.001);

        Auth::shouldReceive('id')->andReturn(9999);
        Auth::shouldReceive('user')->andReturn((object) [
            'id' => 9999, 'section' => 0, 'division' => 0,
        ]);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'hr']);

        $response = $this->withoutMiddleware()->postJson('/update/approval_status', [
            'id' => $seed['app_id'],
            'status' => 'Rejected',
            'remarks' => 'Rejecting at HR level',
        ]);
        $response->assertOk();

        // 6.000 (after DH's restore) + 1.000 for date_ids[1] (never restored) = 7.000.
        // NOT 8.000, which would mean date_ids[0]'s credit got restored twice.
        $this->assertEqualsWithDelta(7.000, $this->creditBalance(), 0.001);
    }
}
