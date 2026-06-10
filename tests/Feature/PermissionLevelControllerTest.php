<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PermissionLevelControllerTest extends TestCase
{
    use DatabaseTransactions;

    private int $adminId;
    private int $standardId;
    private int $permId;
    private string $permCode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminId = DB::connection('users')->table('users')->insertGetId([
            'fname'       => 'Admin',
            'lname'       => 'LevelTest',
            'mname'       => '',
            'username'    => 'plc_admin_' . uniqid(),
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

        $this->standardId = DB::connection('users')->table('users')->insertGetId([
            'fname'       => 'Standard',
            'lname'       => 'LevelTest',
            'mname'       => '',
            'username'    => 'plc_std_' . uniqid(),
            'password'    => bcrypt('password'),
            'designation' => 0,
            'division'    => 0,
            'section'     => 0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::connection('users')->table('user_priv')->updateOrInsert(
            ['user_id' => $this->standardId],
            ['user_id' => $this->standardId, 'syscode' => 'hris', 'level' => 'standard']
        );

        $this->permCode = 'dtr.plc_test_' . uniqid();
        $this->permId = DB::table('hris.permissions')->insertGetId([
            'module'      => 'dtr',
            'code'        => $this->permCode,
            'description' => 'PermissionLevelController test permission',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // 'hr' starts WITHOUT this permission (used to test granting)
        // 'standard' starts WITH this permission (used to test revoking)
        DB::table('hris.access_level_permissions')->insert([
            'level'         => 'standard',
            'permission_id' => $this->permId,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('hris.access_level_permissions')->where('permission_id', $this->permId)->delete();
        DB::table('hris.permissions')->where('id', $this->permId)->delete();
        DB::connection('users')->table('user_priv')->whereIn('user_id', [$this->adminId, $this->standardId])->delete();
        DB::connection('users')->table('users')->whereIn('id', [$this->adminId, $this->standardId])->delete();
        parent::tearDown();
    }

    public function test_non_admin_cannot_view_index(): void
    {
        $user = User::on('users')->find($this->standardId);

        $this->actingAs($user)
             ->withSession(['access_rights' => 'standard'])
             ->get('/admin/permission-levels')
             ->assertStatus(403);
    }

    public function test_non_admin_cannot_fetch_data(): void
    {
        $user = User::on('users')->find($this->standardId);

        $this->actingAs($user)
             ->withSession(['access_rights' => 'standard'])
             ->getJson('/admin/permission-levels/data')
             ->assertStatus(403);
    }

    public function test_admin_can_view_index(): void
    {
        $admin = User::on('users')->find($this->adminId);

        $this->actingAs($admin)
             ->withSession(['access_rights' => 'admin'])
             ->get('/admin/permission-levels')
             ->assertStatus(200)
             ->assertViewIs('admin.permission_levels');
    }

    public function test_admin_can_fetch_data_with_expected_shape(): void
    {
        $admin = User::on('users')->find($this->adminId);

        $response = $this->actingAs($admin)
             ->withSession(['access_rights' => 'admin'])
             ->getJson('/admin/permission-levels/data')
             ->assertStatus(200);

        $response->assertJsonStructure(['levels', 'modules' => ['dtr' => [['id', 'module', 'code', 'description', 'levels']]]]);

        $data = $response->json();
        $this->assertContains('admin', $data['levels']);
        $this->assertContains('standard', $data['levels']);

        $row = collect($data['modules']['dtr'])->firstWhere('code', $this->permCode);
        $this->assertNotNull($row);
        $this->assertTrue($row['levels']['admin']);
        $this->assertTrue($row['levels']['standard']);
    }

    public function test_admin_can_grant_permission_to_level(): void
    {
        $admin = User::on('users')->find($this->adminId);

        $this->actingAs($admin)
             ->withSession(['access_rights' => 'admin'])
             ->postJson('/admin/permission-levels/update', [
                 'level'   => 'hr',
                 'code'    => $this->permCode,
                 'granted' => true,
             ])
             ->assertStatus(200)
             ->assertJson(['message' => 'Updated.']);

        $this->assertTrue(
            DB::table('hris.access_level_permissions')
                ->where('level', 'hr')
                ->where('permission_id', $this->permId)
                ->exists()
        );
    }

    public function test_admin_can_revoke_permission_from_level(): void
    {
        $admin = User::on('users')->find($this->adminId);

        $this->actingAs($admin)
             ->withSession(['access_rights' => 'admin'])
             ->postJson('/admin/permission-levels/update', [
                 'level'   => 'standard',
                 'code'    => $this->permCode,
                 'granted' => false,
             ])
             ->assertStatus(200)
             ->assertJson(['message' => 'Updated.']);

        $this->assertFalse(
            DB::table('hris.access_level_permissions')
                ->where('level', 'standard')
                ->where('permission_id', $this->permId)
                ->exists()
        );
    }

    public function test_admin_cannot_modify_admin_level(): void
    {
        $admin = User::on('users')->find($this->adminId);

        $this->actingAs($admin)
             ->withSession(['access_rights' => 'admin'])
             ->postJson('/admin/permission-levels/update', [
                 'level'   => 'admin',
                 'code'    => $this->permCode,
                 'granted' => false,
             ])
             ->assertStatus(422)
             ->assertJson(['message' => 'Cannot modify admin level.']);
    }

    public function test_update_rejects_invalid_level(): void
    {
        $admin = User::on('users')->find($this->adminId);

        $this->actingAs($admin)
             ->withSession(['access_rights' => 'admin'])
             ->postJson('/admin/permission-levels/update', [
                 'level'   => 'superadmin',
                 'code'    => $this->permCode,
                 'granted' => true,
             ])
             ->assertStatus(422)
             ->assertJson(['message' => 'Invalid level.']);
    }

    public function test_update_rejects_unknown_permission_code(): void
    {
        $admin = User::on('users')->find($this->adminId);

        $this->actingAs($admin)
             ->withSession(['access_rights' => 'admin'])
             ->postJson('/admin/permission-levels/update', [
                 'level'   => 'hr',
                 'code'    => 'does.not_exist_' . uniqid(),
                 'granted' => true,
             ])
             ->assertStatus(422)
             ->assertJson(['message' => 'Unknown permission code.']);
    }
}
