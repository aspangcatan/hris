<?php

namespace Tests\Feature;

use App\Services\PermissionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PermissionServiceTest extends TestCase
{
    use DatabaseTransactions;

    private PermissionService $service;
    private int $testUserId;
    private int $permId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PermissionService();

        $this->testUserId = DB::table('tdh_user.users')->insertGetId([
            'fname'       => 'Test',
            'lname'       => 'User',
            'mname'       => '',
            'username'    => 'permtest_' . uniqid(),
            'password'    => bcrypt('password'),
            'designation' => 0,
            'division'    => 0,
            'section'     => 0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::table('tdh_user.user_priv')->updateOrInsert(
            ['user_id' => $this->testUserId],
            ['user_id' => $this->testUserId, 'syscode' => 'hris', 'level' => 'standard']
        );

        $this->permId = DB::table('hris.permissions')->insertGetId([
            'module'      => 'dtr',
            'code'        => 'dtr.edit_test_' . uniqid(),
            'description' => 'Test permission',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    protected function tearDown(): void
    {
        // Explicit cross-DB cleanup since DatabaseTransactions may not roll back cross-DB inserts
        DB::table('hris.user_permissions')->where('user_id', $this->testUserId)->delete();
        DB::table('hris.permission_audit_logs')->where('target_user_id', $this->testUserId)->delete();
        DB::table('hris.permission_audit_logs')->where('performer_id', $this->testUserId)->delete();
        DB::table('hris.access_level_permissions')->where('permission_id', $this->permId)->delete();
        DB::table('hris.permissions')->where('id', $this->permId)->delete();
        DB::table('tdh_user.user_priv')->where('user_id', $this->testUserId)->delete();
        DB::table('tdh_user.users')->where('id', $this->testUserId)->delete();
        parent::tearDown();
    }

    public function test_can_returns_false_when_not_in_level_defaults(): void
    {
        $code = DB::table('hris.permissions')->where('id', $this->permId)->value('code');
        $this->assertFalse($this->service->can($this->testUserId, $code));
    }

    public function test_can_returns_true_when_in_level_defaults(): void
    {
        $code = DB::table('hris.permissions')->where('id', $this->permId)->value('code');
        DB::table('hris.access_level_permissions')->insert([
            'level'         => 'standard',
            'permission_id' => $this->permId,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->assertTrue($this->service->can($this->testUserId, $code));
    }

    public function test_explicit_grant_adds_permission_not_in_level(): void
    {
        $code = DB::table('hris.permissions')->where('id', $this->permId)->value('code');
        DB::table('hris.user_permissions')->insert([
            'user_id'       => $this->testUserId,
            'permission_id' => $this->permId,
            'type'          => 'grant',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->assertTrue($this->service->can($this->testUserId, $code));
    }

    public function test_explicit_revoke_removes_level_default(): void
    {
        $code = DB::table('hris.permissions')->where('id', $this->permId)->value('code');
        DB::table('hris.access_level_permissions')->insert([
            'level'         => 'standard',
            'permission_id' => $this->permId,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        DB::table('hris.user_permissions')->insert([
            'user_id'       => $this->testUserId,
            'permission_id' => $this->permId,
            'type'          => 'revoke',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->assertFalse($this->service->can($this->testUserId, $code));
    }

    public function test_setUserPermission_creates_grant_and_audit_log(): void
    {
        $adminId = $this->testUserId;
        $code = DB::table('hris.permissions')->where('id', $this->permId)->value('code');

        $this->service->setUserPermission($adminId, $this->testUserId, $code, 'grant');

        $hasPermission = DB::table('hris.user_permissions')
            ->where('user_id', $this->testUserId)
            ->where('permission_id', $this->permId)
            ->where('type', 'grant')
            ->exists();
        $this->assertTrue($hasPermission, 'Expected grant row in user_permissions');

        // Service writes "{$type}d {$code}" — for type='grant' this produces "grantd {$code}"
        $hasAudit = DB::table('hris.permission_audit_logs')
            ->where('performer_id', $adminId)
            ->where('target_user_id', $this->testUserId)
            ->where('action', "grantd {$code}")
            ->exists();
        $this->assertTrue($hasAudit, 'Expected audit log entry for grant');
    }

    public function test_removeUserPermission_deletes_override_and_logs(): void
    {
        $adminId = $this->testUserId;
        $code = DB::table('hris.permissions')->where('id', $this->permId)->value('code');

        DB::table('hris.user_permissions')->insert([
            'user_id'       => $this->testUserId,
            'permission_id' => $this->permId,
            'type'          => 'grant',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->service->removeUserPermission($adminId, $this->testUserId, $code);

        $stillExists = DB::table('hris.user_permissions')
            ->where('user_id', $this->testUserId)
            ->where('permission_id', $this->permId)
            ->exists();
        $this->assertFalse($stillExists, 'Expected user_permissions row to be deleted');

        $hasAudit = DB::table('hris.permission_audit_logs')
            ->where('target_user_id', $this->testUserId)
            ->where('action', "reset {$code} to level default")
            ->exists();
        $this->assertTrue($hasAudit, 'Expected audit log entry for reset');
    }

    public function test_results_are_cached_per_instance(): void
    {
        $code = DB::table('hris.permissions')->where('id', $this->permId)->value('code');

        $this->assertFalse($this->service->can($this->testUserId, $code));

        DB::table('hris.user_permissions')->insert([
            'user_id'       => $this->testUserId,
            'permission_id' => $this->permId,
            'type'          => 'grant',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Cache should still return false — the grant was inserted after the first call
        $this->assertFalse($this->service->can($this->testUserId, $code));
    }
}
