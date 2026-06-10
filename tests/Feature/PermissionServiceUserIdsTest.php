<?php

namespace Tests\Feature;

use App\Services\PermissionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PermissionServiceUserIdsTest extends TestCase
{
    use DatabaseTransactions;

    private PermissionService $service;
    private int $permId;
    private array $userIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PermissionService();

        $this->permId = DB::table('hris.permissions')->insertGetId([
            'module'      => 'schedule',
            'code'        => 'schedule.manage_test_' . uniqid(),
            'description' => 'Test permission',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('hris.user_permissions')->whereIn('user_id', $this->userIds)->delete();
        DB::table('hris.access_level_permissions')->where('permission_id', $this->permId)->delete();
        DB::table('hris.permissions')->where('id', $this->permId)->delete();
        DB::table('tdh_user.user_priv')->whereIn('user_id', $this->userIds)->delete();
        DB::table('tdh_user.users')->whereIn('id', $this->userIds)->delete();
        parent::tearDown();
    }

    private function createUser(string $level): int
    {
        $userId = DB::table('tdh_user.users')->insertGetId([
            'fname'       => 'Test',
            'lname'       => 'User',
            'mname'       => '',
            'username'    => 'permlookup_' . uniqid(),
            'password'    => bcrypt('password'),
            'designation' => 0,
            'division'    => 0,
            'section'     => 0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::table('tdh_user.user_priv')->updateOrInsert(
            ['user_id' => $userId],
            ['user_id' => $userId, 'syscode' => 'hris', 'level' => $level]
        );

        $this->userIds[] = $userId;
        return $userId;
    }

    private function code(): string
    {
        return DB::table('hris.permissions')->where('id', $this->permId)->value('code');
    }

    public function test_returns_empty_for_unknown_code(): void
    {
        $this->assertSame([], $this->service->getUserIdsWithPermission('no.such.code_' . uniqid()));
    }

    public function test_includes_users_whose_level_grants_the_permission(): void
    {
        DB::table('hris.access_level_permissions')->insert([
            'level' => 'schedule', 'permission_id' => $this->permId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $scheduleUser = $this->createUser('schedule');
        $standardUser = $this->createUser('standard');

        $result = $this->service->getUserIdsWithPermission($this->code());

        $this->assertContains($scheduleUser, $result);
        $this->assertNotContains($standardUser, $result);
    }

    public function test_includes_per_user_grant_outside_level_default(): void
    {
        $standardUser = $this->createUser('standard');

        DB::table('hris.user_permissions')->insert([
            'user_id' => $standardUser, 'permission_id' => $this->permId, 'type' => 'grant',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = $this->service->getUserIdsWithPermission($this->code());

        $this->assertContains($standardUser, $result);
    }

    public function test_excludes_user_with_revoke_even_if_level_grants(): void
    {
        DB::table('hris.access_level_permissions')->insert([
            'level' => 'schedule', 'permission_id' => $this->permId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $scheduleUser = $this->createUser('schedule');
        DB::table('hris.user_permissions')->insert([
            'user_id' => $scheduleUser, 'permission_id' => $this->permId, 'type' => 'revoke',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = $this->service->getUserIdsWithPermission($this->code());

        $this->assertNotContains($scheduleUser, $result);
    }

    public function test_excludes_admin_level_by_default(): void
    {
        DB::table('hris.access_level_permissions')->insert([
            'level' => 'admin', 'permission_id' => $this->permId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $adminUser = $this->createUser('admin');

        $result = $this->service->getUserIdsWithPermission($this->code());

        $this->assertNotContains($adminUser, $result);
    }

    public function test_includes_admin_level_when_excludeAdminLevel_is_false(): void
    {
        DB::table('hris.access_level_permissions')->insert([
            'level' => 'admin', 'permission_id' => $this->permId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $adminUser = $this->createUser('admin');

        $result = $this->service->getUserIdsWithPermission($this->code(), false);

        $this->assertContains($adminUser, $result);
    }
}
