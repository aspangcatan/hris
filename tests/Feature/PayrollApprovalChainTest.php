<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PayrollApprovalChainTest extends TestCase
{
    use DatabaseTransactions;

    private int $preparerId = 9600;
    private int $approver1Id = 9601;
    private int $approver2Id = 9602;
    private int $approver3Id = 9603;
    private array $assignatorySnapshot = [];
    private ?int $payrollId = null;
    private ?int $masterId = null;
    private ?int $currentActorId = null;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);

        $this->assignatorySnapshot = DB::table('payroll_assignatories')->get()->map(fn($row) => (array) $row)->all();
        DB::table('payroll_assignatories')->delete();

        foreach ([$this->preparerId, $this->approver1Id, $this->approver2Id, $this->approver3Id] as $id) {
            DB::table('tdh_user.users')->where('id', $id)->delete();
            DB::table('tdh_user.users')->insert([
                'id' => $id,
                'fname' => 'Approver',
                'lname' => 'User' . $id,
                'username' => 'approveruser' . $id,
                'password' => 'x',
                'division' => 2,
                'section' => 21,
                'designation' => 52,
            ]);
        }
    }

    protected function tearDown(): void
    {
        if ($this->payrollId) {
            DB::table('payroll_approvals')->where('payroll_id', $this->payrollId)->delete();
            DB::table('payroll_employee_components')->whereIn(
                'payroll_employee_id',
                DB::table('payroll_employees')->where('payroll_id', $this->payrollId)->pluck('id')
            )->delete();
            DB::table('payroll_employees')->where('payroll_id', $this->payrollId)->delete();
            DB::table('payrolls')->where('id', $this->payrollId)->delete();
        }
        if ($this->masterId) {
            DB::table('hris.cutoff_summaries')->where('cutoff_id', $this->masterId)->delete();
            DB::table('hris.cutoff_summaries_master')->where('id', $this->masterId)->delete();
        }
        DB::table('payroll_assignatories')->delete();
        if (!empty($this->assignatorySnapshot)) {
            DB::table('payroll_assignatories')->insert($this->assignatorySnapshot);
        }
        foreach ([$this->preparerId, $this->approver1Id, $this->approver2Id, $this->approver3Id] as $id) {
            DB::table('tdh_user.users')->where('id', $id)->delete();
        }
        parent::tearDown();
    }

    private function actAs(int $userId, string $accessRights = 'admin'): void
    {
        // Auth::shouldReceive() only swaps the facade root for a Mockery mock
        // on the FIRST call; every call after that just stacks another
        // expectation onto the same mock. Since these tests switch the acting
        // user mid-test (preparer -> approver1 -> approver2), a plain
        // ->andReturn($userId) on each call would never override the first
        // one Mockery registered — Auth::id() would keep returning whichever
        // user was mocked first. Instead, register the returning-a-closure
        // expectation once, and have every actAs() call just update the
        // $currentActorId property the closure reads from.
        if (!Auth::getFacadeRoot() instanceof \Mockery\MockInterface) {
            Auth::shouldReceive('id')->andReturnUsing(fn () => $this->currentActorId);
            Auth::shouldReceive('check')->andReturn(true);
            // Stubbed defensively even though the rewritten submitForApproval() no
            // longer calls Auth::user() — an unstubbed call on a Mockery-replaced
            // facade throws instead of returning null (see PayrollCutoffTest's
            // actAsUser(), which does the same for this reason).
            Auth::shouldReceive('user')->andReturn(null);
        }

        $this->currentActorId = $userId;
        session(['access_rights' => $accessRights]);
    }

    private function seedAssignatories(array $rows): void
    {
        foreach ($rows as $row) {
            DB::table('payroll_assignatories')->insert(array_merge($row, [
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function seedComputedPayroll(): int
    {
        DB::table('hris.cutoff_summaries_master')
            ->where('period_start', '2027-02-01')
            ->where('period_end', '2027-02-15')
            ->where('title', 'Assignatory Test Cutoff')
            ->delete();
        $this->masterId = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title' => 'Assignatory Test Cutoff',
            'period_start' => '2027-02-01',
            'period_end' => '2027-02-15',
            'generated_by' => $this->preparerId,
            'generated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(\App\Services\PayrollServices::class)->generateCutoffSummary(
            $this->preparerId,
            '2027-02-01',
            '2027-02-15',
            'Assignatory Test Cutoff',
            $this->preparerId
        );

        $this->actAs($this->preparerId, 'admin');

        $saveResponse = $this->withoutMiddleware()->postJson('/save_payroll', [
            'title' => 'Assignatory Test Payroll',
            'cutoff_id' => $this->masterId,
            'payroll_type' => 'Regular',
            'rate_type' => 'Non Pro-rated',
        ]);
        $saveResponse->assertOk();

        $payroll = DB::table('payrolls')->where('title', 'Assignatory Test Payroll')->latest('id')->first();
        $this->payrollId = $payroll->id;

        $this->withoutMiddleware()->postJson('/save_payroll_employee', [
            'payroll_id' => $this->payrollId,
            'employees' => [['userid' => $this->preparerId]],
        ])->assertOk();

        return $this->payrollId;
    }

    /** @test */
    public function submit_for_approval_fails_when_no_assignatories_configured(): void
    {
        $payrollId = $this->seedComputedPayroll();

        $response = $this->withoutMiddleware()->postJson('/payroll/submit_for_approval', [
            'payroll_id' => $payrollId,
        ]);

        $response->assertStatus(422);
        $this->assertEquals(0, DB::table('payroll_approvals')->where('payroll_id', $payrollId)->count());
    }

    /** @test */
    public function submit_for_approval_creates_one_row_per_configured_assignatory_in_order(): void
    {
        $this->seedAssignatories([
            ['order_no' => 2, 'label' => 'Division Head', 'user_id' => $this->approver2Id],
            ['order_no' => 1, 'label' => 'Section Head', 'user_id' => $this->approver1Id],
            ['order_no' => 3, 'label' => 'HR', 'user_id' => $this->approver3Id],
        ]);
        $payrollId = $this->seedComputedPayroll();

        $this->withoutMiddleware()->postJson('/payroll/submit_for_approval', [
            'payroll_id' => $payrollId,
        ])->assertOk();

        $approvals = DB::table('payroll_approvals')->where('payroll_id', $payrollId)->orderBy('order_no')->get();
        $this->assertCount(3, $approvals);
        $this->assertEquals(['Section Head', 'Division Head', 'HR'], $approvals->pluck('label')->toArray());
        $this->assertEquals([$this->approver1Id, $this->approver2Id, $this->approver3Id], $approvals->pluck('approver_id')->map(fn($v) => (int) $v)->toArray());
        $this->assertEquals(['pending', 'pending', 'pending'], $approvals->pluck('status')->toArray());
    }

    /** @test */
    public function later_phase_approver_cannot_act_before_an_earlier_phase_is_approved(): void
    {
        $this->seedAssignatories([
            ['order_no' => 1, 'label' => 'Section Head', 'user_id' => $this->approver1Id],
            ['order_no' => 2, 'label' => 'Division Head', 'user_id' => $this->approver2Id],
        ]);
        $payrollId = $this->seedComputedPayroll();
        $this->withoutMiddleware()->postJson('/payroll/submit_for_approval', ['payroll_id' => $payrollId])->assertOk();

        $this->actAs($this->approver2Id, 'standard');

        $response = $this->withoutMiddleware()->postJson('/payroll/approval_action', [
            'payroll_id' => $payrollId,
            'status' => 'approved',
        ]);

        $response->assertStatus(403);
        $this->assertEquals(
            'pending',
            DB::table('payroll_approvals')->where('payroll_id', $payrollId)->where('order_no', 2)->value('status')
        );
    }

    /** @test */
    public function each_named_approver_can_act_only_on_their_own_step_in_sequence(): void
    {
        $this->seedAssignatories([
            ['order_no' => 1, 'label' => 'Section Head', 'user_id' => $this->approver1Id],
            ['order_no' => 2, 'label' => 'Division Head', 'user_id' => $this->approver2Id],
        ]);
        $payrollId = $this->seedComputedPayroll();
        $this->withoutMiddleware()->postJson('/payroll/submit_for_approval', ['payroll_id' => $payrollId])->assertOk();

        $this->actAs($this->approver1Id, 'standard');
        $this->withoutMiddleware()->postJson('/payroll/approval_action', [
            'payroll_id' => $payrollId,
            'status' => 'approved',
        ])->assertOk();

        $this->actAs($this->approver2Id, 'standard');
        $this->withoutMiddleware()->postJson('/payroll/approval_action', [
            'payroll_id' => $payrollId,
            'status' => 'approved',
        ])->assertOk();

        $payroll = DB::table('payrolls')->where('id', $payrollId)->first();
        $this->assertEquals('approved', strtolower($payroll->status));
    }

    /** @test */
    public function rejection_cascades_to_remaining_pending_steps(): void
    {
        $this->seedAssignatories([
            ['order_no' => 1, 'label' => 'Section Head', 'user_id' => $this->approver1Id],
            ['order_no' => 2, 'label' => 'Division Head', 'user_id' => $this->approver2Id],
        ]);
        $payrollId = $this->seedComputedPayroll();
        $this->withoutMiddleware()->postJson('/payroll/submit_for_approval', ['payroll_id' => $payrollId])->assertOk();

        $this->actAs($this->approver1Id, 'standard');
        $this->withoutMiddleware()->postJson('/payroll/approval_action', [
            'payroll_id' => $payrollId,
            'status' => 'rejected',
            'remarks' => 'Numbers look off.',
        ])->assertOk();

        $statuses = DB::table('payroll_approvals')->where('payroll_id', $payrollId)->orderBy('order_no')->pluck('status');
        $this->assertEquals(['rejected', 'rejected'], $statuses->toArray());

        $payroll = DB::table('payrolls')->where('id', $payrollId)->first();
        $this->assertEquals('rejected', strtolower($payroll->status));
    }

    /** @test */
    public function admin_session_can_approve_any_step_regardless_of_assigned_approver(): void
    {
        $this->seedAssignatories([
            ['order_no' => 1, 'label' => 'Section Head', 'user_id' => $this->approver1Id],
        ]);
        $payrollId = $this->seedComputedPayroll();
        $this->withoutMiddleware()->postJson('/payroll/submit_for_approval', ['payroll_id' => $payrollId])->assertOk();

        // Preparer is acting as admin (set up in seedComputedPayroll), not approver1Id.
        $this->withoutMiddleware()->postJson('/payroll/approval_action', [
            'payroll_id' => $payrollId,
            'status' => 'approved',
        ])->assertOk();

        $payroll = DB::table('payrolls')->where('id', $payrollId)->first();
        $this->assertEquals('approved', strtolower($payroll->status));
    }
}
