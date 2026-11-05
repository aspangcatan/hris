<?php

namespace Tests\Feature;

use App\Http\Controllers\LeaveApplicationController;
use App\Services\LeaveApplicationService;
use App\Services\LeaveApprovalService;
use App\Services\LeaveCancellationService;
use App\Services\OrgHierarchyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SwitchesAuthUser;
use Tests\TestCase;

/**
 * Credits must never be given back twice or lost (edit, delete, rejection, DH per-date
 * disapproval, leave cancellation), and nobody may approve their own filing
 * (reviews of 2026-11-05).
 */
class LeaveCreditIntegrityTest extends TestCase
{
    use DatabaseTransactions;
    use SwitchesAuthUser;

    private int $employeeId = 9531;
    private int $shUserId   = 9532;
    private int $dhUserId   = 9533;
    private int $hrUserId   = 9534;
    private int $sectionId  = 9541;
    private int $divisionId = 9542;
    private int $leaveTypeId;

    private string $sig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanup();

        foreach ([$this->employeeId => 'emp', $this->shUserId => 'sh', $this->dhUserId => 'dh', $this->hrUserId => 'hr'] as $id => $tag) {
            DB::table('tdh_user.users')->insert([
                'id' => $id, 'fname' => 'Cri', 'lname' => ucfirst($tag), 'username' => "cri_{$tag}_test", 'password' => 'x', 'designation' => 1,
                'section' => $tag === 'hr' ? 0 : $this->sectionId, 'division' => $tag === 'hr' ? 0 : $this->divisionId,
            ]);
        }
        DB::table('tdh_user.division')->insert(['id' => $this->divisionId, 'description' => 'Cri Test Division', 'head' => $this->dhUserId, 'code' => 'CRIDIV']);
        DB::table('tdh_user.section')->insert(['id' => $this->sectionId, 'description' => 'Cri Test Section', 'head' => $this->shUserId, 'division' => $this->divisionId, 'code' => 'CRISEC']);

        DB::table('hris.leave_types')->updateOrInsert(
            ['code' => 'CRITEST'],
            ['description' => 'Cri Test Leave', 'monthly_earned' => 0, 'status' => 'active', 'days_prior' => 0]
        );
        $this->leaveTypeId = DB::table('hris.leave_types')->where('code', 'CRITEST')->value('id');

        foreach ([$this->employeeId, $this->shUserId, $this->dhUserId] as $uid) {
            DB::table('hris.leave_credits')->insert([
                'user_id' => $uid, 'leave_id' => $this->leaveTypeId,
                'based_credit' => 10.000, 'used_credit' => 0.000, 'last_updated_month' => '2027-1',
            ]);
        }

