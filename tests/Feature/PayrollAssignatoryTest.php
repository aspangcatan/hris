<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PayrollAssignatoryTest extends TestCase
{
    use DatabaseTransactions;

    private array $snapshot = [];
    private array $seededIds = [];
    private int $approverUserId = 9601;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);

        $this->snapshot = DB::table('payroll_assignatories')->get()->map(fn($row) => (array) $row)->all();
        DB::table('payroll_assignatories')->delete();

        DB::table('tdh_user.users')->where('id', $this->approverUserId)->delete();
        DB::table('tdh_user.users')->insert([
            'id' => $this->approverUserId,
            'fname' => 'Assignatory',
            'lname' => 'Tester',
            'username' => 'assignatorytester' . $this->approverUserId,
            'password' => 'x',
            'division' => 2,
            'section' => 21,
            'designation' => 52,
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('payroll_assignatories')->delete();
        if (!empty($this->snapshot)) {
            DB::table('payroll_assignatories')->insert($this->snapshot);
        }
        DB::table('tdh_user.users')->where('id', $this->approverUserId)->delete();
        parent::tearDown();
    }

    private function actAsAdmin(): void
    {
        Auth::shouldReceive('id')->andReturn(9600);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'admin']);
    }

    /** @test */
    public function list_is_empty_when_nothing_configured(): void
    {
        $this->actAsAdmin();

        $response = $this->withoutMiddleware()->getJson('/payroll-assignatories');
        $response->assertOk();
        $response->assertJson(['success' => true, 'data' => []]);
    }

    /** @test */
    public function saving_a_new_assignatory_persists_and_round_trips(): void
    {
        $this->actAsAdmin();

        $saveResponse = $this->withoutMiddleware()->postJson('/payroll-assignatories', [
            'assignatories' => [[
                'label' => 'Section Head',
                'user_id' => $this->approverUserId,
                'order_no' => 1,
                'is_active' => true,
            ]],
        ]);
        $saveResponse->assertOk();
        $saveResponse->assertJson(['success' => true]);

        $listResponse = $this->withoutMiddleware()->getJson('/payroll-assignatories');
        $listResponse->assertOk();
        $data = $listResponse->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Section Head', $data[0]['label']);
        $this->assertEquals($this->approverUserId, $data[0]['user_id']);
        $this->assertEquals(1, $data[0]['order_no']);
    }

    /** @test */
    public function saving_with_an_existing_id_updates_in_place(): void
    {
        $this->actAsAdmin();

        $id = DB::table('payroll_assignatories')->insertGetId([
            'order_no' => 1,
            'label' => 'Old Label',
            'user_id' => $this->approverUserId,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withoutMiddleware()->postJson('/payroll-assignatories', [
            'assignatories' => [[
                'id' => $id,
                'label' => 'New Label',
                'user_id' => $this->approverUserId,
                'order_no' => 1,
                'is_active' => true,
            ]],
        ]);
        $response->assertOk();

        $this->assertEquals(1, DB::table('payroll_assignatories')->count());
        $this->assertEquals('New Label', DB::table('payroll_assignatories')->where('id', $id)->value('label'));
    }

    /** @test */
    public function saving_rejects_an_empty_label(): void
    {
        $this->actAsAdmin();

        $response = $this->withoutMiddleware()->postJson('/payroll-assignatories', [
            'assignatories' => [[
                'label' => '',
                'user_id' => $this->approverUserId,
                'order_no' => 1,
            ]],
        ]);
        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertEquals(0, DB::table('payroll_assignatories')->count());
    }

    /** @test */
    public function saving_rejects_a_nonexistent_user_id(): void
    {
        $this->actAsAdmin();

        $response = $this->withoutMiddleware()->postJson('/payroll-assignatories', [
            'assignatories' => [[
                'label' => 'Ghost Approver',
                'user_id' => 999999999,
                'order_no' => 1,
            ]],
        ]);
        $response->assertStatus(422);
        $this->assertEquals(0, DB::table('payroll_assignatories')->count());
    }
}
