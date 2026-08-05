<?php

namespace Tests\Feature;

use App\Services\HospitalOrderServices;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HospitalOrderOnBehalfTest extends TestCase
{
    use DatabaseTransactions;

    private int $employeeId = 9701001;
    private int $otherSectionEmployeeId = 9701002;
    private int $shUserId = 9701003;
    private int $dhUserId = 9701004;
    private int $sectionId = 8701001;
    private int $otherSectionId = 8701002;
    private int $divisionId = 9801001;
    private int $hoId;

    private ?int $mockedUserId = null;

    private bool $authMocked = false;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tdh_user.users')->insertOrIgnore([
            ['id' => $this->shUserId, 'fname' => 'SH', 'lname' => 'HoTest', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'sh_ho_test', 'password' => 'x'],
            ['id' => $this->dhUserId, 'fname' => 'DH', 'lname' => 'HoTest', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'dh_ho_test', 'password' => 'x'],
            ['id' => $this->employeeId, 'fname' => 'Emp', 'lname' => 'HoTest', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'emp_ho_test', 'password' => 'x'],
            ['id' => $this->otherSectionEmployeeId, 'fname' => 'Other', 'lname' => 'HoTest', 'section' => $this->otherSectionId, 'division' => 0, 'username' => 'other_ho_test', 'password' => 'x'],
        ]);

        DB::table('tdh_user.division')->insertOrIgnore([
            ['id' => $this->divisionId, 'description' => 'HoTestDivision', 'head' => $this->dhUserId, 'code' => 'HTD'],
        ]);

        DB::table('tdh_user.section')->insertOrIgnore([
            ['id' => $this->sectionId, 'description' => 'HoTestSection', 'head' => $this->shUserId, 'division' => $this->divisionId, 'code' => 'HTS'],
            ['id' => $this->otherSectionId, 'description' => 'HoTestOtherSection', 'head' => 0, 'division' => 0, 'code' => 'HTO'],
        ]);

        $this->hoId = DB::table('hris.hospital_orders')->insertGetId([
            'order_number' => 'HO-TEST-' . uniqid(),
            'order_date' => '2026-08-04',
            'time_from' => '08:00',
            'time_to' => '17:00',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-10',
            'created_by' => $this->shUserId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('hris.hospital_order_employees')->where('ho_id', $this->hoId)->delete();
        DB::table('hris.hospital_orders')->where('id', $this->hoId)->delete();
        DB::table('tdh_user.section')->whereIn('id', [$this->sectionId, $this->otherSectionId])->delete();
        DB::table('tdh_user.division')->where('id', $this->divisionId)->delete();
        DB::table('tdh_user.users')->whereIn('id', [$this->shUserId, $this->dhUserId, $this->employeeId, $this->otherSectionEmployeeId])->delete();
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
        // See PassSlipOnBehalfTest for the established sibling pattern.
        if (!$this->authMocked) {
            Auth::shouldReceive('id')->andReturnUsing(fn () => $this->mockedUserId);
            Auth::shouldReceive('check')->andReturn(true);
            $this->authMocked = true;
        }

        session(['access_rights' => $isAdmin ? 'admin' : 'employee']);
    }

    /** @test */
    public function file_for_self_is_unaffected_and_stays_pending()
    {
        $this->actAs($this->employeeId);
        $service = app(HospitalOrderServices::class);

        $service->fileForSelf($this->hoId, '2026-08-05', '08:00', '17:00');

        $row = DB::table('hris.hospital_order_employees')
            ->where('ho_id', $this->hoId)->where('userid', $this->employeeId)->first();

        $this->assertNotNull($row);
        $this->assertNull($row->filed_by);
        $this->assertEquals('pending', $row->status);
    }

    /** @test */
    public function section_head_can_file_for_a_managed_employee_and_it_is_auto_approved()
    {
        $this->actAs($this->shUserId);
        $service = app(HospitalOrderServices::class);

        $service->fileForEmployee($this->hoId, $this->employeeId, '2026-08-05', '08:00', '17:00');

        $row = DB::table('hris.hospital_order_employees')
            ->where('ho_id', $this->hoId)->where('userid', $this->employeeId)->first();

        $this->assertEquals($this->shUserId, $row->filed_by);
        $this->assertEquals('approved', $row->status);
        $this->assertEquals($this->shUserId, $row->reviewed_by);
        $this->assertNotNull($row->reviewed_at);
    }

    /** @test */
    public function section_head_cannot_file_for_an_employee_outside_their_section()
    {
        $this->actAs($this->shUserId);
        $service = app(HospitalOrderServices::class);

        $this->expectException(ValidationException::class);

        $service->fileForEmployee($this->hoId, $this->otherSectionEmployeeId, '2026-08-05', '08:00', '17:00');
    }

    /** @test */
    public function division_head_can_file_for_any_employee_in_their_division()
    {
        $this->actAs($this->dhUserId);
        $service = app(HospitalOrderServices::class);

        $service->fileForEmployee($this->hoId, $this->employeeId, '2026-08-05', '08:00', '17:00');

        $row = DB::table('hris.hospital_order_employees')
            ->where('ho_id', $this->hoId)->where('userid', $this->employeeId)->first();

        $this->assertEquals($this->dhUserId, $row->filed_by);
        $this->assertEquals('approved', $row->status);
    }

    /** @test */
    public function employee_cannot_delete_a_filing_made_on_their_behalf()
    {
        $this->actAs($this->shUserId);
        $service = app(HospitalOrderServices::class);
        $service->fileForEmployee($this->hoId, $this->employeeId, '2026-08-05', '08:00', '17:00');
        $filingId = DB::table('hris.hospital_order_employees')
            ->where('ho_id', $this->hoId)->where('userid', $this->employeeId)->value('id');

        $this->actAs($this->employeeId);

        $this->expectException(ValidationException::class);
        $service->deleteMyFiling($filingId);
    }

    /** @test */
    public function filing_head_can_delete_their_own_approved_on_behalf_filing()
    {
        $this->actAs($this->shUserId);
        $service = app(HospitalOrderServices::class);
        $service->fileForEmployee($this->hoId, $this->employeeId, '2026-08-05', '08:00', '17:00');
        $filingId = DB::table('hris.hospital_order_employees')
            ->where('ho_id', $this->hoId)->where('userid', $this->employeeId)->value('id');

        $service->deleteMyFiling($filingId);

        $this->assertNull(DB::table('hris.hospital_order_employees')->where('id', $filingId)->first());
    }

    /** @test */
    public function admin_can_delete_any_on_behalf_filing()
    {
        $this->actAs($this->shUserId);
        $service = app(HospitalOrderServices::class);
        $service->fileForEmployee($this->hoId, $this->employeeId, '2026-08-05', '08:00', '17:00');
        $filingId = DB::table('hris.hospital_order_employees')
            ->where('ho_id', $this->hoId)->where('userid', $this->employeeId)->value('id');

        $this->actAs($this->dhUserId, true);
        $service->deleteMyFiling($filingId);

        $this->assertNull(DB::table('hris.hospital_order_employees')->where('id', $filingId)->first());
    }

    /** @test */
    public function employees_own_filings_list_includes_the_filer_name()
    {
        $this->actAs($this->shUserId);
        $service = app(HospitalOrderServices::class);
        $service->fileForEmployee($this->hoId, $this->employeeId, '2026-08-05', '08:00', '17:00');

        $this->actAs($this->employeeId);
        $filings = $service->getMyFilings();

        $filing = $filings->firstWhere('ho_id', $this->hoId);
        $this->assertNotNull($filing);
        $this->assertEquals('SH HoTest', trim($filing->filed_by_name));
    }

    /** @test */
    public function plain_employee_can_still_delete_their_own_self_filed_filing_as_before()
    {
        $this->actAs($this->employeeId);
        $service = app(HospitalOrderServices::class);
        $service->fileForSelf($this->hoId, '2026-08-05', '08:00', '17:00');
        $filingId = DB::table('hris.hospital_order_employees')
            ->where('ho_id', $this->hoId)->where('userid', $this->employeeId)->value('id');

        $service->deleteMyFiling($filingId);

        $this->assertNull(DB::table('hris.hospital_order_employees')->where('id', $filingId)->first());
    }
}
