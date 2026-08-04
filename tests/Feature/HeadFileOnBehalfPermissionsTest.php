<?php

namespace Tests\Feature;

use App\Services\PermissionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HeadFileOnBehalfPermissionsTest extends TestCase
{
    use DatabaseTransactions;

    private int $shUserId = 9101001;
    private int $dhUserId = 9101002;
    private int $sectionId = 8101001;
    private int $divisionId = 9201001;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tdh_user.users')->insertOrIgnore([
            ['id' => $this->shUserId, 'fname' => 'SH', 'lname' => 'Behalf', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'sh_behalf_test', 'password' => 'x'],
            ['id' => $this->dhUserId, 'fname' => 'DH', 'lname' => 'Behalf', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'dh_behalf_test', 'password' => 'x'],
        ]);

        DB::table('tdh_user.division')->insertOrIgnore([
            ['id' => $this->divisionId, 'description' => 'BehalfTestDivision', 'head' => $this->dhUserId, 'code' => 'BTD'],
        ]);

        DB::table('tdh_user.section')->insertOrIgnore([
            ['id' => $this->sectionId, 'description' => 'BehalfTestSection', 'head' => $this->shUserId, 'division' => $this->divisionId, 'code' => 'BTS'],
        ]);

        (new PermissionSeeder())->run();
    }

    protected function tearDown(): void
    {
        DB::table('hris.user_permissions')->whereIn('user_id', [$this->shUserId, $this->dhUserId])->delete();
        DB::table('hris.permission_audit_logs')->whereIn('target_user_id', [$this->shUserId, $this->dhUserId])->delete();
        DB::table('tdh_user.section')->where('id', $this->sectionId)->delete();
        DB::table('tdh_user.division')->where('id', $this->divisionId)->delete();
        DB::table('tdh_user.users')->whereIn('id', [$this->shUserId, $this->dhUserId])->delete();
        parent::tearDown();
    }

    /** @test */
    public function catalog_includes_the_new_file_on_behalf_permissions()
    {
        $codes = DB::table('hris.permissions')
            ->whereIn('code', ['passslip.file_on_behalf', 'hospital_order.file_on_behalf'])
            ->pluck('code')
            ->all();

        $this->assertContains('passslip.file_on_behalf', $codes);
        $this->assertContains('hospital_order.file_on_behalf', $codes);
    }

    /** @test */
    public function sync_headship_grants_gives_section_head_the_file_on_behalf_permissions()
    {
        $service = new PermissionService();
        $service->syncHeadshipGrants($this->shUserId, true, false);

        $this->assertTrue($service->can($this->shUserId, 'passslip.file_on_behalf'));
        $this->assertTrue($service->can($this->shUserId, 'hospital_order.file_on_behalf'));
    }

    /** @test */
    public function sync_headship_grants_gives_division_head_the_file_on_behalf_permissions()
    {
        $service = new PermissionService();
        $service->syncHeadshipGrants($this->dhUserId, false, true);

        $this->assertTrue($service->can($this->dhUserId, 'passslip.file_on_behalf'));
        $this->assertTrue($service->can($this->dhUserId, 'hospital_order.file_on_behalf'));
    }
}