        $this->actAs('EMP');
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        // The test leave type must not linger: active types appear in employees' dropdowns.
        (new static('cleanup'))->createApplication();
        DB::table('hris.leave_types')->whereIn('code', ['CRITEST', 'CRIMAT', 'CRIOFF'])->delete();
        parent::tearDownAfterClass();
    }

    private function cleanup(): void
    {
        $users  = [$this->employeeId, $this->shUserId, $this->dhUserId, $this->hrUserId];
        $appIds = DB::table('hris.leave_applications')->whereIn('user_id', $users)->pluck('id');
        DB::table('hris.leave_details')->whereIn('leave_application_id', $appIds)->delete();
        DB::table('hris.leave_date_applied')->whereIn('leave_application_id', $appIds)->delete();
        DB::table('hris.leave_application_approvals')->whereIn('leave_application_id', $appIds)->delete();
        DB::table('hris.leave_applications')->whereIn('id', $appIds)->delete();

        $cIds = DB::table('hris.leave_cancellations')->whereIn('user_id', $users)->pluck('id');
        DB::table('hris.leave_cancellation_dates')->whereIn('leave_cancellation_id', $cIds)->delete();
        DB::table('hris.leave_cancellation_approvals')->whereIn('leave_cancellation_id', $cIds)->delete();
        DB::table('hris.leave_cancellations')->whereIn('id', $cIds)->delete();

        $mIds = DB::table('hris.leave_monetizations')->whereIn('user_id', $users)->pluck('id');
        DB::table('hris.leave_monetization_approvals')->whereIn('leave_monetization_id', $mIds)->delete();
        DB::table('hris.leave_monetizations')->whereIn('id', $mIds)->delete();
        DB::table('hris.credit_earned_logs')->whereIn('user_id', $users)->delete();

        DB::table('hris.leave_application_attachments')->whereIn('leave_application_id', $appIds)->delete();
        DB::table('hris.leave_credits')->whereIn('user_id', $users)->delete();
        DB::table('hris.employees')->whereIn('user_id', $users)->delete();
        DB::table('tdh_user.section')->where('id', $this->sectionId)->delete();
        DB::table('tdh_user.division')->where('id', $this->divisionId)->delete();
        DB::table('tdh_user.users')->whereIn('id', $users)->delete();
    }

    // ---------------------------------------------------------------- helpers

    private function balance(?int $userId = null): float
    {
        $c = DB::table('hris.leave_credits')->where('user_id', $userId ?? $this->employeeId)->where('leave_id', $this->leaveTypeId)->first();
        return round($c->based_credit - $c->used_credit, 3);
    }

    /** EMP / SH / DH / HR, or a raw user id (no special rights). */
    private function actAs($who): void
    {
        $id = ['EMP' => $this->employeeId, 'SH' => $this->shUserId, 'DH' => $this->dhUserId, 'HR' => $this->hrUserId][$who] ?? (int) $who;
        $u  = DB::table('tdh_user.users')->where('id', $id)->first(['id', 'section', 'division']);
        $this->actAsUser($u ?: (object) ['id' => $id, 'section' => 0, 'division' => 0]);
        session()->flush();
        session(['access_rights' => $who === 'HR' ? 'HR' : 'employee']);
    }

    /** Files one 8h date per given day (1.0 credit each) as the current user. */
    private function file(array $days): int
    {
        $r = app(LeaveApplicationService::class)->insertLeaveApplication([
            'commutation' => 'Not Requested',
            'dates' => array_map(fn($d) => ['date_applied' => $d, 'hours' => 8, 'leave_type_id' => $this->leaveTypeId], $days),
            'applicant_signature' => $this->sig,
        ]);
        return $r['app_id'];
    }

    private function dateIds(int $appId): array
    {
        return DB::table('hris.leave_date_applied')->where('leave_application_id', $appId)->orderBy('date_applied')->pluck('id')->all();
    }

    private function act(int $appId, string $who, string $status, array $disapprove = []): void
    {
        $this->actAs($who);
        app(LeaveApprovalService::class)->updateApprovalStatus($appId, null, null, $status,
            array_map(fn($id) => ['date_applied_id' => $id, 'remark' => 'no'], $disapprove),
            in_array($who, ['SH', 'DH'], true) ? $this->sig : null);
    }

    /** SH -> DH (optionally disapproving dates) -> HR approval; ends acting as the employee. */
    private function approve(int $appId, array $dhDisapproveIds = []): void
    {
        $this->act($appId, 'SH', 'approved');
        $this->act($appId, 'DH', 'approved', $dhDisapproveIds);
        $this->act($appId, 'HR', 'approved');
        $this->actAs('EMP');
    }

    private function fileCancellation(array $dateIds): int
    {
        return app(LeaveCancellationService::class)->fileCancellation(['date_ids' => $dateIds, 'reason' => 'test']);
    }

    private function actOnCancellation(int $id, string $who, string $status): void
    {
        $this->actAs($who);
        app(LeaveCancellationService::class)->updateApprovalStatus($id, $status, null);
    }

    private function approveCancellation(int $id): void
    {
        foreach (['SH', 'DH', 'HR'] as $who) {
            $this->actOnCancellation($id, $who, 'approved');
        }
        $this->actAs('EMP');
    }

    private function chain(string $table, string $fk, int $id): array
    {
        return DB::table($table)->where($fk, $id)->orderBy('order_no')->get(['role', 'approver_id'])
            ->map(fn($r) => $r->role . ':' . $r->approver_id)->all();
    }

    // ------------------------------------------------------------ #1 editing

    /** @test */
    public function a_failed_edit_leaves_the_dates_and_credits_untouched(): void
    {
        $appId = $this->file(['2027-03-01', '2027-03-02']);
        $this->assertEquals(8.0, $this->balance());

        try {
            app(LeaveApplicationService::class)->updateLeaveApplication($appId, [
                'dates' => [['date_applied' => '2027-03-06', 'hours' => 8, 'leave_type_id' => $this->leaveTypeId]], // Saturday
                'applicant_signature' => $this->sig,
            ]);
            $this->fail('Expected the weekend date to be refused');
        } catch (\Exception $e) {
            $this->assertStringContainsString('weekend', $e->getMessage());
        }

        $this->assertCount(2, $this->dateIds($appId), 'old dates survive the failed edit');
        $this->assertEquals(8.0, $this->balance());
    }

    /** @test */
    public function an_edit_may_use_the_credits_the_application_already_holds(): void
    {
        DB::table('hris.leave_credits')->where('user_id', $this->employeeId)->update(['based_credit' => 2.000]);
        $appId = $this->file(['2027-03-01', '2027-03-02']); // balance 0

        app(LeaveApplicationService::class)->updateLeaveApplication($appId, [
            'dates' => [
                ['date_applied' => '2027-03-03', 'hours' => 8, 'leave_type_id' => $this->leaveTypeId],
                ['date_applied' => '2027-03-04', 'hours' => 8, 'leave_type_id' => $this->leaveTypeId],
            ],
            'applicant_signature' => $this->sig,
        ]);

        $this->assertEquals(0.0, $this->balance());
        $this->assertCount(2, $this->dateIds($appId));
    }

    // ------------------------------------- #6 editing a rejected / acted-on leave

    /** @test */
    public function a_rejected_leave_cannot_be_edited_and_credits_are_not_refunded_twice(): void
    {
        $appId = $this->file(['2027-03-01']);
        $this->act($appId, 'SH', 'rejected');
        $this->assertEquals(10.0, $this->balance());
        $this->actAs('EMP');

        $this->expectExceptionMessage('only pending');
        try {
            app(LeaveApplicationService::class)->updateLeaveApplication($appId, [
                'dates' => [['date_applied' => '2027-03-02', 'hours' => 8, 'leave_type_id' => $this->leaveTypeId]],
                'applicant_signature' => $this->sig,
            ]);
        } finally {
            $this->assertEquals(10.0, $this->balance());
        }
    }

    // ---------------------------------------------------------- #5 deleting

    /** @test */
    public function deleting_a_fully_rejected_leave_gives_nothing_back_twice(): void
    {
        $appId = $this->file(['2027-03-01', '2027-03-02']); // 8
        [$d1] = $this->dateIds($appId);
        $this->act($appId, 'SH', 'approved');
        $this->act($appId, 'DH', 'approved', [$d1]);
        $this->assertEquals(9.0, $this->balance(), 'DH disapproval refunds that date');
        $this->act($appId, 'HR', 'rejected');
        $this->assertEquals(10.0, $this->balance(), 'HR rejection refunds the rest');

        $this->actAs('EMP');
        $response = app(LeaveApplicationController::class)->delete(new Request(), $appId);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(10.0, $this->balance(), 'nothing refunded again');
    }

    // ==================================================== delete rule (review #2)

    /** @test */
    public function a_leave_can_be_deleted_only_before_any_approver_acts_or_once_fully_rejected(): void
    {
        $svc = app(LeaveApplicationService::class);

        $untouched = $this->file(['2027-03-01']);
        $this->assertTrue($svc->canDelete($untouched), 'pending, nobody acted');

        $shApproved = $this->file(['2027-03-02']);
        $this->act($shApproved, 'SH', 'approved');
        $this->assertFalse($svc->canDelete($shApproved), 'under way');

        $this->actAs('EMP');
        $shRejected = $this->file(['2027-03-03']);
        $this->act($shRejected, 'SH', 'rejected');
        $this->assertFalse($svc->canDelete($shRejected), 'SH rejection can still be overturned by the DH');

        $this->actAs('EMP');
        $fullyRejected = $this->file(['2027-03-04']);
        $this->act($fullyRejected, 'SH', 'approved');
        $this->act($fullyRejected, 'DH', 'rejected');
        $this->assertFalse($svc->canDelete($fullyRejected), 'waiting for HR to acknowledge');
        $this->act($fullyRejected, 'HR', 'acknowledged');
        $this->assertTrue($svc->canDelete($fullyRejected), 'fully rejected');

        // The endpoint enforces it too
        $this->actAs('EMP');
        $response = app(LeaveApplicationController::class)->delete(new Request(), $shApproved);
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertNotNull(DB::table('hris.leave_applications')->where('id', $shApproved)->first());
        $this->assertEquals(422, app(LeaveApplicationController::class)->delete(new Request(), $shRejected)->getStatusCode());
    }

    // ==================================================== details per leave type (review #1)

    /** @test */
    public function details_stay_with_their_own_leave_type_whatever_the_row_order(): void
    {
        $other = $this->makeType('CRIOFF', 'Cri Other Leave');

        $appId = app(LeaveApplicationService::class)->insertLeaveApplication([
            'dates' => [
                ['date_applied' => '2027-03-04', 'hours' => 8, 'leave_type_id' => $this->leaveTypeId],
                ['date_applied' => '2027-03-03', 'hours' => 8, 'leave_type_id' => $other],
            ],
            'details_map' => [(string) $this->leaveTypeId => ['detail' => 'TEST-DETAIL'], (string) $other => ['detail' => 'OTHER-DETAIL']],
            'applicant_signature' => $this->sig,
        ])['app_id'];

        $this->assertEquals(
            [$this->leaveTypeId => 'TEST-DETAIL', $other => 'OTHER-DETAIL'],
            DB::table('hris.leave_details')->where('leave_application_id', $appId)->pluck('detail', 'leave_type_id')->all()
        );
        $shown = collect(app(LeaveApplicationService::class)->findById($appId)->details)->pluck('detail', 'leave_id')->all();
        $this->assertEquals('TEST-DETAIL', $shown[$this->leaveTypeId]);
        $this->assertEquals('OTHER-DETAIL', $shown[$other]);
    }

    // ==================================================== required details (review #4)

    /** @test */
    public function types_with_6b_details_require_a_valid_detail_and_others_do_not(): void
    {
        $vl = $this->makeType('CRIMAT', 'Cri Vacation Leave');
        $file = fn(array $details) => app(LeaveApplicationService::class)->insertLeaveApplication([
            'dates' => [['date_applied' => '2027-03-01', 'hours' => 8, 'leave_type_id' => $vl]],
            'details_map' => $details ? [(string) $vl => $details] : [],
            'applicant_signature' => $this->sig,
        ]);

        foreach ([
            'no details'        => [[], 'provide the details'],
            'unknown option'    => [['detail' => 'Somewhere'], 'provide the details'],
            'missing text'      => [['detail' => 'Within the Philippines', 'extra' => ''], 'required detail text'],
        ] as $case => [$details, $message]) {
            try {
                $file($details);
                $this->fail("{$case}: expected refusal");
            } catch (\Exception $e) {
                $this->assertStringContainsString($message, $e->getMessage(), $case);
            }
        }
        $this->assertEquals(10.0, $this->balance($this->employeeId));

        $this->assertGreaterThan(0, $file(['detail' => 'Within the Philippines', 'extra' => 'Cebu'])['app_id']);

        // A type with no 6.B details needs none
        $this->assertGreaterThan(0, $this->fileType($this->leaveTypeId, '2027-03-02'));
    }

    /** @test */
    public function sick_leave_needs_its_classification(): void
    {
        $sl = $this->makeType('CRIMAT', 'Cri Sick Leave');
        $file = fn(string $extra) => app(LeaveApplicationService::class)->insertLeaveApplication([
            'dates' => [['date_applied' => '2027-03-01', 'hours' => 8, 'leave_type_id' => $sl]],
            'details_map' => [(string) $sl => ['detail' => 'Out Patient (Specify Illness)', 'extra' => $extra]],
            'applicant_signature' => $this->sig,
        ]);

        try {
            $file('fever');
            $this->fail('Expected the missing classification to be refused');
        } catch (\Exception $e) {
            $this->assertStringContainsString('classification', $e->getMessage());
        }
        try {
            $file('(Work-Related)');
            $this->fail('Expected the missing illness text to be refused');
        } catch (\Exception $e) {
            $this->assertStringContainsString('required detail text', $e->getMessage());
        }
        $this->assertGreaterThan(0, $file('fever (Non-Work Related)')['app_id']);
    }

    // ==================================================== LWOP flag on edit (review #6)

    /** @test */
    public function an_edit_keeps_the_lwop_setting_it_was_filed_with(): void
    {
        $lwop = app(LeaveApplicationService::class)->insertLeaveApplication([
            'dates' => [['date_applied' => '2027-03-01', 'hours' => 8, 'leave_type_id' => $this->leaveTypeId]],
            'applicant_signature' => $this->sig,
            'is_lwop' => true,
        ])['app_id'];
        $this->assertEquals(10.0, $this->balance());

        // Request without is_lwop (or with false) must not turn it into a paid leave
        app(LeaveApplicationService::class)->updateLeaveApplication($lwop, [
            'dates' => [['date_applied' => '2027-03-02', 'hours' => 8, 'leave_type_id' => $this->leaveTypeId]],
            'applicant_signature' => $this->sig,
            'is_lwop' => false,
        ]);
        $this->assertEquals(1, (int) DB::table('hris.leave_applications')->where('id', $lwop)->value('is_lwop'));
        $this->assertEquals(10.0, $this->balance(), 'still no credits taken');

        // And a regular leave cannot be turned into LWOP to get its credits back
        $regular = $this->file(['2027-03-03']);
        app(LeaveApplicationService::class)->updateLeaveApplication($regular, [
            'dates' => [['date_applied' => '2027-03-03', 'hours' => 8, 'leave_type_id' => $this->leaveTypeId]],
            'applicant_signature' => $this->sig,
            'is_lwop' => true,
        ]);
        $this->assertEquals(0, (int) DB::table('hris.leave_applications')->where('id', $regular)->value('is_lwop'));
        $this->assertEquals(9.0, $this->balance());
    }

    // ==================================================== filing + attachments together (review #3)

    /** Files through the endpoint with one attachment whose name is too long to store (forces a failure). */
    private function fileWithFailingAttachment()
    {
        $file = \Illuminate\Http\UploadedFile::fake()->create(str_repeat('a', 300) . '.pdf', 10, 'application/pdf');
        return $this->withoutMiddleware()->post('/insert_leave_applications', [
            'data' => json_encode([
                'commutation' => 'Not Requested',
                'dates' => [['date_applied' => '2027-03-01', 'hours' => 8, 'leave_type_id' => $this->leaveTypeId]],
                'applicant_signature' => $this->sig,
            ]),
            'attachments' => [$file],
        ]);
    }

    /** @test */
    public function a_failed_attachment_leaves_no_stored_file_behind(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $this->fileWithFailingAttachment()->assertStatus(422);

        $this->assertEmpty(\Illuminate\Support\Facades\Storage::disk('public')->allFiles('leave_attachments'));
    }

    /** @test */
    public function a_failed_attachment_saves_no_part_of_the_filing(): void
    {
        $engine = DB::selectOne("SELECT ENGINE e FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'hris' AND TABLE_NAME = 'leave_applications'")->e ?? '';
        if (strcasecmp($engine, 'InnoDB') !== 0) {
            $this->markTestSkipped("leave_applications is {$engine} here; the rollback needs InnoDB (as in production).");
        }
        \Illuminate\Support\Facades\Storage::fake('public');

        $this->fileWithFailingAttachment()->assertStatus(422);

        $this->assertEquals(0, DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->count());
        $this->assertEquals(10.0, $this->balance());
    }

    // ---------------------------------------- #2 cancelling a DH-disapproved date

    /** @test */
    public function dh_disapproved_dates_are_not_offered_or_accepted_for_cancellation(): void
    {
        $appId = $this->file(['2027-03-01', '2027-03-02']);
        [$d1, $d2] = $this->dateIds($appId);
        $this->approve($appId, [$d1]);
        $this->assertEquals(9.0, $this->balance());

        $offered = app(LeaveCancellationService::class)->getApprovedDatesForUser()->pluck('id')->all();
        $this->assertEquals([$d2], $offered);

        $this->expectExceptionMessage('can no longer be cancelled');
        try {
            $this->fileCancellation([$d1]);
        } finally {
            $this->assertEquals(9.0, $this->balance());
        }
    }

    // ------------------------------------------- #3 duplicate / stale cancellation

    /** @test */
    public function a_date_cannot_be_in_two_open_cancellations(): void
    {
        $appId = $this->file(['2027-03-01']);
        [$d1] = $this->dateIds($appId);
        $this->approve($appId);

        $this->fileCancellation([$d1]);

        $this->expectExceptionMessage('can no longer be cancelled');
        $this->fileCancellation([$d1]);
    }

    /** @test */
    public function an_already_cancelled_date_cannot_be_cancelled_again(): void
    {
        $appId = $this->file(['2027-03-01']);
        [$d1] = $this->dateIds($appId);
        $this->approve($appId);

        $this->approveCancellation($this->fileCancellation([$d1]));
        $this->assertEquals(10.0, $this->balance());

        $this->expectExceptionMessage('can no longer be cancelled');
        try {
            $this->fileCancellation([$d1]);
        } finally {
            $this->assertEquals(10.0, $this->balance());
        }
    }

    /** @test */
    public function hr_approval_refunds_only_dates_that_still_hold_a_deduction(): void
    {
        $appId = $this->file(['2027-03-01']);
        [$d1] = $this->dateIds($appId);
        $this->approve($appId);

        // Two cancellations of the same date slip in (e.g. created before this fix)
        $c1 = $this->fileCancellation([$d1]);
        $c2 = DB::table('hris.leave_cancellations')->insertGetId(['user_id' => $this->employeeId, 'reason' => 'dup', 'status' => 'Pending', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('hris.leave_cancellation_dates')->insert(['leave_cancellation_id' => $c2, 'leave_date_applied_id' => $d1, 'date_applied' => '2027-03-01', 'hours' => 8]);
        foreach (['SH' => [1, $this->sectionId], 'DH' => [2, $this->divisionId], 'HR' => [3, 15]] as $role => [$order, $approver]) {
            DB::table('hris.leave_cancellation_approvals')->insert(['leave_cancellation_id' => $c2, 'approver_id' => $approver, 'role' => $role, 'order_no' => $order, 'status' => 'pending']);
        }

        $this->approveCancellation($c1);
        $this->approveCancellation($c2);

        $this->assertEquals(10.0, $this->balance(), 'the credit comes back once');
    }

    // --------------------------------------------- #4 rejected cancellation stops

    /** @test */
    public function a_rejected_cancellation_no_longer_moves_through_the_chain(): void
    {
        $appId = $this->file(['2027-03-01']);
        [$d1] = $this->dateIds($appId);
        $this->approve($appId);
        $cId = $this->fileCancellation([$d1]);

        $this->actOnCancellation($cId, 'SH', 'rejected');

        $this->actAs('DH');
        $queue = app(LeaveCancellationService::class)->getCancellationsForApproval()->pluck('id')->all();
        $this->assertNotContains($cId, $queue, 'DH queue does not show it');
        $this->assertFalse(app(LeaveCancellationService::class)->getCancellationById($cId)->can_act);

        try {
            $this->actOnCancellation($cId, 'DH', 'approved');
            $this->fail('Expected DH approval to be refused');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('not allowed', $e->getMessage());
        }

        $this->assertEquals('Rejected', DB::table('hris.leave_cancellations')->where('id', $cId)->value('status'));
        $this->assertEquals('active', DB::table('hris.leave_date_applied')->where('id', $d1)->value('status'));
        $this->assertEquals(9.0, $this->balance());
    }

    // ---------------------------------- #7 rejection after a cancelled date

    /** @test */
    public function rejecting_after_hr_undo_does_not_refund_cancelled_dates_again(): void
    {
        $appId = $this->file(['2027-03-01', '2027-03-02']); // 8
        [$d1] = $this->dateIds($appId);
        $this->approve($appId);
        $this->approveCancellation($this->fileCancellation([$d1])); // 9

        $this->actAs('HR');
        app(LeaveApprovalService::class)->undoApproval($appId);
        app(LeaveApprovalService::class)->updateApprovalStatus($appId, null, null, 'rejected');

        $this->assertEquals(10.0, $this->balance(), 'only the non-cancelled date is refunded');
    }

    // ==================================================== security: self-approval

    /** @test */
    public function a_section_heads_own_leave_skips_their_step_and_goes_to_the_division_head(): void
    {
        $this->actAs('SH');
        $appId = $this->file(['2027-03-01']);

        $this->assertEquals(["DH:{$this->divisionId}", 'HR:15'], $this->chain('hris.leave_application_approvals', 'leave_application_id', $appId));
        $this->assertNull(app(LeaveApprovalService::class)->resolveCurrentApprovalRoleForLeave($appId), 'SH cannot act on own leave');
        $this->assertNotContains($appId, app(LeaveApprovalService::class)->getLeavesForApproval()->pluck('id')->all());

        $this->act($appId, 'DH', 'approved');
        $this->assertEquals('approved', DB::table('hris.leave_application_approvals')->where('leave_application_id', $appId)->where('role', 'DH')->value('status'));
    }

    /** @test */
    public function a_division_heads_own_leave_goes_to_the_medical_center_chief(): void
    {
        $topId  = app(OrgHierarchyService::class)->topDivisionId();
        $mccId  = (int) DB::table('tdh_user.division')->where('id', $topId)->value('head');

        $this->actAs('DH');
        $appId = $this->file(['2027-03-01']);
        $this->assertEquals(["DH:{$topId}", 'HR:15'], $this->chain('hris.leave_application_approvals', 'leave_application_id', $appId));
        $this->assertNull(app(LeaveApprovalService::class)->resolveCurrentApprovalRoleForLeave($appId), 'DH cannot act on own leave');

        $this->actAs($mccId);
        $this->assertEquals('DH', app(LeaveApprovalService::class)->resolveCurrentApprovalRoleForLeave($appId), 'MCC acts on it');
    }

    /** @test */
    public function the_medical_center_chiefs_own_filing_goes_straight_to_hr(): void
    {
        $topId = app(OrgHierarchyService::class)->topDivisionId();
        $mcc   = DB::table('tdh_user.users')->where('id', DB::table('tdh_user.division')->where('id', $topId)->value('head'))->first(['id', 'section', 'division']);

        $this->assertEquals([], app(OrgHierarchyService::class)->approvalChainFor($mcc));
    }

    /** @test */
    public function nobody_can_approve_their_own_leave_even_as_admin_or_hr(): void
    {
        $appId = $this->file(['2027-03-01']);
        session(['access_rights' => 'ADMIN']);
        $this->assertNull(app(LeaveApprovalService::class)->resolveCurrentApprovalRoleForLeave($appId));

        $this->expectException(\RuntimeException::class);
        app(LeaveApprovalService::class)->updateApprovalStatus($appId, null, null, 'approved', [], $this->sig);
    }

    /** @test */
    public function a_member_who_heads_another_section_cannot_approve_their_own_sections_leaves(): void
    {
        // A user in our section who heads some other section: the old check (session flag +
        // own section field) let them approve our section's leaves.
        $otherSection = 9543;
        DB::table('tdh_user.users')->insert(['id' => 9535, 'fname' => 'Cri', 'lname' => 'Other', 'username' => 'cri_other_test', 'password' => 'x', 'designation' => 1, 'section' => $this->sectionId, 'division' => $this->divisionId]);
        DB::table('tdh_user.section')->insert(['id' => $otherSection, 'description' => 'Cri Other', 'head' => 9535, 'division' => $this->divisionId, 'code' => 'CRIOTH']);

        try {
            $appId = $this->file(['2027-03-01']);
            $this->actAs(9535);
            session(['is_section_head' => true]);
            $this->assertNull(app(LeaveApprovalService::class)->resolveCurrentApprovalRoleForLeave($appId));
            $this->actAs('SH');
            $this->assertEquals('SH', app(LeaveApprovalService::class)->resolveCurrentApprovalRoleForLeave($appId));
        } finally {
            DB::table('tdh_user.section')->where('id', $otherSection)->delete();
            DB::table('tdh_user.users')->where('id', 9535)->delete();
        }
    }

    /** @test */
    public function a_section_heads_own_cancellation_skips_their_step(): void
    {
        $this->actAs('SH');
        $appId = $this->file(['2027-03-01']);
        $this->act($appId, 'DH', 'approved');
        $this->act($appId, 'HR', 'approved');

        $this->actAs('SH');
        [$d1] = $this->dateIds($appId);
        $cId = $this->fileCancellation([$d1]);
        $this->assertEquals(["DH:{$this->divisionId}", 'HR:15'], $this->chain('hris.leave_cancellation_approvals', 'leave_cancellation_id', $cId));

        try {
            $this->actOnCancellation($cId, 'SH', 'approved');
            $this->fail('SH must not act on own cancellation');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('not allowed', $e->getMessage());
        }
    }

    // ==================================================== security: who can view

    /** @test */
    public function an_unrelated_employee_cannot_view_print_or_list_someone_elses_leave(): void
    {
        $appId = $this->file(['2027-03-01']);
        $this->actAs($this->hrUserId); // a user id with no head role...
        session(['access_rights' => 'employee']); // ...and no HR rights

        $this->withoutMiddleware()->getJson("/leave-applications/{$appId}")->assertStatus(404);
        $this->withoutMiddleware()->get("/print_leave?id={$appId}")->assertStatus(404);
        $this->withoutMiddleware()->getJson("/get_credit_balance?id={$this->employeeId}")->assertStatus(403);

        foreach (['pending', 'approved_by_stage', 'approved_all'] as $filter) {
            $this->assertNotContains($appId, app(LeaveApprovalService::class)->getLeavesForApproval(null, $filter)->pluck('id')->all(), $filter);
        }
    }

    /** @test */
    public function the_owner_and_the_chains_heads_can_view_the_leave(): void
    {
        $appId = $this->file(['2027-03-01']);
        $this->withoutMiddleware()->getJson("/leave-applications/{$appId}")->assertOk();

        $this->actAs('SH');
        $this->withoutMiddleware()->getJson("/leave-applications/{$appId}")->assertOk();
        $this->actAs('DH');
        $this->withoutMiddleware()->getJson("/leave-applications/{$appId}")->assertOk();
        $this->actAs('HR');
        $this->withoutMiddleware()->getJson("/leave-applications/{$appId}")->assertOk();
    }

    /** @test */
    public function an_unrelated_employee_cannot_view_someone_elses_cancellation(): void
    {
        $appId = $this->file(['2027-03-01']);
        [$d1] = $this->dateIds($appId);
        $this->approve($appId);
        $cId = $this->fileCancellation([$d1]);

        $this->actAs($this->hrUserId);
        session(['access_rights' => 'employee']);
        $this->withoutMiddleware()->getJson("/leave-cancellations/{$cId}")->assertStatus(404);

        $this->actAs('EMP');
        $this->withoutMiddleware()->getJson("/leave-cancellations/{$cId}")->assertOk();
    }

    // ==================================================== security: route permissions

    /** @test */
    public function credit_and_leave_type_endpoints_require_their_permissions(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\Authenticate::class);

        $this->putJson('/update_used_credit', ['employee_id' => $this->employeeId, 'leave_id' => $this->leaveTypeId, 'used_credit' => -100])->assertStatus(403);
        $this->putJson('/update_leave_type', ['description' => 'Hacked'])->assertStatus(403);
        $this->deleteJson('/delete/leave_type', ['id' => $this->leaveTypeId])->assertStatus(403);
        $this->postJson('/bulk-leave-credits', [])->assertStatus(403);
        $this->getJson('/get_all_cancellations_admin')->assertStatus(403);

        $this->assertEquals(10.0, $this->balance());
        $this->assertNotNull(DB::table('hris.leave_types')->where('id', $this->leaveTypeId)->first());
    }

    // ==================================================== filing rules (gap review #4-#9)

    /** Creates a test leave type (with 10 credits for the employee) and returns its id. */
    private function makeType(string $code, string $description, string $status = 'active'): int
    {
        DB::table('hris.leave_types')->updateOrInsert(['code' => $code], ['description' => $description, 'monthly_earned' => 0, 'status' => $status, 'days_prior' => 0]);
        $id = (int) DB::table('hris.leave_types')->where('code', $code)->value('id');
        DB::table('hris.leave_credits')->insert(['user_id' => $this->employeeId, 'leave_id' => $id, 'based_credit' => 10, 'used_credit' => 0, 'last_updated_month' => '2027-1']);
        return $id;
    }

    private function fileType(int $typeId, string $day = '2027-03-01'): int
    {
        return app(LeaveApplicationService::class)->insertLeaveApplication([
            'commutation' => 'Not Requested',
            'dates' => [['date_applied' => $day, 'hours' => 8, 'leave_type_id' => $typeId]],
            'applicant_signature' => $this->sig,
        ])['app_id'];
    }

    /** @test */
    public function the_same_date_cannot_be_listed_twice_in_one_filing_even_as_half_days_of_different_types(): void
    {
        $other = $this->makeType('CRIOFF', 'Cri Other Leave');

        foreach ([$this->leaveTypeId, $other] as $secondType) {
            try {
                app(LeaveApplicationService::class)->insertLeaveApplication([
                    'dates' => [
                        ['date_applied' => '2027-03-01', 'hours' => 4, 'leave_type_id' => $this->leaveTypeId],
                        ['date_applied' => '2027-03-01', 'hours' => 4, 'leave_type_id' => $secondType],
                    ],
                    'applicant_signature' => $this->sig,
                ]);
                $this->fail('Expected the repeated date to be refused');
            } catch (\Exception $e) {
                $this->assertStringContainsString('more than once', $e->getMessage());
            }
        }
        $this->assertEquals(10.0, $this->balance());
    }

    /** @test */
    public function a_second_half_day_filing_on_the_same_date_is_refused(): void
    {
        app(LeaveApplicationService::class)->insertLeaveApplication([
            'dates' => [['date_applied' => '2027-03-01', 'hours' => 4, 'leave_type_id' => $this->leaveTypeId]],
            'applicant_signature' => $this->sig,
        ]);

        $this->expectExceptionMessage('already have a pending or approved leave');
        app(LeaveApplicationService::class)->insertLeaveApplication([
            'dates' => [['date_applied' => '2027-03-01', 'hours' => 4, 'leave_type_id' => $this->leaveTypeId]],
            'applicant_signature' => $this->sig,
        ]);
    }

    /** @test */
    public function a_date_already_on_a_live_leave_cannot_be_filed_again_until_that_leave_is_rejected(): void
    {
        $first = $this->file(['2027-03-01', '2027-03-02']);

        try {
            $this->file(['2027-03-02', '2027-03-03']);
            $this->fail('Expected the overlapping date to be refused');
        } catch (\Exception $e) {
            $this->assertStringContainsString('2027-03-02', $e->getMessage());
        }
        $this->assertEquals(8.0, $this->balance(), 'nothing deducted for the refused filing');

        $this->act($first, 'SH', 'rejected');
        $this->actAs('EMP');
        $this->file(['2027-03-02']);
        $this->assertEquals(9.0, $this->balance());
    }

    /** @test */
    public function editing_a_leave_may_keep_its_own_dates(): void
    {
        $appId = $this->file(['2027-03-01']);
        app(LeaveApplicationService::class)->updateLeaveApplication($appId, [
            'dates' => [['date_applied' => '2027-03-01', 'hours' => 4, 'leave_type_id' => $this->leaveTypeId]],
            'applicant_signature' => $this->sig,
        ]);
        $this->assertEquals(9.5, $this->balance());
    }

    /** @test */
    public function an_inactive_or_unknown_leave_type_is_refused(): void
    {
        $off = $this->makeType('CRIOFF', 'Cri Inactive Leave', 'inactive');
        foreach ([$off, 999999] as $typeId) {
            try {
                $this->fileType($typeId);
                $this->fail("Expected type {$typeId} to be refused");
            } catch (\Exception $e) {
                $this->assertStringContainsString('no longer available', $e->getMessage());
            }
        }
    }

    /** @test */
    public function sex_restricted_leave_types_are_enforced_on_the_server(): void
    {
        $mat = $this->makeType('CRIMAT', 'Cri Maternity Leave');

        // No employee record / no sex recorded: refused
        try {
            $this->fileType($mat);
            $this->fail('An employee with no recorded sex must not file maternity leave');
        } catch (\Exception $e) {
            $this->assertStringContainsString('sex to be recorded', $e->getMessage());
        }

        DB::table('hris.employees')->insert(['user_id' => $this->employeeId, 'sex' => 'MALE']);
        try {
            $this->fileType($mat);
            $this->fail('A male employee must not file maternity leave');
        } catch (\Exception $e) {
            $this->assertStringContainsString('female employees', $e->getMessage());
        }

        DB::table('hris.employees')->where('user_id', $this->employeeId)->update(['sex' => 'FEMALE']);
        $this->assertGreaterThan(0, $this->fileType($mat));
    }

    /** @test */
    public function special_leave_benefits_for_women_is_female_only_and_unrestricted_types_ignore_sex(): void
    {
        $slbw = $this->makeType('CRIMAT', 'Cri Special Leave Benefits for Women');
        DB::table('hris.employees')->insert(['user_id' => $this->employeeId, 'sex' => 'MALE']);

        try {
            $this->fileType($slbw);
            $this->fail('A male employee must not file Special Leave Benefits for Women');
        } catch (\Exception $e) {
            $this->assertStringContainsString('female employees', $e->getMessage());
        }

        // An ordinary leave type is not affected by sex (or by sex being unrecorded)
        DB::table('hris.employees')->where('user_id', $this->employeeId)->update(['sex' => null]);
        $this->assertGreaterThan(0, $this->fileType($this->leaveTypeId, '2027-03-02'));
    }

    /** @test */
    public function maternity_leave_needs_an_attachment_to_be_filed_and_approved(): void
    {
        $mat = $this->makeType('CRIMAT', 'Cri Maternity Leave');
        DB::table('hris.employees')->insert(['user_id' => $this->employeeId, 'sex' => 'FEMALE']);

        $this->withoutMiddleware()->post('/insert_leave_applications', ['data' => json_encode([
            'commutation' => 'Not Requested',
            'dates' => [['date_applied' => '2027-03-01', 'hours' => 8, 'leave_type_id' => $mat]],
            'applicant_signature' => $this->sig,
        ])])->assertStatus(422);
        $this->assertEquals(0, DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->count());

        // Filed without one through the service (as an edit could leave it): approval is refused
        $appId = $this->fileType($mat);
        try {
            $this->act($appId, 'SH', 'approved');
            $this->fail('Expected approval to be refused without an attachment');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('attachment', $e->getMessage());
        }
        $this->assertEquals('pending', DB::table('hris.leave_application_approvals')->where('leave_application_id', $appId)->where('role', 'SH')->value('status'));

        DB::table('hris.leave_application_attachments')->insert(['leave_application_id' => $appId, 'file_path' => 'leave_attachments/cri.pdf', 'original_name' => 'cri.pdf']);
        $this->act($appId, 'SH', 'approved');
        $this->assertEquals('approved', DB::table('hris.leave_application_approvals')->where('leave_application_id', $appId)->where('role', 'SH')->value('status'));
    }

    // ==================================================== attachments cleanup (#4)

    /** Adds one stored attachment file (on a faked public disk) to the application. */
    private function attach(int $appId, string $name = 'proof.pdf'): string
    {
        $path = "leave_attachments/{$appId}_{$name}";
        \Illuminate\Support\Facades\Storage::disk('public')->put($path, 'pdf');
        DB::table('hris.leave_application_attachments')->insert(['leave_application_id' => $appId, 'file_path' => $path, 'original_name' => $name]);
        return $path;
    }

    /** @test */
    public function deleting_a_leave_removes_its_rows_and_attachment_files(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $appId = $this->file(['2027-03-01']);
        $path  = $this->attach($appId);

        app(LeaveApplicationController::class)->delete(new Request(), $appId);

        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($path);
        foreach (['leave_date_applied', 'leave_application_approvals', 'leave_details', 'leave_application_attachments'] as $table) {
            $this->assertEquals(0, DB::table("hris.{$table}")->where('leave_application_id', $appId)->count(), $table);
        }
        $this->assertEquals(10.0, $this->balance());
    }

    /** @test */
    public function an_sh_rejection_keeps_the_attachments_because_it_can_still_be_overturned(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $appId = $this->file(['2027-03-01']);
        $path  = $this->attach($appId);

        $this->act($appId, 'SH', 'rejected');

        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($path);
        $this->assertEquals(1, DB::table('hris.leave_application_attachments')->where('leave_application_id', $appId)->count());
    }

    /** @test */
    public function attachments_are_deleted_once_the_leave_is_completely_rejected(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        // DH rejects (final) -> files kept until HR acknowledges, then removed
        $a = $this->file(['2027-03-01']);
        $pathA = $this->attach($a);
        $this->act($a, 'SH', 'approved');
        $this->act($a, 'DH', 'rejected');
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($pathA);
        $this->act($a, 'HR', 'acknowledged');
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($pathA);
        $this->assertEquals(0, DB::table('hris.leave_application_attachments')->where('leave_application_id', $a)->count());

        // HR rejects -> removed
        $this->actAs('EMP');
        $b = $this->file(['2027-03-02']);
        $pathB = $this->attach($b);
        $this->act($b, 'SH', 'approved');
        $this->act($b, 'DH', 'approved');
        $this->act($b, 'HR', 'rejected');
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($pathB);
    }

    /** @test */
    public function an_approved_leave_keeps_its_attachments(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $appId = $this->file(['2027-03-01']);
        $path  = $this->attach($appId);
        $this->approve($appId);

        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($path);
    }

    /** @test */
    public function the_division_head_cannot_undo_after_hr_acknowledged(): void
    {
        $appId = $this->file(['2027-03-01']);
        $this->act($appId, 'SH', 'approved');
        $this->act($appId, 'DH', 'approved');
        $this->act($appId, 'HR', 'acknowledged');
        $this->assertEquals('Approved', DB::table('hris.leave_applications')->where('id', $appId)->value('status'));

        $this->actAs('DH');
        $this->assertFalse(app(LeaveApprovalService::class)->canUndoApproval($appId));
        $this->expectException(\RuntimeException::class);
        try {
            app(LeaveApprovalService::class)->undoApproval($appId);
        } finally {
            $this->assertEquals('Approved', DB::table('hris.leave_applications')->where('id', $appId)->value('status'));
        }
    }

    /** @test */
    public function hr_cannot_approve_a_cancellation_once_the_leave_is_no_longer_approved(): void
    {
        $appId = $this->file(['2027-03-01']);
        [$d1] = $this->dateIds($appId);
        $this->approve($appId);
        $cId = $this->fileCancellation([$d1]);
        $this->actOnCancellation($cId, 'SH', 'approved');
        $this->actOnCancellation($cId, 'DH', 'approved');

        $this->actAs('HR');
        app(LeaveApprovalService::class)->undoApproval($appId); // leave back to Pending

        try {
            $this->actOnCancellation($cId, 'HR', 'approved');
            $this->fail('Expected HR approval to be refused');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('no longer approved', $e->getMessage());
        }
        $this->assertEquals('pending', DB::table('hris.leave_cancellation_approvals')->where('leave_cancellation_id', $cId)->where('role', 'HR')->value('status'));
        $this->assertEquals('active', DB::table('hris.leave_date_applied')->where('id', $d1)->value('status'));
        $this->assertEquals(9.0, $this->balance());
    }

    // ------------------------------------------------- double submit / overturn

    /** @test */
    public function a_step_acted_on_twice_gives_the_credits_back_only_once(): void
    {
        $appId = $this->file(['2027-03-01']);
        $this->assertEquals(9.0, $this->balance());

        $this->act($appId, 'SH', 'rejected');
        $this->assertEquals(10.0, $this->balance());

        // A second request that resolved the SH step before the first one committed
        // (double-click / two tabs) arrives with the role already decided.
        $this->actAs('SH');
        try {
            app(LeaveApprovalService::class)->updateApprovalStatus($appId, 'SH', null, 'rejected');
            $this->fail('Expected the second action on the same step to be refused');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('already been acted on', $e->getMessage());
        }
        $this->assertEquals(10.0, $this->balance(), 'credits returned once, not twice');
    }

    /** @test */
    public function a_rejection_cannot_be_overturned_once_its_dates_are_on_another_leave(): void
    {
        $rejected = $this->file(['2027-03-01']);
        $this->act($rejected, 'SH', 'rejected');
        $this->actAs('EMP');
        $this->file(['2027-03-01']);             // the employee re-files the freed day
        $this->assertEquals(9.0, $this->balance());

        $this->actAs('DH');
        try {
            app(LeaveApprovalService::class)->updateApprovalStatus($rejected, null, null, 'approved', [], $this->sig, true);
            $this->fail('Expected the overturn to be refused');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('2027-03-01', $e->getMessage());
        }
        $this->assertEquals(9.0, $this->balance(), 'nothing re-deducted');
        $this->assertEquals('rejected', strtolower(DB::table('hris.leave_applications')->where('id', $rejected)->value('status')));
    }

    /** @test */
    public function a_rejection_can_still_be_overturned_when_its_dates_are_free(): void
    {
        $appId = $this->file(['2027-03-01']);
        $this->act($appId, 'SH', 'rejected');
        $this->assertEquals(10.0, $this->balance());

        $this->actAs('DH');
        app(LeaveApprovalService::class)->updateApprovalStatus($appId, null, null, 'approved', [], $this->sig, true);

        $this->assertEquals(9.0, $this->balance());
        $this->assertEquals('pending', strtolower(DB::table('hris.leave_applications')->where('id', $appId)->value('status')));
    }

    // ------------------------------------------------ monetization / cutoff earning

    /** Monetization approvals still check the session head flags. */
    private function actAsMonetizationApprover(string $who): void
    {
        $this->actAs($who);
        session(['is_section_head' => $who === 'SH', 'is_division_head' => $who === 'DH']);
    }

    /** @test */
    public function a_rejected_monetization_cannot_be_acted_on_again_or_refunded_twice(): void
    {
        DB::table('hris.leave_types')->where('id', $this->leaveTypeId)
            ->update(['monetizable' => 1, 'min_monetize_days' => 0, 'min_retained_days' => 0]);

        $id = app(\App\Services\LeaveMonetizationService::class)->store(['leave_type_id' => $this->leaveTypeId, 'days_requested' => 2]);
        $this->assertEquals(7.5, $this->balance()); // 2 days x 1.25

        $this->actAsMonetizationApprover('SH');
        app(\App\Services\LeaveMonetizationService::class)->updateApprovalStatus($id, null, 'rejected');
        $this->assertEquals(10.0, $this->balance());

        // The Division Head's step was left pending, but the request is finished.
        $this->actAsMonetizationApprover('DH');
        foreach (['rejected', 'approved'] as $status) {
            try {
                app(\App\Services\LeaveMonetizationService::class)->updateApprovalStatus($id, null, $status);
                $this->fail("Expected '{$status}' on a rejected monetization to be refused");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('already rejected', $e->getMessage());
            }
        }
        $this->assertEquals(10.0, $this->balance(), 'credits returned once, not twice');
        $this->assertCount(0, app(\App\Services\LeaveMonetizationService::class)->getForApproval(null, 'pending')
            ->where('id', $id), 'a rejected request is not in the next approver\'s pending list');
    }

    /** @test */
    public function the_monthly_cutoff_credits_a_month_once_and_keeps_deductions_made_in_between(): void
    {
        DB::table('hris.leave_types')->where('id', $this->leaveTypeId)->update(['monthly_earned' => 1]);
        $service = app(\App\Services\LeaveCreditService::class);

        $this->file(['2027-03-01']);                 // 1.0 deducted: balance 9
        $service->updateEarnedCredits($this->employeeId, 2027, 2, 22, 0, 1.25, 0);
        $this->assertEquals(10.25, $this->balance(), 'earned credits added on top of the deduction');

        $service->updateEarnedCredits($this->employeeId, 2027, 2, 22, 0, 1.25, 0); // second click
        $this->assertEquals(10.25, $this->balance(), 'the same month is not credited twice');
        $this->assertEquals(1, DB::table('hris.credit_earned_logs')
            ->where('user_id', $this->employeeId)->where('leave_type_id', $this->leaveTypeId)->count());
    }

    // ------------------------------------------- DH concurs with an SH rejection

    /** @test */
    public function the_division_head_can_agree_with_a_section_head_rejection_and_hr_closes_it(): void
    {
        $appId = $this->file(['2027-03-01', '2027-03-02']);
        $this->assertEquals(8.0, $this->balance());

        $this->act($appId, 'SH', 'rejected');
        $this->assertEquals(10.0, $this->balance());
        $this->assertFalse(app(LeaveApplicationService::class)->canDelete($appId), 'still open: the DH has not decided');

        $this->act($appId, 'DH', 'rejected');           // agrees - no extra refund
        $this->assertEquals(10.0, $this->balance(), 'credits returned once, not again on the DH rejection');
        $this->assertEquals('rejected', strtolower(DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $appId)->where('role', 'DH')->value('status')));

        // HR can only acknowledge a DH rejection; that closes the leave.
        $this->act($appId, 'HR', 'acknowledged');
        $this->assertEquals(10.0, $this->balance());
        $this->assertEquals('rejected', strtolower(DB::table('hris.leave_applications')->where('id', $appId)->value('status')));
        $this->assertFalse(DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $appId)->whereRaw('LOWER(status) = ?', ['pending'])->exists(), 'nothing left pending');

        $this->actAs('EMP');
        $this->assertTrue(app(LeaveApplicationService::class)->canDelete($appId), 'the employee can now delete it');
    }

    /** @test */
    public function only_the_division_head_can_agree_with_an_earlier_rejection(): void
    {
        $appId = $this->file(['2027-03-01']);
        $this->act($appId, 'SH', 'rejected');

        // HR's turn has not come (the DH step is still open), so HR cannot reject or close it.
        $this->actAs('HR');
        foreach (['rejected', 'acknowledged'] as $status) {
            try {
                app(LeaveApprovalService::class)->updateApprovalStatus($appId, null, null, $status);
                $this->fail("Expected HR '{$status}' to be refused before the DH decides");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('not allowed', $e->getMessage());
            }
        }
        $this->assertEquals(10.0, $this->balance());
    }
}
