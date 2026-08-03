<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DtrRawTimelogEditToggleTest extends TestCase
{
    use DatabaseTransactions;

    private int $testUserId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testUserId = DB::connection('users')->table('users')->insertGetId([
            'fname'       => 'ToggleTest',
            'lname'       => 'User',
            'mname'       => '',
            'username'    => 'dtrtoggletest_' . uniqid(),
            'password'    => bcrypt('password'),
            'designation' => 0,
            'division'    => 0,
            'section'     => 0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::connection('users')->table('user_priv')->updateOrInsert(
            ['user_id' => $this->testUserId],
            ['user_id' => $this->testUserId, 'syscode' => 'hris', 'level' => 'standard']
        );

        $permId = DB::table('hris.permissions')->where('code', 'dtr.edit')->value('id');

        DB::table('hris.user_permissions')->insert([
            'user_id'       => $this->testUserId,
            'permission_id' => $permId,
            'type'          => 'grant',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        /** @var User $user */
        $user = User::on('users')->findOrFail($this->testUserId);
        Auth::guard('web')->login($user);
    }

    protected function tearDown(): void
    {
        DB::table('hris.user_permissions')->where('user_id', $this->testUserId)->delete();
        DB::connection('users')->table('user_priv')->where('user_id', $this->testUserId)->delete();
        DB::connection('users')->table('users')->where('id', $this->testUserId)->delete();
        parent::tearDown();
    }

    public function test_store_manual_blocked_when_raw_timelog_editing_disabled_even_with_permission(): void
    {
        config(['hris.dtr_raw_timelog_editing_enabled' => false]);

        $response = $this->postJson(route('storeManual'), [
            'status' => 'IN',
            'date'   => '2026-08-03',
            'time'   => '08:00',
        ]);

        $response->assertStatus(403);
    }

    public function test_delete_time_log_blocked_when_raw_timelog_editing_disabled_even_with_permission(): void
    {
        config(['hris.dtr_raw_timelog_editing_enabled' => false]);

        $response = $this->deleteJson(route('deleteTimeLog'), [
            'log_id' => 999999,
        ]);

        $response->assertStatus(403);
    }
}
