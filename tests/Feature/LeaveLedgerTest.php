<?php

namespace Tests\Feature;

use App\Http\Controllers\LeaveApplicationController;
use App\Services\LeaveApplicationService;
use App\Services\LeaveApprovalService;
use App\Services\LeaveCancellationService;
use App\Services\LeaveLedgerService;
use App\Services\LeaveMonetizationService;
use App\Services\LeaveOpeningBalanceService;
use App\Services\OvertimeServices;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SwitchesAuthUser;
use Tests\TestCase;

/**
 * Every balance change reaches the ledger on its leave date, and certified
 * opening balances reconcile with leave_credits. Every write rolls back.
 */
class LeaveLedgerTest extends TestCase
{
    use DatabaseTransactions;
    use SwitchesAuthUser;

    private int $employeeId = 9631;
    private int $shUserId   = 9632;
    private int $dhUserId   = 9633;
    private int $hrUserId   = 9634;
    private int $sectionId  = 9641;
    private int $divisionId = 9642;
    private int $typeId;

    private string $sig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([$this->employeeId => 'emp', $this->shUserId => 'sh', $this->dhUserId => 'dh', $this->hrUserId => 'hr'] as $id => $tag) {
            DB::table('tdh_user.users')->where('id', $id)->delete();
            DB::table('tdh_user.users')->insert([
                'id' => $id, 'fname' => 'Led', 'lname' => ucfirst($tag), 'username' => "led_{$tag}_test", 'password' => 'x', 'designation' => 1,
                'section' => $tag === 'hr' ? 0 : $this->sectionId, 'division' => $tag === 'hr' ? 0 : $this->divisionId,
            ]);
        }
        DB::table('tdh_user.division')->where('id', $this->divisionId)->delete();
        DB::table('tdh_user.section')->where('id', $this->sectionId)->delete();
        DB::table('tdh_user.division')->insert(['id' => $this->divisionId, 'description' => 'Led Division', 'head' => $this->dhUserId, 'code' => 'LEDDIV']);
        DB::table('tdh_user.section')->insert(['id' => $this->sectionId, 'description' => 'Led Section', 'head' => $this->shUserId, 'division' => $this->divisionId, 'code' => 'LEDSEC']);

        $this->typeId = DB::table('hris.leave_types')->insertGetId([
            'code' => 'LEDTEST', 'description' => 'Ledger Test Leave', 'monthly_earned' => 0, 'deduct_tardiness' => 0,
            'status' => 'active', 'days_prior' => 0, 'monetizable' => 1, 'min_monetize_days' => 0, 'min_retained_days' => 0,
        ]);
        DB::table('hris.leave_credits')->insert([
            'user_id' => $this->employeeId, 'leave_id' => $this->typeId, 'based_credit' => 10, 'used_credit' => 0, 'last_updated_month' => '2027-1',
        ]);

