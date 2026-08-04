<?php

namespace Tests\Feature;

use App\Http\Controllers\PassSlipController;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PassSlipControllerOnBehalfTest extends TestCase
{
    use DatabaseTransactions;

    private int $employeeId = 9501001;
    private int $shUserId = 9501002;
    private int $unauthorizedHeadId = 9501003;
    private int $outsideEmployeeId = 9501004;
    private int $sectionId = 8501001;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('hris.pass_slips')->whereIn('user_id', [$this->employeeId, $this->shUserId, $this->outsideEmployeeId])->delete();

        DB::table('tdh_user.users')->insertOrIgnore([
            ['id' => $this->shUserId, 'fname' => 'SH', 'lname' => 'CtrlTest', 'section' => $this->sectionId, 'division' => 0, 'username' => 'sh_ctrl_test', 'password' => 'x'],
            ['id' => $this->unauthorizedHeadId, 'fname' => 'NoPerm', 'lname' => 'CtrlTest', 'section' => 0, 'division' => 0, 'username' => 'noperm_ctrl_test', 'password' => 'x'],
            ['id' => $this->employeeId, 'fname' => 'Emp', 'lname' => 'CtrlTest', 'section' => $this->sectionId, 'division' => 0, 'username' => 'emp_ctrl_test', 'password' => 'x'],
            ['id' => $this->outsideEmployeeId, 'fname' => 'Outside', 'lname' => 'CtrlTest', 'section' => 0, 'division' => 0, 'username' => 'outside_ctrl_test', 'password' => 'x'],
        ]);

        DB::table('tdh_user.section')->insertOrIgnore([
            ['id' => $this->sectionId, 'description' => 'CtrlTestSection', 'head' => $this->shUserId, 'division' => 0, 'code' => 'CTS'],
        ]);

        (new PermissionSeeder())->run();

        $permId = DB::table('hris.permissions')->where('code', 'passslip.file_on_behalf')->value('id');
        DB::table('hris.user_permissions')->updateOrInsert(
            ['user_id' => $this->shUserId, 'permission_id' => $permId],
            ['user_id' => $this->shUserId, 'permission_id' => $permId, 'type' => 'grant', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    protected function tearDown(): void
    {
        DB::table('hris.pass_slips')->whereIn('user_id', [$this->employeeId, $this->shUserId, $this->outsideEmployeeId])->delete();
        DB::table('hris.user_permissions')->whereIn('user_id', [$this->shUserId, $this->unauthorizedHeadId])->delete();
        DB::table('tdh_user.section')->where('id', $this->sectionId)->delete();
        DB::table('tdh_user.users')->whereIn('id', [$this->shUserId, $this->unauthorizedHeadId, $this->employeeId, $this->outsideEmployeeId])->delete();
        parent::tearDown();
    }

    private function actAs(int $userId): void
    {
        Auth::shouldReceive('id')->andReturn($userId);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'employee']);
    }

    /** @test */
    public function head_with_permission_can_file_on_behalf_via_the_controller()
    {
        $this->actAs($this->shUserId);
        $controller = app(PassSlipController::class);

        $request = Request::create('/pass-slip/store', 'POST', [
            'slip_date' => '2026-08-04',
            'time_out' => '13:00',
            'time_in' => '14:00',
            'type' => 'Official',
            'target_user_id' => $this->employeeId,
        ]);

        $response = $controller->store($request);

        $this->assertEquals(200, $response->getStatusCode());

        $exists = DB::table('hris.pass_slips')
            ->where('user_id', $this->employeeId)
            ->where('filed_by', $this->shUserId)
            ->where('status', 'approved')
            ->exists();
        $this->assertTrue($exists);
    }

    /** @test */
    public function user_without_the_permission_gets_403_when_targeting_another_employee()
    {
        $this->actAs($this->unauthorizedHeadId);
        $controller = app(PassSlipController::class);

        $request = Request::create('/pass-slip/store', 'POST', [
            'slip_date' => '2026-08-04',
            'time_out' => '13:00',
            'time_in' => '14:00',
            'type' => 'Official',
            'target_user_id' => $this->employeeId,
        ]);

        $response = $controller->store($request);

        $this->assertEquals(403, $response->getStatusCode());

        $exists = DB::table('hris.pass_slips')
            ->where('user_id', $this->employeeId)
            ->where('filed_by', $this->unauthorizedHeadId)
            ->exists();
        $this->assertFalse($exists);
    }

    /** @test */
    public function permitted_head_gets_422_when_target_is_outside_their_managed_scope()
    {
        $this->actAs($this->shUserId);
        $controller = app(PassSlipController::class);

        $request = Request::create('/pass-slip/store', 'POST', [
            'slip_date' => '2026-08-04',
            'time_out' => '13:00',
            'time_in' => '14:00',
            'type' => 'Official',
            'target_user_id' => $this->outsideEmployeeId,
        ]);

        $response = $controller->store($request);

        $this->assertEquals(422, $response->getStatusCode());

        $exists = DB::table('hris.pass_slips')
            ->where('user_id', $this->outsideEmployeeId)
            ->exists();
        $this->assertFalse($exists);
    }
}
