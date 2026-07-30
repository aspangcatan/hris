<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeaveApplicationMinuteDeductionTest extends TestCase
{
    use DatabaseTransactions;

    private int $employeeId = 9171;
    private int $shUserId   = 9172;
    private int $dhUserId   = 9173;
    private int $sectionId  = 9271;
    private int $divisionId = 9272;
    private int $leaveTypeId;

    private string $sampleSignature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    // minute_conversions is MyISAM, so DatabaseTransactions rollback is a
    // silent no-op for it - restore the real row this test overwrites,
    // same pattern as LeaveCreditServiceTest.
    private $originalMinute60Deduction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanup();

        DB::table('tdh_user.users')->insertOrIgnore([
            ['id' => $this->shUserId,   'fname' => 'Min',  'lname' => 'ShUser', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'min_sh_test',  'password' => 'x'],
            ['id' => $this->dhUserId,   'fname' => 'Min',  'lname' => 'DhUser', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'min_dh_test',  'password' => 'x'],
            ['id' => $this->employeeId, 'fname' => 'Min',  'lname' => 'EmpUser', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'min_emp_test', 'password' => 'x'],
        ]);
        DB::table('tdh_user.division')->insertOrIgnore([
            ['id' => $this->divisionId, 'description' => 'Min Test Division', 'head' => $this->dhUserId, 'code' => 'MINDIV'],
        ]);
        DB::table('tdh_user.section')->insertOrIgnore([
            ['id' => $this->sectionId, 'description' => 'Min Test Section', 'head' => $this->shUserId, 'division' => $this->divisionId, 'code' => 'MINSEC'],
        ]);

        DB::table('hris.leave_types')->updateOrInsert(
            ['code' => 'MINTEST'],
            ['description' => 'Min Test Leave', 'monthly_earned' => 0, 'status' => 'active']
        );
        $this->leaveTypeId = DB::table('hris.leave_types')->where('code', 'MINTEST')->value('id');

        DB::table('hris.leave_credits')->insertOrIgnore([
            'user_id' => $this->employeeId,
            'leave_id' => $this->leaveTypeId,
            'based_credit' => 10.000,
            'used_credit' => 0.000,
            'last_updated_month' => '2027-1',
        ]);

        $this->originalMinute60Deduction = DB::table('minute_conversions')->where('minute', 60)->value('points_deduction');
        // 0.1/hour - deliberately different from the old flat 1.25/8=0.15625/hour
        // rate so a passing assertion proves the new table-driven rate is in use.
        DB::table('minute_conversions')->where('minute', 60)->update(['points_deduction' => 0.1]);
    }

    protected function tearDown(): void
    {
        DB::table('minute_conversions')->where('minute', 60)->update(['points_deduction' => $this->originalMinute60Deduction]);
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
        Auth::shouldReceive('id')->andReturn($this->employeeId);
        Auth::shouldReceive('user')->andReturn((object) [
            'id' => $this->employeeId, 'section' => $this->sectionId, 'division' => $this->divisionId,
        ]);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'employee', 'is_section_head' => false, 'is_division_head' => false]);
    }

    private function filePayload(int $hours = 8): array
    {
        return [
            'data' => json_encode([
                'commutation' => 'Not Requested',
                'dates' => [
                    ['date_applied' => '2027-03-01', 'hours' => $hours, 'leave_type_id' => $this->leaveTypeId],
                ],
                'applicant_signature' => $this->sampleSignature,
            ]),
        ];
    }

    /** @test */
    public function filing_deducts_credits_using_the_minute_conversions_rate(): void
    {
        $this->actAsEmployee();

        $this->withoutMiddleware()->post('/insert_leave_applications', $this->filePayload(8))->assertOk();

        $appId = DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->value('id');

        // 8 hours * 60 = 480 minutes = 8 full-hour blocks * 0.1 = 0.8
        $this->assertEquals(0.8, DB::table('hris.leave_date_applied')->where('leave_application_id', $appId)->value('credits_deducted'));
        $this->assertEquals(9.2, DB::table('hris.leave_credits')->where('user_id', $this->employeeId)->where('leave_id', $this->leaveTypeId)->value('based_credit'));
    }

    /** @test */
    public function insufficient_balance_is_evaluated_against_the_minute_conversions_rate(): void
    {
        $this->actAsEmployee();

        // Below the 0.8 required for 8 hours at the overridden 0.1/hour rate,
        // but above the old 1.25-based rate would have required (1.0) - proves
        // the new rate, not the old one, drives the balance check.
        DB::table('hris.leave_credits')
            ->where('user_id', $this->employeeId)
            ->where('leave_id', $this->leaveTypeId)
            ->update(['based_credit' => 0.5]);

        $response = $this->withoutMiddleware()->post('/insert_leave_applications', $this->filePayload(8));

        $response->assertStatus(422);
        $this->assertEquals(0, DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->count());
    }

    /** @test */
    public function editing_recalculates_deduction_using_the_minute_conversions_rate(): void
    {
        $this->actAsEmployee();
        $this->withoutMiddleware()->post('/insert_leave_applications', $this->filePayload(8))->assertOk();
        $appId = DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->value('id');

        $update = [
            'commutation' => 'Not Requested',
            'dates' => [
                ['date_applied' => '2027-03-02', 'hours' => 4, 'leave_type_id' => $this->leaveTypeId],
            ],
            'applicant_signature' => $this->sampleSignature,
        ];

        $this->withoutMiddleware()->putJson("/leave-applications/{$appId}", $update)->assertOk();

        // 4 hours * 60 = 240 minutes = 4 full-hour blocks * 0.1 = 0.4
        $this->assertEquals(0.4, DB::table('hris.leave_date_applied')->where('leave_application_id', $appId)->value('credits_deducted'));
        // Restored 0.8 from the old 8h row, then deducted 0.4 for the new 4h row: 10 - 0.4 = 9.6
        $this->assertEquals(9.6, DB::table('hris.leave_credits')->where('user_id', $this->employeeId)->where('leave_id', $this->leaveTypeId)->value('based_credit'));
    }
}