        $this->actAs('EMP');
    }

    protected function tearDown(): void
    {
        // tdh_user tables may not roll back with the test.
        DB::table('tdh_user.section')->where('id', $this->sectionId)->delete();
        DB::table('tdh_user.division')->where('id', $this->divisionId)->delete();
        DB::table('tdh_user.users')->whereIn('id', [$this->employeeId, $this->shUserId, $this->dhUserId, $this->hrUserId])->delete();
        parent::tearDown();
    }

    private function actAs($who): void
    {
        $id = ['EMP' => $this->employeeId, 'SH' => $this->shUserId, 'DH' => $this->dhUserId, 'HR' => $this->hrUserId][$who];
        $u = DB::table('tdh_user.users')->where('id', $id)->first(['id', 'section', 'division']);
        $this->actAsUser($u);
        session()->flush();
        session(['access_rights' => $who === 'HR' ? 'HR' : 'employee', 'is_section_head' => $who === 'SH', 'is_division_head' => $who === 'DH']);
    }

    private function file(array $days): int
    {
        return app(LeaveApplicationService::class)->insertLeaveApplication([
            'commutation' => 'Not Requested',
            'dates' => array_map(fn($d) => ['date_applied' => $d, 'hours' => 8, 'leave_type_id' => $this->typeId], $days),
            'applicant_signature' => $this->sig,
        ])['app_id'];
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

    private function balance(?int $typeId = null): float
    {
        return app(LeaveLedgerService::class)->balance($this->employeeId, $typeId ?? $this->typeId);
    }

    private function entries(?int $typeId = null)
    {
        return DB::table('hris.leave_credit_ledger')
            ->where('employee_id', $this->employeeId)->where('leave_type_id', $typeId ?? $this->typeId)->orderBy('id')->get();
    }

    /** The ledger's net movement must equal how far the balance moved from 10. */
    private function assertLedgerMatches(): void
    {
        $net = $this->entries()->sum(fn($e) => (float) $e->earned_credits - (float) $e->consumed_credits + (float) $e->adjustments);
        $this->assertEqualsWithDelta($this->balance() - 10, $net, 0.001, 'ledger does not match the balance');
    }

    /** @test */
    public function filing_records_one_entry_per_leave_date_on_that_date()
    {
        $this->file(['2027-03-02', '2027-03-01']);

        $entries = $this->entries();
        $this->assertCount(2, $entries);
        $this->assertSame(['2027-03-01', '2027-03-02'], $entries->pluck('effective_date')->all());
        $this->assertSame([LeaveLedgerService::CONSUMED, LeaveLedgerService::CONSUMED], $entries->pluck('transaction_type')->all());
        $this->assertEquals(10, $entries[0]->beginning_balance);
        $this->assertEquals(9, $entries[0]->ending_balance);
        $this->assertEquals(9, $entries[1]->beginning_balance);
        $this->assertEquals(8, $entries[1]->ending_balance);
        $this->assertSame(now()->toDateString(), substr($entries[0]->transaction_date, 0, 10));
        $this->assertLedgerMatches();
    }

    /** @test */
    public function editing_returns_the_old_dates_and_charges_the_new_ones()
    {
        $appId = $this->file(['2027-03-01', '2027-03-02']);
        app(LeaveApplicationService::class)->updateLeaveApplication($appId, [
            'dates' => [['date_applied' => '2027-03-05', 'hours' => 8, 'leave_type_id' => $this->typeId]],
            'applicant_signature' => $this->sig,
        ]);

        $this->assertEquals(9, $this->balance());
        $this->assertCount(2, $this->entries()->where('transaction_type', LeaveLedgerService::RESTORED));
        $this->assertSame('2027-03-05', $this->entries()->last()->effective_date);
        $this->assertLedgerMatches();
    }

    /** @test */
    public function deleting_a_leave_returns_its_credits()
    {
        $appId = $this->file(['2027-03-01']);
        app(LeaveApplicationController::class)->delete(new Request(), $appId);

        $this->assertEquals(10, $this->balance());
        $this->assertLedgerMatches();
    }

    /** @test */
    public function a_rejection_that_is_overturned_is_charged_again()
    {
        $appId = $this->file(['2027-03-01']);
        $this->act($appId, 'SH', 'rejected');
        $this->assertEquals(10, $this->balance());

        $this->actAs('DH');
        app(LeaveApprovalService::class)->updateApprovalStatus($appId, null, null, 'approved', [], $this->sig, true);

        $this->assertEquals(9, $this->balance());
        $this->assertSame(['consumed', 'restored', 'consumed'], $this->entries()->pluck('transaction_type')->all());
        $this->assertLedgerMatches();
    }

    /** @test */
    public function a_dh_disapproved_date_is_returned_on_its_own_date()
    {
        $appId = $this->file(['2027-03-01', '2027-03-02']);
        [$first] = $this->dateIds($appId);
        $this->act($appId, 'SH', 'approved');
        $this->act($appId, 'DH', 'approved', [$first]);

        $this->assertEquals(9, $this->balance());
        $restored = $this->entries()->where('transaction_type', LeaveLedgerService::RESTORED)->first();
        $this->assertSame('2027-03-01', $restored->effective_date);
        $this->assertLedgerMatches();
    }

    /** @test */
    public function a_cancelled_date_is_returned()
    {
        $appId = $this->file(['2027-03-01', '2027-03-02']);
        $this->act($appId, 'SH', 'approved');
        $this->act($appId, 'DH', 'approved');
        $this->act($appId, 'HR', 'approved');
        $this->actAs('EMP');
        $dates = $this->dateIds($appId);

        $id = app(LeaveCancellationService::class)->fileCancellation(['date_ids' => [$dates[1]], 'reason' => 'test']);
        foreach (['SH', 'DH', 'HR'] as $who) {
            $this->actAs($who);
            app(LeaveCancellationService::class)->updateApprovalStatus($id, 'approved', null);
        }

        $this->assertEquals(9, $this->balance());
        $this->assertSame('2027-03-02', $this->entries()->where('transaction_type', LeaveLedgerService::RESTORED)->first()->effective_date);
        $this->assertLedgerMatches();
    }

    /** @test */
    public function sick_leave_falling_back_to_vacation_records_each_balance()
    {
        $sl = (int) DB::table('hris.leave_types')->where('code', 'SL')->value('id');
        $vl = (int) DB::table('hris.leave_types')->where('code', 'VL')->value('id');
        foreach ([$sl => 0.5, $vl => 5] as $type => $credit) {
            DB::table('hris.leave_credits')->insert(['user_id' => $this->employeeId, 'leave_id' => $type, 'based_credit' => $credit, 'used_credit' => 0, 'last_updated_month' => '2027-1']);
        }

        app(LeaveApplicationService::class)->insertLeaveApplication([
            'dates' => [['date_applied' => '2027-03-01', 'hours' => 8, 'leave_type_id' => $sl]],
            'details_map' => [(string) $sl => ['detail' => 'Out Patient (Specify Illness)', 'extra' => 'fever (Non-Work Related)']],
            'applicant_signature' => $this->sig,
        ]);

        $this->assertEquals(0.5, $this->entries($sl)->first()->consumed_credits);
        $this->assertEquals(0.5, $this->entries($vl)->first()->consumed_credits);
        $this->assertSame('2027-03-01', $this->entries($vl)->first()->effective_date);
    }

    /** @test */
    public function monetization_and_its_rejection_are_recorded()
    {
        $id = app(LeaveMonetizationService::class)->store(['leave_type_id' => $this->typeId, 'days_requested' => 2]);
        $this->assertEquals(7.5, $this->balance());
        $this->assertLedgerMatches();

        $this->actAs('SH');
        app(LeaveMonetizationService::class)->updateApprovalStatus($id, null, 'rejected');

        $this->assertEquals(10, $this->balance());
        $this->assertSame(['monetized', 'restored'], $this->entries()->pluck('transaction_type')->all());
        $this->assertLedgerMatches();
    }

    /** @test */
    public function a_dh_undo_charges_the_disapproved_date_again()
    {
        $appId = $this->file(['2027-03-01', '2027-03-02']);
        [$first] = $this->dateIds($appId);
        $this->act($appId, 'SH', 'approved');
        $this->act($appId, 'DH', 'approved', [$first]);
        $this->assertEquals(9, $this->balance());

        app(LeaveApprovalService::class)->undoApproval($appId);

        $this->assertEquals(8, $this->balance());
        $this->assertSame(['consumed', 'consumed', 'restored', 'consumed'], $this->entries()->pluck('transaction_type')->all());
        $this->assertSame('2027-03-01', $this->entries()->last()->effective_date);
        $this->assertLedgerMatches();
    }

    /** @test */
    public function the_bulk_credit_page_records_each_credit()
    {
        DB::table('hris.employees')->updateOrInsert(['user_id' => $this->employeeId], ['employee_type' => 'Permanent']);

        $this->withoutMiddleware()->postJson('/bulk-leave-credits', [
            'user_ids' => [$this->employeeId],
            'credits' => [['leave_type_id' => $this->typeId, 'value' => 1.5]],
        ])->assertOk();

        $this->assertEquals(11.5, $this->balance());
        $entry = $this->entries()->first();
        $this->assertSame(LeaveLedgerService::ADJUSTMENT, $entry->transaction_type);
        $this->assertSame('leave_credit_batches', $entry->source_type);
        $this->assertLedgerMatches();
    }

    /** @test */
    public function manual_edits_are_recorded_as_adjustments()
    {
        $this->withoutMiddleware();
        $this->putJson('/update_base_credit', ['employee_id' => $this->employeeId, 'leave_id' => $this->typeId, 'based_credit' => 12.5])->assertOk();
        $this->putJson('/update_used_credit', ['employee_id' => $this->employeeId, 'leave_id' => $this->typeId, 'used_credit' => 1])->assertOk();

        $this->assertEquals(11.5, $this->balance());
        $this->assertSame(['adjustment', 'adjustment'], $this->entries()->pluck('transaction_type')->all());
        $this->assertEquals(2.5, $this->entries()[0]->adjustments);
        $this->assertEquals(-1, $this->entries()[1]->adjustments);
        $this->assertLedgerMatches();
    }

    /** @test */
    public function cto_from_overtime_is_recorded()
    {
        DB::table('hris.leave_types')->updateOrInsert(['code' => 'CTO'], ['description' => 'CTO', 'monthly_earned' => 0, 'status' => 'active']);
        $cto = (int) DB::table('hris.leave_types')->where('code', 'CTO')->value('id');

        $method = new \ReflectionMethod(OvertimeServices::class, 'awardCtoCredits');
        $method->setAccessible(true);
        $method->invoke(app(OvertimeServices::class), $this->employeeId, '17:00:00', '18:00:00');

        $entry = $this->entries($cto)->first();
        $this->assertSame(LeaveLedgerService::EARNED, $entry->transaction_type);
        $this->assertEquals($this->balance($cto), (float) $entry->ending_balance);
    }

    /** @test */
    public function certifying_adds_back_leave_filed_for_later_dates_and_reconciles()
    {
        $this->file(['2027-03-01']);        // on or before the as-of date
        $this->file(['2027-04-05']);        // after it, already charged
        $this->assertEquals(8, $this->balance());

        $service = app(LeaveOpeningBalanceService::class);
        $row = $service->preview('2027-03-31', [$this->employeeId])->first();
        $this->assertSame('ready', $row['status']);
        $this->assertEquals(9, collect($row['balances'])->firstWhere('leave_type_id', $this->typeId)['opening']);

        $result = $service->certify('2027-03-31', [$this->employeeId], $this->hrUserId);
        $this->assertSame(1, $result['certified']);

        $opening = $this->entries()->firstWhere('transaction_type', LeaveLedgerService::OPENING);
        $this->assertEquals(9, $opening->ending_balance);
        $this->assertSame('2027-03-31', $opening->effective_date);
        $this->assertSame($this->hrUserId, (int) $opening->finalized_by);

        $check = $service->reconcile();
        $this->assertSame([], collect($check['differences'])->where('code', 'LEDTEST')->values()->all());
    }

    /** @test */
    public function an_employee_cannot_be_certified_twice()
    {
        $service = app(LeaveOpeningBalanceService::class);
        $service->certify('2027-03-31', [$this->employeeId], $this->hrUserId);

        $this->assertSame('certified', $service->preview('2027-04-30', [$this->employeeId])->first()['status']);
        $this->assertSame(['certified' => 0, 'skipped' => 1], $service->certify('2027-04-30', [$this->employeeId], $this->hrUserId));
        $this->assertCount(1, $this->entries()->where('transaction_type', LeaveLedgerService::OPENING));
    }

    /** @test */
    public function certifying_before_a_month_already_posted_is_blocked()
    {
        DB::table('hris.credit_earned_logs')->insert([
            'user_id' => $this->employeeId, 'month' => 5, 'year' => 2027, 'total_lateundertime_minutes' => 0, 'leave_type_id' => $this->typeId, 'earned_points' => 1.25,
        ]);

        $service = app(LeaveOpeningBalanceService::class);
        $this->assertSame('blocked', $service->preview('2027-04-30', [$this->employeeId])->first()['status']);
        $this->assertSame('ready', $service->preview('2027-05-31', [$this->employeeId])->first()['status']);
    }

    /** @test */
    public function later_changes_keep_the_ledger_reconciled_and_a_direct_edit_is_caught()
    {
        $service = app(LeaveOpeningBalanceService::class);
        $service->certify('2027-02-28', [$this->employeeId], $this->hrUserId);

        $appId = $this->file(['2027-03-01', '2027-03-02']);
        $this->act($appId, 'SH', 'rejected');
        $this->assertSame([], collect($service->reconcile()['differences'])->where('code', 'LEDTEST')->values()->all());

        DB::table('hris.leave_credits')->where('user_id', $this->employeeId)->where('leave_id', $this->typeId)->increment('based_credit', 3);

        $difference = collect($service->reconcile()['differences'])->firstWhere('code', 'LEDTEST');
        $this->assertEquals(3, $difference['difference']);
    }

    /** @test */
    public function only_holders_of_the_permission_can_certify()
    {
        $code = 'leave.certify_opening';
        $permission = DB::table('hris.permissions')->where('code', $code)->value('id');
        $this->assertTrue(DB::table('hris.access_level_permissions')->where('level', 'hrh')->where('permission_id', $permission)->exists());

        DB::table('tdh_user.user_priv')->updateOrInsert(['user_id' => $this->hrUserId, 'syscode' => 'hris'], ['level' => 'standard']);
        $service = new \App\Services\PermissionService();
        $this->assertFalse($service->can($this->hrUserId, $code));

        // A non-HRH can be given it individually.
        DB::table('hris.user_permissions')->insert(['user_id' => $this->hrUserId, 'permission_id' => $permission, 'type' => 'grant', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertTrue((new \App\Services\PermissionService())->can($this->hrUserId, $code));

        DB::table('tdh_user.user_priv')->where('user_id', $this->hrUserId)->delete();
    }

    /** @test */
    public function the_first_snapshot_starts_from_the_certified_opening_and_counts_leave_by_its_date()
    {
        config(['hris.cutoff_snapshots_enabled' => true]);
        DB::table('hris.leave_types')->where('id', $this->typeId)->update(['monthly_earned' => 1]);
        $this->file(['2027-03-02']);   // inside the cutoff, filed before certifying
        $this->file(['2027-04-06']);   // after the cutoff

        app(LeaveOpeningBalanceService::class)->certify('2027-02-28', [$this->employeeId], $this->hrUserId);

        $cutoffId = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title' => 'Ledger Cutoff ' . uniqid(), 'status' => 'draft', 'period_start' => '2027-03-01', 'period_end' => '2027-03-31',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('hris.cutoff_summaries')->insert([
            'employee_id' => $this->employeeId, 'cutoff_id' => $cutoffId, 'period_start' => '2027-03-01', 'period_end' => '2027-03-31',
            'total_working_days' => 23, 'days_present' => 0, 'days_absent' => 0, 'late_minutes' => 0, 'undertime_minutes' => 0, 'lwop_days' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Approve the March leave so it counts as used.
        DB::table('hris.leave_applications')->where('user_id', $this->employeeId)->update(['status' => 'approved']);
        app(\App\Services\CutoffSnapshotService::class)->generate($cutoffId, $this->hrUserId);

        $balance = DB::table('hris.cutoff_leave_balance_snapshots')->where(['cutoff_id' => $cutoffId, 'leave_type_id' => $this->typeId])->first();
        $this->assertEquals(10, $balance->beginning_balance); // 8 today + 2 filed ahead
        $this->assertEquals(1, $balance->leave_used);         // March only; the April leave isn't this cutoff's
        $this->assertEquals(9, $balance->ending_balance);
        $this->assertSame('opening_balance', $balance->opening_source);
    }

    /** @test */
    public function other_balance_movements_appear_as_snapshot_adjustments()
    {
        config(['hris.cutoff_snapshots_enabled' => true]);
        DB::table('hris.leave_types')->where('id', $this->typeId)->update(['monthly_earned' => 1]);
        $asOf = now()->subMonths(2)->endOfMonth()->toDateString();
        app(LeaveOpeningBalanceService::class)->certify($asOf, [$this->employeeId], $this->hrUserId);

        DB::table('hris.leave_credits')->where('user_id', $this->employeeId)->where('leave_id', $this->typeId)->increment('based_credit', 2);
        app(LeaveLedgerService::class)->record($this->employeeId, $this->typeId, LeaveLedgerService::ADJUSTMENT, 2, now()->toDateString(), 'leave_credit_batches', 1);

        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();
        $cutoffId = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title' => 'Adj Cutoff ' . uniqid(), 'status' => 'draft', 'period_start' => $start, 'period_end' => $end, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('hris.cutoff_summaries')->insert([
            'employee_id' => $this->employeeId, 'cutoff_id' => $cutoffId, 'period_start' => $start, 'period_end' => $end,
            'total_working_days' => 20, 'days_present' => 0, 'days_absent' => 0, 'late_minutes' => 0, 'undertime_minutes' => 0, 'lwop_days' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        app(\App\Services\CutoffSnapshotService::class)->generate($cutoffId, $this->hrUserId);

        $balance = DB::table('hris.cutoff_leave_balance_snapshots')->where(['cutoff_id' => $cutoffId, 'leave_type_id' => $this->typeId])->first();
        $this->assertEquals(2, $balance->adjustments);
        $this->assertEquals(12, $balance->ending_balance);
    }
}
