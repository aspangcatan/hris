<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PermissionControllerTest extends TestCase
{
    use DatabaseTransactions;

    private int $adminId;
    private int $targetId;
    private int $permId;
    private string $permCode;

    protected function setUp(): void
    {
        parent::setUp();

        // Insert admin user via 'users' connection (same as CheckPermissionMiddlewareTest pattern)
        $this->adminId = DB::connection('users')->table('users')->insertGetId([
            'fname'       => 'Admin',
            'lname'       => 'Test',
            'mname'       => '',
            'username'    => 'pct_admin_' . uniqid(),
            'password'    => bcrypt('password'),
            'designation' => 0,
            'division'    => 0,
            'section'     => 0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::connection('users')->table('user_priv')->updateOrInsert(
            ['user_id' => $this->adminId],
            ['user_id' => $this->adminId, 'syscode' => 'hris', 'level' => 'admin']
        );

        // Insert target user (subject of permission changes)
        $this->targetId = DB::connection('users')->table('users')->insertGetId([
            'fname'       => 'Target',
            'lname'       => 'User',
            'mname'       => '',
            'username'    => 'pct_target_' . uniqid(),
            'password'    => bcrypt('password'),
            'designation' => 0,
            'division'    => 0,
            'section'     => 0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::connection('users')->table('user_priv')->updateOrInsert(
            ['user_id' => $this->targetId],
            ['user_id' => $this->targetId, 'syscode' => 'hris', 'level' => 'standard']
        );

        // Ensure a permission row exists for grant/reset tests
        $this->permCode = 'dtr.pct_test_' . uniqid();
        $this->permId = DB::table('hris.permissions')->insertGetId([
            'module'      => 'dtr',
            'code'        => $this->permCode,
            'description' => 'PermissionController test permission',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    protected function tearDown(): void
    {
        // Explicit cross-DB cleanup (DatabaseTransactions may not roll back cross-DB writes)
        DB::table('hris.user_permissions')->where('user_id', $this->targetId)->delete();
        DB::table('hris.user_permissions')->where('user_id', $this->adminId)->delete();
        DB::table('hris.permission_audit_logs')->whereIn('target_user_id', [$this->adminId, $this->targetId])->delete();
        DB::table('hris.permission_audit_logs')->whereIn('performer_id', [$this->adminId, $this->targetId])->delete();
        DB::table('hris.permissions')->where('id', $this->permId)->delete();
        DB::connection('users')->table('user_priv')->whereIn('user_id', [$this->adminId, $this->targetId])->delete();
        DB::connection('users')->table('users')->whereIn('id', [$this->adminId, $this->targetId])->delete();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Test 1: Non-admin cannot access users list (expects 403)
    // -------------------------------------------------------------------------

    public function test_non_admin_cannot_access_users_list(): void
    {
        $user = User::on('users')->find($this->targetId);

        $this->actingAs($user)
             ->withSession(['access_rights' => 'standard'])
             ->getJson('/admin/permissions/users')
             ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Test 2: Admin can get users list (expects 200 with data and total keys)
    // -------------------------------------------------------------------------

    public function test_admin_can_get_users_list(): void
    {
        $admin = User::on('users')->find($this->adminId);

        $response = $this->actingAs($admin)
                         ->withSession(['access_rights' => 'admin'])
                         ->getJson('/admin/permissions/users')
                         ->assertStatus(200);

        $response->assertJsonStructure(['data', 'total']);
    }

    // -------------------------------------------------------------------------
    // Test 3: Admin can grant permission (expects 200, DB has the grant row)
    // -------------------------------------------------------------------------

    public function test_admin_can_grant_permission(): void
    {
        $admin = User::on('users')->find($this->adminId);

        $this->actingAs($admin)
             ->withSession(['access_rights' => 'admin'])
             ->postJson("/admin/permissions/users/{$this->targetId}", [
                 'code' => $this->permCode,
                 'type' => 'grant',
             ])
             ->assertStatus(200)
             ->assertJson(['message' => 'Permission grantd successfully.']);

        $this->assertTrue(
            DB::table('hris.user_permissions')
                ->where('user_id', $this->targetId)
                ->where('permission_id', $this->permId)
                ->where('type', 'grant')
                ->exists(),
            'Expected a grant row in hris.user_permissions'
        );
    }

    // -------------------------------------------------------------------------
    // Test 4: Admin can reset all permissions (expects 200, DB row deleted)
    // -------------------------------------------------------------------------

    public function test_admin_can_reset_all_permissions(): void
    {
        // Seed an existing override so there is something to delete
        DB::table('hris.user_permissions')->insert([
            'user_id'       => $this->targetId,
            'permission_id' => $this->permId,
            'type'          => 'grant',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $admin = User::on('users')->find($this->adminId);

        $this->actingAs($admin)
             ->withSession(['access_rights' => 'admin'])
             ->deleteJson("/admin/permissions/users/{$this->targetId}/reset")
             ->assertStatus(200)
             ->assertJson(['message' => 'All permissions reset to level defaults.']);

        $this->assertFalse(
            DB::table('hris.user_permissions')
                ->where('user_id', $this->targetId)
                ->exists(),
            'Expected all user_permissions rows for target to be deleted'
        );
    }
}
