<?php

namespace Tests\Feature;

use App\Http\Controllers\HospitalOrderController;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HospitalOrderControllerOnBehalfTest extends TestCase
{
    use DatabaseTransactions;

    private int $employeeId = 9601001;
    private int $shUserId = 9601002;
    private int $unauthorizedHeadId = 9601003;
    private int $sectionId = 8601001;
    private int $hoId;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tdh_user.users')->insertOrIgnore([
            ['id' => $this->shUserId, 'fname' => 'SH', 'lname' => 'HoCtrlTest', 'section' => $this->sectionId, 'division' => 0, 'username' => 'sh_hoctrl_test', 'password' => 'x'],
            ['id' => $this->unauthorizedHeadId, 'fname' => 'NoPerm', 'lname' => 'HoCtrlTest', 'section' => 0, 'division' => 0, 'username' => 'noperm_hoctrl_test', 'password' => 'x'],
            ['id' => $this->employeeId, 'fname' => 'Emp', 'lname' => 'HoCtrlTest', 'section' => $this->sectionId, 'division' => 0, 'username' => 'emp_hoctrl_test', 'password' => 'x'],
        ]);

        DB::table('tdh_user.section')->insertOrIgnore([
            ['id' => $this->sectionId, 'description' => 'HoCtrlTestSection', 'head' => $this->shUserId, 'division' => 0, 'code' => 'HCS'],
        ]);

        (new PermissionSeeder())->run();

        $permId = DB::table('hris.permissions')->where('code', 'hospital_order.file_on_behalf')->value('id');
        DB::table('hris.user_permissions')->updateOrInsert(
            ['user_id' => $this->shUserId, 'permission_id' => $permId],
            ['user_id' => $this->shUserId, 'permission_id' => $permId, 'type' => 'grant', 'created_at' => now(), 'updated_at' => now()]
        );

        $this->hoId = DB::table('hris.hospital_orders')->insertGetId([
            'order_number' => 'HOCTRL-TEST-' . uniqid(),
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
        DB::table('hris.user_permissions')->whereIn('user_id', [$this->shUserId, $this->unauthorizedHeadId])->delete();
        DB::table('tdh_user.section')->where('id', $this->sectionId)->delete();
        DB::table('tdh_user.users')->whereIn('id', [$this->shUserId, $this->unauthorizedHeadId, $this->employeeId])->delete();
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
        $controller = app(HospitalOrderController::class);

        $request = Request::create('/hospital-orders/file', 'POST', [
            'ho_id' => $this->hoId,
            'filed_date' => '2026-08-05',
            'time_from' => '08:00',
            'time_to' => '17:00',
            'target_user_id' => $this->employeeId,
        ]);

        $response = $controller->fileForSelf($request);

        $this->assertEquals(200, $response->getStatusCode());

        $exists = DB::table('hris.hospital_order_employees')
            ->where('ho_id', $this->hoId)
            ->where('userid', $this->employeeId)
            ->where('filed_by', $this->shUserId)
            ->where('status', 'approved')
            ->exists();
        $this->assertTrue($exists);
    }

    /** @test */
    public function user_without_the_permission_gets_403_when_targeting_another_employee()
    {
        $this->actAs($this->unauthorizedHeadId);
        $controller = app(HospitalOrderController::class);

        $request = Request::create('/hospital-orders/file', 'POST', [
            'ho_id' => $this->hoId,
            'filed_date' => '2026-08-05',
            'time_from' => '08:00',
            'time_to' => '17:00',
            'target_user_id' => $this->employeeId,
        ]);

        $response = $controller->fileForSelf($request);

        $this->assertEquals(403, $response->getStatusCode());

        $exists = DB::table('hris.hospital_order_employees')
            ->where('ho_id', $this->hoId)
            ->where('userid', $this->employeeId)
            ->exists();
        $this->assertFalse($exists);
    }
}
