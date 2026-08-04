<?php

namespace Tests\Unit;

use App\Services\OrgHierarchyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrgHierarchyServiceTest extends TestCase
{
    use DatabaseTransactions;

    private int $shUserId = 9001001;
    private int $dhUserId = 9001002;
    private int $plainEmployeeId = 9001003;
    private int $sectionEmployeeId = 9001004;
    private int $otherSectionEmployeeId = 9001005;
    private int $sectionId = 8001001;
    private int $otherSectionId = 8001002;
    private int $divisionId = 9101001;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tdh_user.users')->insertOrIgnore([
            ['id' => $this->shUserId, 'fname' => 'SH', 'lname' => 'OrgTest', 'section' => $this->sectionId, 'division' => 0, 'username' => 'sh_org_test', 'password' => 'x'],
            ['id' => $this->dhUserId, 'fname' => 'DH', 'lname' => 'OrgTest', 'section' => 0, 'division' => $this->divisionId, 'username' => 'dh_org_test', 'password' => 'x'],
            ['id' => $this->plainEmployeeId, 'fname' => 'Plain', 'lname' => 'OrgTest', 'section' => 0, 'division' => 0, 'username' => 'plain_org_test', 'password' => 'x'],
            ['id' => $this->sectionEmployeeId, 'fname' => 'Alpha', 'lname' => 'OrgTest', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'sec_org_test', 'password' => 'x'],
            ['id' => $this->otherSectionEmployeeId, 'fname' => 'Beta', 'lname' => 'OrgTest', 'section' => $this->otherSectionId, 'division' => 0, 'username' => 'osec_org_test', 'password' => 'x'],
        ]);

        DB::table('tdh_user.division')->insertOrIgnore([
            ['id' => $this->divisionId, 'description' => 'OrgTestDivision', 'head' => $this->dhUserId, 'code' => 'OTD'],
        ]);

        DB::table('tdh_user.section')->insertOrIgnore([
            ['id' => $this->sectionId, 'description' => 'OrgTestSection', 'head' => $this->shUserId, 'division' => 0, 'code' => 'OTS'],
            ['id' => $this->otherSectionId, 'description' => 'OrgTestOtherSection', 'head' => 0, 'division' => 0, 'code' => 'OTO'],
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('tdh_user.section')->whereIn('id', [$this->sectionId, $this->otherSectionId])->delete();
        DB::table('tdh_user.division')->where('id', $this->divisionId)->delete();
        DB::table('tdh_user.users')->whereIn('id', [
            $this->shUserId, $this->dhUserId, $this->plainEmployeeId, $this->sectionEmployeeId, $this->otherSectionEmployeeId,
        ])->delete();
        parent::tearDown();
    }

    /** @test */
    public function section_head_manages_only_employees_in_their_section()
    {
        $service = new OrgHierarchyService();
        $ids = $service->getManagedEmployeeIds($this->shUserId);

        $this->assertContains($this->sectionEmployeeId, $ids);
        $this->assertNotContains($this->otherSectionEmployeeId, $ids);
        $this->assertNotContains($this->shUserId, $ids);
    }

    /** @test */
    public function division_head_manages_every_employee_in_their_division_regardless_of_section()
    {
        $service = new OrgHierarchyService();
        $ids = $service->getManagedEmployeeIds($this->dhUserId);

        $this->assertContains($this->sectionEmployeeId, $ids);
        $this->assertNotContains($this->otherSectionEmployeeId, $ids);
    }

    /** @test */
    public function plain_employee_manages_nobody()
    {
        $service = new OrgHierarchyService();
        $ids = $service->getManagedEmployeeIds($this->plainEmployeeId);

        $this->assertSame([], $ids);
    }

    /** @test */
    public function get_managed_employees_returns_formatted_full_names()
    {
        $service = new OrgHierarchyService();
        $employees = $service->getManagedEmployees($this->shUserId);

        $match = $employees->firstWhere('id', $this->sectionEmployeeId);
        $this->assertNotNull($match);
        $this->assertEquals('OrgTest, Alpha', $match->full_name);
    }
}
