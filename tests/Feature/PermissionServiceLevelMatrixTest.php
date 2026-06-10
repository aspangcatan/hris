<?php

namespace Tests\Feature;

use App\Services\PermissionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PermissionServiceLevelMatrixTest extends TestCase
{
    use DatabaseTransactions;

    private PermissionService $service;
    private int $permId;
    private string $permCode;
    private array $userIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PermissionService();

        $this->permCode = 'dtr.lvlmatrix_test_' . uniqid();
        $this->permId = DB::table('hris.permissions')->insertGetId([
            'module'      => 'dtr',
            'code'        => $this->permCode,
            'description' => 'Level matrix test permission',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Ensure 'hr' and 'sh' levels are discoverable via tdh_user.user_priv
        foreach (['hr', 'sh'] as $level) {
            $userId = DB::connection('users')->table('users')->insertGetId([
                'fname'       => 'LvlMatrix',
                'lname'       => ucfirst($level),
                'mname'       => '',
                'username'    => 'lvlmatrix_' . $level . '_' . uniqid(),
                'password'    => bcrypt('password'),
                'designation' => 0,
                'division'    => 0,
                'section'     => 0,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            DB::connection('users')->table('user_priv')->updateOrInsert(
                ['user_id' => $userId],
                ['user_id' => $userId, 'syscode' => 'hris', 'level' => $level]
            );

            $this->userIds[] = $userId;
        }

        // Grant the test permission to 'hr' by default, leave 'sh' without it
        DB::table('hris.access_level_permissions')->insert([
            'level'         => 'hr',
            'permission_id' => $this->permId,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('hris.access_level_permissions')->where('permission_id', $this->permId)->delete();
        DB::table('hris.permissions')->where('id', $this->permId)->delete();
        DB::connection('users')->table('user_priv')->whereIn('user_id', $this->userIds)->delete();
        DB::connection('users')->table('users')->whereIn('id', $this->userIds)->delete();
        parent::tearDown();
    }

    public function test_matrix_includes_admin_as_always_granted(): void
    {
        $matrix = $this->service->getLevelPermissionMatrix();

        $this->assertContains('admin', $matrix['levels']);

        $row = $this->findTestPermRow($matrix);
        $this->assertTrue($row['levels']['admin']);
    }

    public function test_matrix_reflects_access_level_permissions_rows(): void
    {
        $matrix = $this->service->getLevelPermissionMatrix();

        $this->assertContains('hr', $matrix['levels']);
        $this->assertContains('sh', $matrix['levels']);

        $row = $this->findTestPermRow($matrix);
        $this->assertTrue($row['levels']['hr']);
        $this->assertFalse($row['levels']['sh']);
    }

    public function test_set_level_permission_grants_and_revokes(): void
    {
        // Grant to 'sh' (not currently granted)
        $this->service->setLevelPermission('sh', $this->permCode, true);
        $this->assertTrue(
            DB::table('hris.access_level_permissions')
                ->where('level', 'sh')
                ->where('permission_id', $this->permId)
                ->exists()
        );

        // Revoke from 'hr' (currently granted via setUp)
        $this->service->setLevelPermission('hr', $this->permCode, false);
        $this->assertFalse(
            DB::table('hris.access_level_permissions')
                ->where('level', 'hr')
                ->where('permission_id', $this->permId)
                ->exists()
        );
    }

    private function findTestPermRow(array $matrix): array
    {
        foreach ($matrix['modules']['dtr'] as $row) {
            if ($row['code'] === $this->permCode) {
                return $row;
            }
        }

        $this->fail('Test permission row not found in matrix');
    }
}
