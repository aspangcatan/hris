<?php

namespace Tests\Feature;

use App\Http\Controllers\ManagedEmployeeController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ManagedEmployeeControllerTest extends TestCase
{
    use DatabaseTransactions;

    private int $shUserId = 9901001;
    private int $employeeId = 9901002;
    private int $outsideEmployeeId = 9901003;
    private int $sectionId = 8901001;
    private int $otherSectionId = 8901002;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tdh_user.users')->insertOrIgnore([
            ['id' => $this->shUserId, 'fname' => 'SH', 'lname' => 'MgdTest', 'section' => $this->sectionId, 'division' => 0, 'username' => 'sh_mgd_test', 'password' => 'x'],
            ['id' => $this->employeeId, 'fname' => 'Zeta', 'lname' => 'MgdTest', 'section' => $this->sectionId, 'division' => 0, 'username' => 'emp_mgd_test', 'password' => 'x'],
            ['id' => $this->outsideEmployeeId, 'fname' => 'Outside', 'lname' => 'MgdTest', 'section' => $this->otherSectionId, 'division' => 0, 'username' => 'outside_mgd_test', 'password' => 'x'],
        ]);

        DB::table('tdh_user.section')->insertOrIgnore([
            ['id' => $this->sectionId, 'description' => 'MgdTestSection', 'head' => $this->shUserId, 'division' => 0, 'code' => 'MTS'],
            ['id' => $this->otherSectionId, 'description' => 'MgdTestOtherSection', 'head' => 0, 'division' => 0, 'code' => 'MTO'],
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('tdh_user.section')->whereIn('id', [$this->sectionId, $this->otherSectionId])->delete();
        DB::table('tdh_user.users')->whereIn('id', [$this->shUserId, $this->employeeId, $this->outsideEmployeeId])->delete();
        parent::tearDown();
    }

    /** @test */
    public function returns_only_employees_in_the_heads_managed_section()
    {
        Auth::shouldReceive('id')->andReturn($this->shUserId);
        Auth::shouldReceive('check')->andReturn(true);

        $controller = app(ManagedEmployeeController::class);
        $response = $controller->index();
        $data = collect(json_decode($response->getContent(), true));

        $this->assertTrue($data->contains('id', $this->employeeId));
        $this->assertFalse($data->contains('id', $this->outsideEmployeeId));
    }
}
