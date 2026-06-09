<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckPermissionMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    private int $testUserId;
    private int $permId;

    protected function setUp(): void
    {
        parent::setUp();

        // Insert via the 'users' connection so User::findOrFail() can see the row within
        // the same connection transaction that DatabaseTransactions wraps.
        $this->testUserId = DB::connection('users')->table('users')->insertGetId([
            'fname'       => 'MW',
            'lname'       => 'TestUser',
            'mname'       => '',
            'username'    => 'mwtest_' . uniqid(),
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

        // Ensure the permission row exists (idempotent across test runs)
        DB::table('hris.permissions')->insertOrIgnore([
            'module'      => 'dtr',
            'code'        => 'dtr.edit_mwtest',
            'description' => 'Middleware test permission',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->permId = DB::table('hris.permissions')
            ->where('code', 'dtr.edit_mwtest')
            ->value('id');
    }

    protected function tearDown(): void
    {
        // Explicit cross-DB cleanup since DatabaseTransactions may not roll back cross-DB writes
        DB::table('hris.user_permissions')->where('user_id', $this->testUserId)->delete();
        DB::table('hris.permission_audit_logs')->where('target_user_id', $this->testUserId)->delete();
        DB::table('hris.permission_audit_logs')->where('performer_id', $this->testUserId)->delete();
        DB::connection('users')->table('user_priv')->where('user_id', $this->testUserId)->delete();
        DB::connection('users')->table('users')->where('id', $this->testUserId)->delete();
        // hris.permissions row for dtr.edit_mwtest is left in place (insertOrIgnore keeps it stable)
        parent::tearDown();
    }

    /**
     * Build a JSON request and log in as the test user, then run the middleware handle().
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    private function runMiddleware(): mixed
    {
        /** @var \App\Models\User $user */
        $user = User::on('users')->findOrFail($this->testUserId);

        // Log the user in via the web guard so Auth::check() and Auth::id() work
        Auth::guard('web')->login($user);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Accept', 'application/json');

        $middleware = new CheckPermission(new PermissionService());

        return $middleware->handle($request, fn ($req) => response('ok', 200), 'dtr.edit_mwtest');
    }

    public function test_returns_403_json_when_user_lacks_permission(): void
    {
        $response = $this->runMiddleware();

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_allows_request_when_user_has_permission(): void
    {
        // Grant the permission explicitly via user_permissions
        DB::table('hris.user_permissions')->insert([
            'user_id'       => $this->testUserId,
            'permission_id' => $this->permId,
            'type'          => 'grant',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $response = $this->runMiddleware();

        $this->assertSame(200, $response->getStatusCode());
    }
}
