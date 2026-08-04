<?php

namespace Tests\Feature;

use App\Models\PassSlip;
use App\Services\PassSlipService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PassSlipOnBehalfTest extends TestCase
{
    use DatabaseTransactions;

    private int $employeeId = 9301001;
    private int $otherSectionEmployeeId = 9301002;
    private int $shUserId = 9301003;
    private int $dhUserId = 9301004;
    private int $sectionId = 8301001;
    private int $otherSectionId = 8301002;
    private int $divisionId = 9401001;

    private ?int $mockedUserId = null;

    private bool $authMocked = false;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('hris.pass_slips')->whereIn('user_id', [$this->employeeId, $this->otherSectionEmployeeId, $this->shUserId, $this->dhUserId])->delete();

        // Inserted via the `users` connection (not the default `mysql` connection's
        // cross-database `tdh_user.users` table reference) so these rows are genuinely
        // committed immediately. DatabaseTransactions only wraps the default connection,
        // so writes through the default connection stay invisible to the separate `users`
        // connection (which App\Models\User reads from) until the wrapping transaction
        // commits -- which never happens in tests. See OrgHierarchyServiceTest for the
        // established sibling pattern of avoiding the Eloquent User model in test reads;
        // here we instead make the fixture itself connection-agnostic.
        DB::connection('users')->table('users')->insertOrIgnore([
            ['id' => $this->shUserId, 'fname' => 'SH', 'lname' => 'PassTest', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'sh_pass_test', 'password' => 'x'],
            ['id' => $this->dhUserId, 'fname' => 'DH', 'lname' => 'PassTest', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'dh_pass_test', 'password' => 'x'],
            ['id' => $this->employeeId, 'fname' => 'Emp', 'lname' => 'PassTest', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'emp_pass_test', 'password' => 'x'],
            ['id' => $this->otherSectionEmployeeId, 'fname' => 'Other', 'lname' => 'PassTest', 'section' => $this->otherSectionId, 'division' => 0, 'username' => 'other_pass_test', 'password' => 'x'],
        ]);

        DB::connection('users')->table('division')->insertOrIgnore([
            ['id' => $this->divisionId, 'description' => 'PassTestDivision', 'head' => $this->dhUserId, 'code' => 'PTD'],
        ]);

        DB::connection('users')->table('section')->insertOrIgnore([
            ['id' => $this->sectionId, 'description' => 'PassTestSection', 'head' => $this->shUserId, 'division' => $this->divisionId, 'code' => 'PTS'],
            ['id' => $this->otherSectionId, 'description' => 'PassTestOtherSection', 'head' => 0, 'division' => 0, 'code' => 'PTO'],
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('hris.pass_slips')->whereIn('user_id', [$this->employeeId, $this->otherSectionEmployeeId, $this->shUserId, $this->dhUserId])->delete();
        DB::connection('users')->table('section')->whereIn('id', [$this->sectionId, $this->otherSectionId])->delete();
        DB::connection('users')->table('division')->where('id', $this->divisionId)->delete();
        DB::connection('users')->table('users')->whereIn('id', [$this->shUserId, $this->dhUserId, $this->employeeId, $this->otherSectionEmployeeId])->delete();
        parent::tearDown();
    }

    private function actAs(int $userId, bool $isAdmin = false): void
    {
        $this->mockedUserId = $userId;

        // Auth::shouldReceive() stacks a new expectation onto the SAME underlying mock on
        // every call (Facade::isMock() reuses the swapped instance). Mockery matches
        // open-ended (no ->once()/->times()) expectations in registration order and the
        // first one never becomes ineligible, so a second `shouldReceive('id')->andReturn()`
        // call is silently unreachable -- Auth::id() keeps returning the value from the
        // FIRST actAs() call for the rest of the test. Registering the expectation once
        // with andReturnUsing() and mutating the backing property on each actAs() call
        // avoids that trap while still letting a single test switch identities mid-test.
        if (!$this->authMocked) {
            Auth::shouldReceive('id')->andReturnUsing(fn () => $this->mockedUserId);
            Auth::shouldReceive('check')->andReturn(true);
            $this->authMocked = true;
        }

        session(['access_rights' => $isAdmin ? 'admin' : 'employee']);
    }

    /** @test */
    public function self_filing_is_unaffected_by_the_new_target_parameter()
    {
        $this->actAs($this->employeeId);
        $service = app(PassSlipService::class);

        $slip = $service->store([
            'slip_date' => '2026-08-04',
            'time_out' => '13:00',
            'time_in' => '14:00',
            'type' => 'Personal',
        ]);

        $this->assertEquals($this->employeeId, $slip->user_id);
        $this->assertNull($slip->filed_by);
    }

    /** @test */
    public function section_head_can_file_for_a_managed_employee_and_it_is_auto_approved()
    {
        $this->actAs($this->shUserId);
        $service = app(PassSlipService::class);

        $slip = $service->store([
            'slip_date' => '2026-08-04',
            'time_out' => '13:00',
            'time_in' => '14:00',
            'type' => 'Official',
        ], $this->employeeId);

        $this->assertEquals($this->employeeId, $slip->user_id);
        $this->assertEquals($this->shUserId, $slip->filed_by);
        $this->assertEquals('approved', $slip->status);
        $this->assertEquals($this->shUserId, $slip->approver_id);
        $this->assertNotNull($slip->approved_at);
    }

    /** @test */
    public function section_head_cannot_file_for_an_employee_outside_their_section()
    {
        $this->actAs($this->shUserId);
        $service = app(PassSlipService::class);

        $this->expectException(ValidationException::class);

        $service->store([
            'slip_date' => '2026-08-04',
            'time_out' => '13:00',
            'time_in' => '14:00',
            'type' => 'Official',
        ], $this->otherSectionEmployeeId);
    }

    /** @test */
    public function division_head_can_file_for_any_employee_in_their_division()
    {
        $this->actAs($this->dhUserId);
        $service = app(PassSlipService::class);

        $slip = $service->store([
            'slip_date' => '2026-08-04',
            'time_out' => '13:00',
            'time_in' => '14:00',
            'type' => 'Official',
        ], $this->employeeId);

        $this->assertEquals($this->dhUserId, $slip->filed_by);
        $this->assertEquals('approved', $slip->status);
    }

    /** @test */
    public function employee_cannot_delete_a_filing_made_on_their_behalf()
    {
        $this->actAs($this->shUserId);
        $service = app(PassSlipService::class);
        $slip = $service->store([
            'slip_date' => '2026-08-04',
            'time_out' => '13:00',
            'time_in' => '14:00',
            'type' => 'Official',
        ], $this->employeeId);

        $this->actAs($this->employeeId);
        $deleted = $service->destroy($slip->id);

        $this->assertFalse($deleted);
        $this->assertNotNull(PassSlip::find($slip->id));
    }

    /** @test */
    public function filing_head_can_delete_their_own_on_behalf_filing()
    {
        $this->actAs($this->shUserId);
        $service = app(PassSlipService::class);
        $slip = $service->store([
            'slip_date' => '2026-08-04',
            'time_out' => '13:00',
            'time_in' => '14:00',
            'type' => 'Official',
        ], $this->employeeId);

        $deleted = $service->destroy($slip->id);

        $this->assertTrue($deleted);
        $this->assertNull(PassSlip::find($slip->id));
    }

    /** @test */
    public function admin_can_delete_any_filing()
    {
        $this->actAs($this->shUserId);
        $service = app(PassSlipService::class);
        $slip = $service->store([
            'slip_date' => '2026-08-04',
            'time_out' => '13:00',
            'time_in' => '14:00',
            'type' => 'Official',
        ], $this->employeeId);

        $this->actAs($this->dhUserId, true);
        $deleted = $service->destroy($slip->id);

        $this->assertTrue($deleted);
    }

    /** @test */
    public function employees_own_list_includes_filings_made_on_their_behalf_with_filer_loaded()
    {
        $this->actAs($this->shUserId);
        $service = app(PassSlipService::class);
        $service->store([
            'slip_date' => '2026-08-04',
            'time_out' => '13:00',
            'time_in' => '14:00',
            'type' => 'Official',
        ], $this->employeeId);

        $this->actAs($this->employeeId);
        $slips = $service->getUserPassSlips();

        $this->assertCount(1, $slips);
        $this->assertEquals($this->shUserId, $slips->first()->filedBy->id);
    }

    /** @test */
    public function section_heads_review_queue_includes_their_own_auto_approved_on_behalf_filing()
    {
        $this->actAs($this->shUserId);
        $service = app(PassSlipService::class);
        $slip = $service->store([
            'slip_date' => '2026-08-04',
            'time_out' => '13:00',
            'time_in' => '14:00',
            'type' => 'Official',
        ], $this->employeeId);

        $result = $service->getPendingSlips();

        $found = $result->getCollection()->firstWhere('id', $slip->id);
        $this->assertNotNull($found);
        $this->assertEquals('approved', $found->status);
    }
}
