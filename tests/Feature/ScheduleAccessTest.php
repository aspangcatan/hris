<?php

namespace Tests\Feature;

use App\Http\Controllers\ScheduleController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScheduleAccessTest extends TestCase
{
    use DatabaseTransactions;

    private int $managerUserId;
    private int $employeeUserId;
    private int $divisionId;
    private int $sectionAId;
    private int $sectionBId;
    private int $schedulePermissionId;

    protected function setUp(): void
    {
        parent::setUp();

        // The PermissionService singleton caches effective permissions per user id
        // for its lifetime. Since DatabaseTransactions rolls back each test, newly
        // created users can be assigned the same auto-increment id as a previous
        // test's user, causing stale cached permission results. Forget the
        // singleton instance so permission_check() resolves a fresh, uncached
        // PermissionService for this test.
        $this->app->forgetInstance(\App\Services\PermissionService::class);

        $this->managerUserId = DB::table('tdh_user.users')->insertGetId([
            'fname' => 'Manager', 'lname' => 'Test', 'mname' => '',
            'username' => 'sched_mgr_' . uniqid(), 'password' => bcrypt('password'),
            'designation' => 0, 'division' => 0, 'section' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->employeeUserId = DB::table('tdh_user.users')->insertGetId([
            'fname' => 'Employee', 'lname' => 'Test', 'mname' => '',
            'username' => 'sched_emp_' . uniqid(), 'password' => bcrypt('password'),
            'designation' => 0, 'division' => 0, 'section' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('tdh_user.user_priv')->updateOrInsert(
            ['user_id' => $this->managerUserId],
            ['user_id' => $this->managerUserId, 'syscode' => 'hris', 'level' => 'schedule']
        );
        DB::table('tdh_user.user_priv')->updateOrInsert(
            ['user_id' => $this->employeeUserId],
            ['user_id' => $this->employeeUserId, 'syscode' => 'hris', 'level' => 'standard']
        );

        $this->divisionId = DB::table('tdh_user.division')->insertGetId([
            'description' => 'ScheduleTestDivision', 'head' => $this->managerUserId, 'code' => 'SCHDIV',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->sectionAId = DB::table('tdh_user.section')->insertGetId([
            'description' => 'ScheduleTestSectionA', 'division' => $this->divisionId, 'head' => $this->managerUserId, 'code' => 'SCHSECA',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->sectionBId = DB::table('tdh_user.section')->insertGetId([
            'description' => 'ScheduleTestSectionB', 'division' => $this->divisionId, 'head' => $this->managerUserId, 'code' => 'SCHSECB',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('tdh_user.users')->where('id', $this->managerUserId)->update([
            'division' => $this->divisionId, 'section' => $this->sectionAId,
        ]);
        DB::table('tdh_user.users')->where('id', $this->employeeUserId)->update([
            'division' => $this->divisionId, 'section' => $this->sectionAId,
        ]);

        // The test database is not seeded with PermissionSeeder, so the
        // 'schedule.manage' permission and its grant to the 'schedule' level
        // must be created here for permission_check('schedule.manage') to work.
        $this->schedulePermissionId = DB::table('hris.permissions')->insertGetId([
            'module' => 'schedule',
            'code' => 'schedule.manage',
            'description' => 'Manage schedules',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('hris.access_level_permissions')->insert([
            'level' => 'schedule',
            'permission_id' => $this->schedulePermissionId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('hris.access_level_permissions')->where('permission_id', $this->schedulePermissionId)->delete();
        DB::table('hris.permissions')->where('id', $this->schedulePermissionId)->delete();
        DB::table('hris.schedule_user_sections')->whereIn('user_id', [$this->managerUserId, $this->employeeUserId])->delete();
        DB::table('hris.user_permissions')->whereIn('user_id', [$this->managerUserId, $this->employeeUserId])->delete();
        DB::table('tdh_user.section')->whereIn('id', [$this->sectionAId, $this->sectionBId])->delete();
        DB::table('tdh_user.division')->where('id', $this->divisionId)->delete();
        DB::table('tdh_user.user_priv')->whereIn('user_id', [$this->managerUserId, $this->employeeUserId])->delete();
        DB::table('tdh_user.users')->whereIn('id', [$this->managerUserId, $this->employeeUserId])->delete();
        parent::tearDown();
    }

    private function actAsManager(): void
    {
        Auth::shouldReceive('id')->andReturn($this->managerUserId);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'schedule']);
    }

    private function callPrivate(object $object, string $method, array $args = [])
    {
        $ref = new \ReflectionMethod($object, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($object, $args);
    }

    /** @test */
    public function manager_with_no_assigned_sections_has_no_managed_sections(): void
    {
        $this->actAsManager();
        $controller = app(ScheduleController::class);

        // Manager's own section is sectionA (set in setUp). The old fallback
        // would have returned [sectionAId] here; the new behavior must not.
        $this->assertSame([], $this->callPrivate($controller, 'getManagedSectionIdsForCurrentUser'));
    }

    /** @test */
    public function manager_with_assigned_sections_returns_only_those_sections(): void
    {
        $this->actAsManager();

        DB::table('hris.schedule_user_sections')->insert([
            'user_id' => $this->managerUserId,
            'section_id' => $this->sectionBId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $controller = app(ScheduleController::class);

        // Only sectionB (the assigned one) should come back — not sectionA
        // (the manager's own section), since the fallback is gone.
        $this->assertSame([$this->sectionBId], $this->callPrivate($controller, 'getManagedSectionIdsForCurrentUser'));
    }

    /** @test */
    public function cannot_manage_employee_when_no_sections_assigned(): void
    {
        $this->actAsManager();
        $controller = app(ScheduleController::class);

        $this->assertFalse($this->callPrivate($controller, 'canManageEmployee', [$this->employeeUserId]));
    }

    /** @test */
    public function can_manage_employee_in_assigned_section(): void
    {
        $this->actAsManager();

        DB::table('hris.schedule_user_sections')->insert([
            'user_id' => $this->managerUserId,
            'section_id' => $this->sectionAId, // employee is in sectionA
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $controller = app(ScheduleController::class);

        $this->assertTrue($this->callPrivate($controller, 'canManageEmployee', [$this->employeeUserId]));
    }

    /** @test */
    public function cannot_manage_employee_outside_assigned_sections(): void
    {
        $this->actAsManager();

        DB::table('hris.schedule_user_sections')->insert([
            'user_id' => $this->managerUserId,
            'section_id' => $this->sectionBId, // employee is in sectionA, not sectionB
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $controller = app(ScheduleController::class);

        $this->assertFalse($this->callPrivate($controller, 'canManageEmployee', [$this->employeeUserId]));
    }

    /** @test */
    public function get_schedules_returns_empty_when_manager_has_no_assigned_sections(): void
    {
        $this->actAsManager();
        $controller = app(ScheduleController::class);

        $response = $controller->getSchedules(new Request());
        $data = $response->getData(true);

        $this->assertSame([], $data['schedules']);
        $this->assertArrayHasKey('days', $data);
    }

    /** @test */
    public function get_managed_sections_returns_empty_when_manager_has_no_assigned_sections(): void
    {
        $this->actAsManager();
        $controller = app(ScheduleController::class);

        $response = $controller->getManagedSections();
        $data = $response->getData(true);

        $this->assertSame([], $data['sections']);
    }

    /** @test */
    public function get_managed_sections_returns_assigned_sections_for_manager(): void
    {
        $this->actAsManager();

        DB::table('hris.schedule_user_sections')->insert([
            'user_id' => $this->managerUserId,
            'section_id' => $this->sectionAId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $controller = app(ScheduleController::class);
        $response = $controller->getManagedSections();
        $data = $response->getData(true);

        $sectionIds = collect($data['sections'])->pluck('id')->map(fn($id) => (int) $id)->all();
        $this->assertSame([$this->sectionAId], $sectionIds);
    }

    /** @test */
    public function get_managed_sections_returns_unfiltered_list_for_admin(): void
    {
        Auth::shouldReceive('id')->andReturn($this->managerUserId);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'admin']);

        $controller = app(ScheduleController::class);
        $response = $controller->getManagedSections();
        $data = $response->getData(true);

        $sectionIds = collect($data['sections'])->pluck('id')->map(fn($id) => (int) $id)->all();
        $this->assertContains($this->sectionAId, $sectionIds);
        $this->assertContains($this->sectionBId, $sectionIds);
    }
}
