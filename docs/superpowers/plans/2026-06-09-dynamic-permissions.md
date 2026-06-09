# Dynamic Access Rights Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Activate the pre-existing `permissions`, `user_permissions`, and `access_level_permissions` tables to add a per-user, additive+revocable permission layer on top of the existing level system, with an admin UI for management.

**Architecture:** A `PermissionService` resolves effective permissions (level defaults + user grants − user revokes) with per-request caching. A `CheckPermission` middleware enforces route-level access. An admin-only page at `/admin/permissions` lets admin assign/revoke permissions per user with a full audit trail.

**Tech Stack:** Laravel 8, MySQL (dual DB: `hris` + `tdh_user`), Bootstrap 5, jQuery

---

## File Map

| File | Action |
|------|--------|
| `database/seeders/PermissionSeeder.php` | Create |
| `database/seeders/DatabaseSeeder.php` | Modify — add call |
| `app/Services/PermissionService.php` | Create |
| `app/Http/Middleware/CheckPermission.php` | Create |
| `app/Http/Kernel.php` | Modify — register alias |
| `app/Providers/AppServiceProvider.php` | Modify — Blade directive |
| `app/Http/Controllers/Admin/PermissionController.php` | Create |
| `resources/views/admin/permissions.blade.php` | Create |
| `resources/views/layouts/sidebar.blade.php` | Modify — add admin link |
| `routes/web.php` | Modify — add admin routes + middleware on existing routes |
| `app/Http/Controllers/DTRController.php` | Modify — replace inline checks |
| `app/Http/Controllers/EmployeeController.php` | Modify — replace inline checks |
| `app/Http/Controllers/LeaveApplicationController.php` | Modify — replace inline checks |
| `app/Http/Controllers/PayrollController.php` | Modify — replace inline checks |
| `app/Http/Controllers/OvertimeController.php` | Modify — replace inline checks |
| `app/Http/Controllers/PassSlipController.php` | Modify — replace inline checks |
| `app/Http/Controllers/SalnAdminController.php` | Modify — replace inline checks |
| `app/Http/Controllers/HospitalOrderController.php` | Modify — replace inline checks |
| `tests/Feature/PermissionServiceTest.php` | Create |
| `tests/Feature/CheckPermissionMiddlewareTest.php` | Create |
| `tests/Feature/PermissionControllerTest.php` | Create |

---

### Task 1: PermissionSeeder

**Files:**
- Create: `database/seeders/PermissionSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Create the seeder**

```php
<?php
// database/seeders/PermissionSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed permissions catalog
        $catalog = [
            ['module' => 'dtr',            'code' => 'dtr.view_own',            'description' => 'View own DTR'],
            ['module' => 'dtr',            'code' => 'dtr.view_others',         'description' => "View other employees' DTR"],
            ['module' => 'dtr',            'code' => 'dtr.edit',                'description' => 'Edit/correct DTR entries'],
            ['module' => 'dtr',            'code' => 'dtr.print',               'description' => 'Print DTR'],
            ['module' => 'employee',       'code' => 'employee.view',           'description' => 'View employee profiles'],
            ['module' => 'employee',       'code' => 'employee.edit',           'description' => 'Edit employee PDS data'],
            ['module' => 'leave',          'code' => 'leave.file',              'description' => 'File leave applications'],
            ['module' => 'leave',          'code' => 'leave.approve',           'description' => 'Approve leave applications'],
            ['module' => 'leave',          'code' => 'leave.manage_credits',    'description' => 'Add/edit leave credits'],
            ['module' => 'leave',          'code' => 'leave.manage_types',      'description' => 'Manage leave types'],
            ['module' => 'payroll',        'code' => 'payroll.view',            'description' => 'View payroll records'],
            ['module' => 'payroll',        'code' => 'payroll.manage',          'description' => 'Create/edit payrolls'],
            ['module' => 'payroll',        'code' => 'payroll.settings',        'description' => 'Manage payroll settings'],
            ['module' => 'overtime',       'code' => 'overtime.file',           'description' => 'File overtime requests'],
            ['module' => 'overtime',       'code' => 'overtime.approve',        'description' => 'Approve overtime requests'],
            ['module' => 'passslip',       'code' => 'passslip.file',           'description' => 'File pass slips'],
            ['module' => 'passslip',       'code' => 'passslip.approve',        'description' => 'Approve pass slips'],
            ['module' => 'schedule',       'code' => 'schedule.view',           'description' => 'View schedules'],
            ['module' => 'schedule',       'code' => 'schedule.manage',         'description' => 'Manage schedules'],
            ['module' => 'saln',           'code' => 'saln.view_own',           'description' => 'View own SALN'],
            ['module' => 'saln',           'code' => 'saln.admin',              'description' => 'Administer all SALNs'],
            ['module' => 'hospital_order', 'code' => 'hospital_order.file',     'description' => 'File hospital orders'],
            ['module' => 'hospital_order', 'code' => 'hospital_order.approve',  'description' => 'Approve/manage hospital orders'],
            ['module' => 'reports',        'code' => 'reports.quarterly_leave', 'description' => 'Access quarterly leave balance reports'],
        ];

        foreach ($catalog as $p) {
            DB::table('hris.permissions')->updateOrInsert(
                ['code' => $p['code']],
                array_merge($p, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        // 2. Helper closure: get permission id by code
        $pid = fn(string $code) => DB::table('hris.permissions')->where('code', $code)->value('id');

        // 3. Level defaults — mirrors current implicit access exactly
        $levelDefaults = [
            'admin' => array_column($catalog, 'code'),
            'hr' => [
                'dtr.view_own','dtr.view_others','dtr.print',
                'employee.view','employee.edit',
                'leave.file','leave.approve','leave.manage_credits','leave.manage_types',
                'payroll.view','payroll.manage',
                'overtime.file','overtime.approve',
                'passslip.file','passslip.approve',
                'schedule.view',
                'saln.view_own','saln.admin',
                'hospital_order.file','hospital_order.approve',
                'reports.quarterly_leave',
            ],
            'hrh' => [
                'dtr.view_own','dtr.view_others','dtr.print',
                'employee.view','employee.edit',
                'leave.file','leave.approve','leave.manage_credits','leave.manage_types',
                'payroll.view','payroll.manage',
                'overtime.file','overtime.approve',
                'passslip.file','passslip.approve',
                'schedule.view',
                'saln.view_own','saln.admin',
                'hospital_order.file','hospital_order.approve',
                'reports.quarterly_leave',
            ],
            'dh' => [
                'dtr.view_own','dtr.view_others','dtr.print',
                'employee.view',
                'leave.file','leave.approve',
                'overtime.file','overtime.approve',
                'passslip.file','passslip.approve',
                'schedule.view',
                'saln.view_own',
                'hospital_order.file','hospital_order.approve',
                'payroll.view',
                'reports.quarterly_leave',
            ],
            'schedule' => [
                'dtr.view_own','dtr.view_others','dtr.edit','dtr.print',
                'schedule.view','schedule.manage',
            ],
            'it' => [
                'dtr.view_own','dtr.view_others','dtr.edit','dtr.print',
            ],
            'standard' => [
                'dtr.view_own','dtr.print',
                'employee.view',
                'leave.file',
                'overtime.file',
                'passslip.file',
                'saln.view_own',
                'schedule.view',
                'hospital_order.file',
            ],
        ];

        foreach ($levelDefaults as $level => $codes) {
            foreach ($codes as $code) {
                $permId = $pid($code);
                if (!$permId) continue;
                DB::table('hris.access_level_permissions')->updateOrInsert(
                    ['level' => $level, 'permission_id' => $permId],
                    ['level' => $level, 'permission_id' => $permId, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        // 4. Auto-grant approval permissions to existing section/division heads
        $approvalIds = array_filter(array_map($pid, [
            'leave.approve','overtime.approve','passslip.approve','hospital_order.approve',
        ]));

        $shIds = DB::table('tdh_user.users as u')
            ->join('tdh_user.section as s', 's.head', '=', 'u.id')
            ->pluck('u.id');
        $dhIds = DB::table('tdh_user.users as u')
            ->join('tdh_user.division as d', 'd.head', '=', 'u.id')
            ->pluck('u.id');
        $headIds = $shIds->merge($dhIds)->unique();

        foreach ($headIds as $userId) {
            foreach ($approvalIds as $permId) {
                DB::table('hris.user_permissions')->updateOrInsert(
                    ['user_id' => $userId, 'permission_id' => $permId],
                    ['user_id' => $userId, 'permission_id' => $permId, 'type' => 'grant',
                     'created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        $this->command?->info('PermissionSeeder: ' . count($catalog) . ' permissions seeded, auto-granted to ' . $headIds->count() . ' heads.');
    }
}
```

- [ ] **Step 2: Register in DatabaseSeeder**

Open `database/seeders/DatabaseSeeder.php` and add:

```php
public function run()
{
    $this->call(MinuteConversionSeeder::class);
    $this->call(PermissionSeeder::class);
}
```

- [ ] **Step 3: Run the seeder**

```bash
php artisan db:seed --class=PermissionSeeder
```

Expected output:
```
PermissionSeeder: 24 permissions seeded, auto-granted to X heads.
```

- [ ] **Step 4: Verify in DB**

```bash
php artisan tinker --execute="echo DB::table('hris.permissions')->count() . ' permissions, ' . DB::table('hris.access_level_permissions')->count() . ' level defaults';"
```

Expected: `24 permissions, N level defaults`

- [ ] **Step 5: Commit**

```bash
git add database/seeders/PermissionSeeder.php database/seeders/DatabaseSeeder.php
git commit -m "feat: add PermissionSeeder with 24 permission codes and level defaults"
```

---

### Task 2: PermissionService

**Files:**
- Create: `app/Services/PermissionService.php`

- [ ] **Step 1: Create the service**

```php
<?php
// app/Services/PermissionService.php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PermissionService
{
    /** Per-request cache: userId => string[] of effective permission codes */
    private array $cache = [];

    public function can(int $userId, string $code): bool
    {
        return in_array($code, $this->effectiveCodes($userId), true);
    }

    /** Returns flat array of effective permission code strings for a user. */
    public function getEffectivePermissions(int $userId): array
    {
        return $this->effectiveCodes($userId);
    }

    /**
     * Returns full catalog grouped by module with per-row state, for the admin UI.
     * Each row: [id, module, code, description, from_level, granted, revoked, effective]
     */
    public function getForAdminUI(int $userId): array
    {
        $level = $this->getUserLevel($userId);

        $levelIds = DB::table('hris.access_level_permissions')
            ->where('level', $level)
            ->pluck('permission_id')
            ->flip()
            ->toArray();

        $overrides = DB::table('hris.user_permissions')
            ->where('user_id', $userId)
            ->pluck('type', 'permission_id')
            ->toArray();

        return DB::table('hris.permissions')
            ->orderBy('module')
            ->orderBy('code')
            ->get()
            ->map(function ($p) use ($levelIds, $overrides) {
                $fromLevel = array_key_exists($p->id, $levelIds);
                $granted   = ($overrides[$p->id] ?? null) === 'grant';
                $revoked   = ($overrides[$p->id] ?? null) === 'revoke';
                return [
                    'id'          => $p->id,
                    'module'      => $p->module,
                    'code'        => $p->code,
                    'description' => $p->description,
                    'from_level'  => $fromLevel,
                    'granted'     => $granted,
                    'revoked'     => $revoked,
                    'effective'   => ($fromLevel || $granted) && !$revoked,
                ];
            })
            ->groupBy('module')
            ->toArray();
    }

    /** Grant or revoke a permission for a user. $type must be 'grant' or 'revoke'. */
    public function setUserPermission(int $adminId, int $targetUserId, string $code, string $type): void
    {
        $permId = DB::table('hris.permissions')->where('code', $code)->value('id');
        if (!$permId) {
            throw new \InvalidArgumentException("Unknown permission code: {$code}");
        }

        DB::table('hris.user_permissions')->updateOrInsert(
            ['user_id' => $targetUserId, 'permission_id' => $permId],
            ['user_id' => $targetUserId, 'permission_id' => $permId, 'type' => $type,
             'created_at' => now(), 'updated_at' => now()]
        );

        DB::table('hris.permission_audit_logs')->insert([
            'performer_id'   => $adminId,
            'target_user_id' => $targetUserId,
            'action'         => "{$type}d {$code}",
            'details'        => json_encode(['code' => $code, 'type' => $type]),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        unset($this->cache[$targetUserId]);
    }

    /** Remove the per-user override for $code, restoring level default. */
    public function removeUserPermission(int $adminId, int $targetUserId, string $code): void
    {
        $permId = DB::table('hris.permissions')->where('code', $code)->value('id');
        if (!$permId) return;

        DB::table('hris.user_permissions')
            ->where('user_id', $targetUserId)
            ->where('permission_id', $permId)
            ->delete();

        DB::table('hris.permission_audit_logs')->insert([
            'performer_id'   => $adminId,
            'target_user_id' => $targetUserId,
            'action'         => "reset {$code} to level default",
            'details'        => json_encode(['code' => $code, 'type' => 'reset']),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        unset($this->cache[$targetUserId]);
    }

    /** Remove ALL per-user overrides, fully restoring level defaults. */
    public function resetUserPermissions(int $adminId, int $targetUserId): void
    {
        $removedCodes = DB::table('hris.user_permissions as up')
            ->join('hris.permissions as p', 'p.id', '=', 'up.permission_id')
            ->where('up.user_id', $targetUserId)
            ->pluck('p.code')
            ->toArray();

        DB::table('hris.user_permissions')->where('user_id', $targetUserId)->delete();

        DB::table('hris.permission_audit_logs')->insert([
            'performer_id'   => $adminId,
            'target_user_id' => $targetUserId,
            'action'         => 'reset all permissions to level defaults',
            'details'        => json_encode(['removed' => $removedCodes]),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        unset($this->cache[$targetUserId]);
    }

    // ── private helpers ──────────────────────────────────────────────────────

    private function effectiveCodes(int $userId): array
    {
        if (isset($this->cache[$userId])) {
            return $this->cache[$userId];
        }

        $level = $this->getUserLevel($userId);

        $levelIds = DB::table('hris.access_level_permissions')
            ->where('level', $level)
            ->pluck('permission_id');

        $grantIds = DB::table('hris.user_permissions')
            ->where('user_id', $userId)->where('type', 'grant')
            ->pluck('permission_id');

        $revokeFlip = DB::table('hris.user_permissions')
            ->where('user_id', $userId)->where('type', 'revoke')
            ->pluck('permission_id')
            ->flip()
            ->toArray();

        $effectiveIds = $levelIds->merge($grantIds)
            ->unique()
            ->filter(fn($id) => !array_key_exists($id, $revokeFlip))
            ->values();

        $this->cache[$userId] = DB::table('hris.permissions')
            ->whereIn('id', $effectiveIds)
            ->pluck('code')
            ->toArray();

        return $this->cache[$userId];
    }

    public function getUserLevel(int $userId): string
    {
        return strtolower(
            DB::table('tdh_user.user_priv')
                ->where('user_id', $userId)
                ->value('level') ?? 'standard'
        );
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Services/PermissionService.php
git commit -m "feat: add PermissionService with grant/revoke resolution and request-level cache"
```

---

### Task 3: PermissionService Tests

**Files:**
- Create: `tests/Feature/PermissionServiceTest.php`

- [ ] **Step 1: Create the test file**

```php
<?php
// tests/Feature/PermissionServiceTest.php

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

        // Insert a test user in tdh_user
        $this->testUserId = DB::table('tdh_user.users')->insertGetId([
            'fname' => 'Test', 'lname' => 'User', 'mname' => '',
            'email' => 'permtest_' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('tdh_user.user_priv')->updateOrInsert(
            ['user_id' => $this->testUserId],
            ['user_id' => $this->testUserId, 'level' => 'standard',
             'created_at' => now(), 'updated_at' => now()]
        );

        // Insert one test permission
        $this->permId = DB::table('hris.permissions')->insertGetId([
            'module' => 'dtr', 'code' => 'dtr.edit_test_' . uniqid(),
            'description' => 'Test permission',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_can_returns_false_when_not_in_level_defaults(): void
    {
        $code = DB::table('hris.permissions')->where('id', $this->permId)->value('code');
        // No level default inserted — standard level does not have this permission
        $this->assertFalse($this->service->can($this->testUserId, $code));
    }

    public function test_can_returns_true_when_in_level_defaults(): void
    {
        $code = DB::table('hris.permissions')->where('id', $this->permId)->value('code');
        DB::table('hris.access_level_permissions')->insert([
            'level' => 'standard', 'permission_id' => $this->permId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertTrue($this->service->can($this->testUserId, $code));
    }

    public function test_explicit_grant_adds_permission_not_in_level(): void
    {
        $code = DB::table('hris.permissions')->where('id', $this->permId)->value('code');
        // No level default — but explicitly granted
        DB::table('hris.user_permissions')->insert([
            'user_id' => $this->testUserId, 'permission_id' => $this->permId,
            'type' => 'grant', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertTrue($this->service->can($this->testUserId, $code));
    }

    public function test_explicit_revoke_removes_level_default(): void
    {
        $code = DB::table('hris.permissions')->where('id', $this->permId)->value('code');
        // Permission IS in level defaults
        DB::table('hris.access_level_permissions')->insert([
            'level' => 'standard', 'permission_id' => $this->permId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        // But explicitly revoked
        DB::table('hris.user_permissions')->insert([
            'user_id' => $this->testUserId, 'permission_id' => $this->permId,
            'type' => 'revoke', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertFalse($this->service->can($this->testUserId, $code));
    }

    public function test_setUserPermission_creates_grant_and_audit_log(): void
    {
        $adminId = $this->testUserId;
        $code = DB::table('hris.permissions')->where('id', $this->permId)->value('code');

        $this->service->setUserPermission($adminId, $this->testUserId, $code, 'grant');

        $this->assertDatabaseHas('hris.user_permissions', [
            'user_id'       => $this->testUserId,
            'permission_id' => $this->permId,
            'type'          => 'grant',
        ]);
        $this->assertDatabaseHas('hris.permission_audit_logs', [
            'performer_id'   => $adminId,
            'target_user_id' => $this->testUserId,
            'action'         => "granted {$code}",
        ]);
    }

    public function test_removeUserPermission_deletes_override_and_logs(): void
    {
        $adminId = $this->testUserId;
        $code = DB::table('hris.permissions')->where('id', $this->permId)->value('code');

        DB::table('hris.user_permissions')->insert([
            'user_id' => $this->testUserId, 'permission_id' => $this->permId,
            'type' => 'grant', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->service->removeUserPermission($adminId, $this->testUserId, $code);

        $this->assertDatabaseMissing('hris.user_permissions', [
            'user_id' => $this->testUserId, 'permission_id' => $this->permId,
        ]);
        $this->assertDatabaseHas('hris.permission_audit_logs', [
            'action' => "reset {$code} to level default",
        ]);
    }

    public function test_results_are_cached_per_instance(): void
    {
        $code = DB::table('hris.permissions')->where('id', $this->permId)->value('code');

        // First call — no permission
        $this->assertFalse($this->service->can($this->testUserId, $code));

        // Add the permission directly to DB (bypassing service)
        DB::table('hris.user_permissions')->insert([
            'user_id' => $this->testUserId, 'permission_id' => $this->permId,
            'type' => 'grant', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Second call on same service instance — still false (cached)
        $this->assertFalse($this->service->can($this->testUserId, $code));
    }
}
```

- [ ] **Step 2: Run the tests**

```bash
php artisan test tests/Feature/PermissionServiceTest.php
```

Expected: 6 tests pass.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/PermissionServiceTest.php
git commit -m "test: add PermissionService tests covering grant/revoke/cache/audit"
```

---

### Task 4: CheckPermission Middleware + Blade Directive

**Files:**
- Create: `app/Http/Middleware/CheckPermission.php`
- Modify: `app/Http/Kernel.php` (line 64, after `'verified'`)
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Create the middleware**

```php
<?php
// app/Http/Middleware/CheckPermission.php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{
    public function __construct(private PermissionService $permissions) {}

    public function handle(Request $request, Closure $next, string $code): mixed
    {
        if (!Auth::check()) {
            return $request->expectsJson()
                ? response()->json(['error' => 'Unauthenticated'], 401)
                : redirect()->route('login');
        }

        if (!$this->permissions->can(Auth::id(), $code)) {
            return $request->expectsJson()
                ? response()->json(['error' => 'Unauthorized'], 403)
                : redirect()->route('dashboard')->with('error', 'You do not have permission to access that page.');
        }

        return $next($request);
    }
}
```

- [ ] **Step 2: Register in Kernel.php**

In `app/Http/Kernel.php`, add inside `$routeMiddleware` after line 64 (`'verified' => ...`):

```php
        'permission' => \App\Http\Middleware\CheckPermission::class,
```

The `$routeMiddleware` array should now end with:
```php
        'verified'   => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'permission' => \App\Http\Middleware\CheckPermission::class,
    ];
```

- [ ] **Step 3: Register the @permission Blade directive in AppServiceProvider**

Replace the entire `app/Providers/AppServiceProvider.php` with:

```php
<?php

namespace App\Providers;

use App\Services\PermissionService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // if (config('app.env') !== 'local') {
        //     URL::forceScheme('https');
        // }
        Model::preventLazyLoading(!app()->isProduction());

        Blade::directive('permission', function (string $expression) {
            return "<?php if(app(\App\Services\PermissionService::class)->can(\Illuminate\Support\Facades\Auth::id(), {$expression})): ?>";
        });

        Blade::directive('endpermission', function () {
            return '<?php endif; ?>';
        });
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Middleware/CheckPermission.php app/Http/Kernel.php app/Providers/AppServiceProvider.php
git commit -m "feat: add CheckPermission middleware and @permission Blade directive"
```

---

### Task 5: CheckPermission Middleware Tests

**Files:**
- Create: `tests/Feature/CheckPermissionMiddlewareTest.php`

- [ ] **Step 1: Create the test**

```php
<?php
// tests/Feature/CheckPermissionMiddlewareTest.php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CheckPermissionMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = DB::table('tdh_user.users')->insertGetId([
            'fname' => 'MW', 'lname' => 'Test', 'mname' => '',
            'email' => 'mwtest_' . uniqid() . '@test.com',
            'password' => bcrypt('pw'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('tdh_user.user_priv')->updateOrInsert(
            ['user_id' => $this->userId],
            ['user_id' => $this->userId, 'level' => 'standard',
             'created_at' => now(), 'updated_at' => now()]
        );

        // Seed one permission (dtr.edit_mwtest) with no level default for standard
        DB::table('hris.permissions')->insertOrIgnore([
            'module' => 'dtr', 'code' => 'dtr.edit_mwtest',
            'description' => 'MW Test', 'created_at' => now(), 'updated_at' => now(),
        ]);

        Route::middleware(['web', 'auth', 'permission:dtr.edit_mwtest'])
            ->get('/_mw_test_route', fn() => response('ok', 200));
    }

    public function test_returns_403_json_when_user_lacks_permission(): void
    {
        $user = \App\Models\User::find($this->userId);
        $response = $this->actingAs($user)->getJson('/_mw_test_route');
        $response->assertStatus(403)->assertJson(['error' => 'Unauthorized']);
    }

    public function test_allows_request_when_user_has_permission(): void
    {
        $permId = DB::table('hris.permissions')->where('code', 'dtr.edit_mwtest')->value('id');
        DB::table('hris.user_permissions')->insert([
            'user_id' => $this->userId, 'permission_id' => $permId,
            'type' => 'grant', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $user = \App\Models\User::find($this->userId);
        $response = $this->actingAs($user)->getJson('/_mw_test_route');
        $response->assertStatus(200);
    }
}
```

- [ ] **Step 2: Run the tests**

```bash
php artisan test tests/Feature/CheckPermissionMiddlewareTest.php
```

Expected: 2 tests pass.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/CheckPermissionMiddlewareTest.php
git commit -m "test: add CheckPermission middleware tests"
```

---

### Task 6: Admin PermissionController

**Files:**
- Create: `app/Http/Controllers/Admin/PermissionController.php`
- Create: `tests/Feature/PermissionControllerTest.php`

- [ ] **Step 1: Create the controller**

```php
<?php
// app/Http/Controllers/Admin/PermissionController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PermissionController extends Controller
{
    public function __construct(private PermissionService $permissions)
    {
        $this->middleware(function ($request, $next) {
            if (strtolower((string) session('access_rights')) !== 'admin') {
                return $request->expectsJson()
                    ? response()->json(['error' => 'Unauthorized'], 403)
                    : abort(403);
            }
            return $next($request);
        });
    }

    /** GET /admin/permissions — render the page */
    public function index()
    {
        return view('admin.permissions');
    }

    /** GET /admin/permissions/users?search=&page= — paginated employee list */
    public function users(Request $request)
    {
        $search = $request->input('search', '');

        $query = DB::table('tdh_user.users as u')
            ->leftJoin('tdh_user.user_priv as up', 'up.user_id', '=', 'u.id')
            ->select('u.id', 'u.fname', 'u.lname', 'u.mname',
                     DB::raw("COALESCE(up.level, 'standard') as level"))
            ->where(function ($q) use ($search) {
                if ($search) {
                    $q->where('u.fname', 'like', "%{$search}%")
                      ->orWhere('u.lname', 'like', "%{$search}%");
                }
            })
            ->orderBy('u.lname')
            ->orderBy('u.fname');

        return response()->json($query->paginate(20));
    }

    /** GET /admin/permissions/users/{id} — permission state for one user */
    public function show(int $id)
    {
        $user = DB::table('tdh_user.users as u')
            ->leftJoin('tdh_user.user_priv as up', 'up.user_id', '=', 'u.id')
            ->where('u.id', $id)
            ->select('u.id', 'u.fname', 'u.lname',
                     DB::raw("COALESCE(up.level, 'standard') as level"))
            ->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $permissions = $this->permissions->getForAdminUI($id);

        $auditLog = DB::table('hris.permission_audit_logs as pal')
            ->leftJoin('tdh_user.users as u', 'u.id', '=', 'pal.performer_id')
            ->where('pal.target_user_id', $id)
            ->select('pal.action', 'pal.created_at',
                     DB::raw("CONCAT(u.fname, ' ', u.lname) as performed_by"))
            ->orderByDesc('pal.created_at')
            ->limit(20)
            ->get();

        return response()->json([
            'user'        => $user,
            'permissions' => $permissions,
            'audit_log'   => $auditLog,
        ]);
    }

    /** POST /admin/permissions/users/{id} — set a permission override */
    public function update(Request $request, int $id)
    {
        $request->validate([
            'code' => 'required|string',
            'type' => 'required|in:grant,revoke,reset',
        ]);

        $code = $request->input('code');
        $type = $request->input('type');

        if ($type === 'reset') {
            $this->permissions->removeUserPermission(Auth::id(), $id, $code);
        } else {
            $this->permissions->setUserPermission(Auth::id(), $id, $code, $type);
        }

        return response()->json(['message' => "Permission {$type}d successfully."]);
    }

    /** DELETE /admin/permissions/users/{id}/reset — reset all overrides */
    public function reset(int $id)
    {
        $this->permissions->resetUserPermissions(Auth::id(), $id);
        return response()->json(['message' => 'All permissions reset to level defaults.']);
    }
}
```

- [ ] **Step 2: Create the controller test**

```php
<?php
// tests/Feature/PermissionControllerTest.php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PermissionControllerTest extends TestCase
{
    use DatabaseTransactions;

    private int $adminId;
    private int $targetId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminId = DB::table('tdh_user.users')->insertGetId([
            'fname' => 'Admin', 'lname' => 'User', 'mname' => '',
            'email' => 'admin_' . uniqid() . '@test.com',
            'password' => bcrypt('pw'), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('tdh_user.user_priv')->updateOrInsert(
            ['user_id' => $this->adminId],
            ['user_id' => $this->adminId, 'level' => 'admin',
             'created_at' => now(), 'updated_at' => now()]
        );

        $this->targetId = DB::table('tdh_user.users')->insertGetId([
            'fname' => 'Target', 'lname' => 'User', 'mname' => '',
            'email' => 'target_' . uniqid() . '@test.com',
            'password' => bcrypt('pw'), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('tdh_user.user_priv')->updateOrInsert(
            ['user_id' => $this->targetId],
            ['user_id' => $this->targetId, 'level' => 'standard',
             'created_at' => now(), 'updated_at' => now()]
        );

        DB::table('hris.permissions')->insertOrIgnore([
            'module' => 'dtr', 'code' => 'dtr.edit',
            'description' => 'Edit DTR', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_non_admin_cannot_access_users_list(): void
    {
        $user = \App\Models\User::find($this->targetId);
        $this->actingAs($user)
            ->withSession(['access_rights' => 'standard'])
            ->getJson('/admin/permissions/users')
            ->assertStatus(403);
    }

    public function test_admin_can_get_users_list(): void
    {
        $admin = \App\Models\User::find($this->adminId);
        $this->actingAs($admin)
            ->withSession(['access_rights' => 'admin'])
            ->getJson('/admin/permissions/users')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'total']);
    }

    public function test_admin_can_grant_permission(): void
    {
        $admin = \App\Models\User::find($this->adminId);
        $permId = DB::table('hris.permissions')->where('code', 'dtr.edit')->value('id');

        $this->actingAs($admin)
            ->withSession(['access_rights' => 'admin'])
            ->postJson("/admin/permissions/users/{$this->targetId}", [
                'code' => 'dtr.edit',
                'type' => 'grant',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('hris.user_permissions', [
            'user_id'       => $this->targetId,
            'permission_id' => $permId,
            'type'          => 'grant',
        ]);
    }

    public function test_admin_can_reset_all_permissions(): void
    {
        $admin = \App\Models\User::find($this->adminId);
        $permId = DB::table('hris.permissions')->where('code', 'dtr.edit')->value('id');
        DB::table('hris.user_permissions')->insert([
            'user_id' => $this->targetId, 'permission_id' => $permId,
            'type' => 'grant', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession(['access_rights' => 'admin'])
            ->deleteJson("/admin/permissions/users/{$this->targetId}/reset")
            ->assertStatus(200);

        $this->assertDatabaseMissing('hris.user_permissions', [
            'user_id' => $this->targetId,
        ]);
    }
}
```

- [ ] **Step 3: Run tests**

```bash
php artisan test tests/Feature/PermissionControllerTest.php
```

Expected: 4 tests pass.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Admin/PermissionController.php tests/Feature/PermissionControllerTest.php
git commit -m "feat: add Admin PermissionController with AJAX endpoints and tests"
```

---

### Task 7: Admin UI View + Routes + Sidebar

**Files:**
- Create: `resources/views/admin/permissions.blade.php`
- Modify: `routes/web.php` — add 5 admin routes inside `auth` group
- Modify: `resources/views/layouts/sidebar.blade.php` — add admin link

- [ ] **Step 1: Create the admin permissions view**

First, create the directory:
```bash
mkdir resources/views/admin
```

Then create `resources/views/admin/permissions.blade.php`:

```blade
@extends('layouts.layout')

@section('content')
<div class="pagetitle">
    <h1>Access Rights Management</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
            <li class="breadcrumb-item active">Access Rights</li>
        </ol>
    </nav>
</div>

<section class="section">
    <div class="row">
        {{-- LEFT: User List --}}
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Employees</h5>
                    <div class="mb-3">
                        <input type="text" id="user_search" class="form-control"
                               placeholder="Search by name..." autocomplete="off">
                    </div>
                    <div id="user_list" style="max-height:65vh; overflow-y:auto;">
                        <p class="text-muted text-center">Loading...</p>
                    </div>
                    <div id="user_pagination" class="mt-2 d-flex justify-content-between align-items-center"></div>
                </div>
            </div>
        </div>

        {{-- RIGHT: Permission Panel --}}
        <div class="col-md-8">
            <div class="card h-100" id="permission_panel" style="display:none;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="card-title mb-0" id="panel_user_name">—</h5>
                            <small class="text-muted">Level: <span id="panel_user_level" class="badge bg-secondary"></span></small>
                        </div>
                        <button class="btn btn-sm btn-outline-danger" id="btn_reset_all">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset to Level Defaults
                        </button>
                    </div>

                    <div id="permissions_by_module"></div>

                    <hr>
                    <h6>Recent Changes</h6>
                    <div id="audit_log" style="max-height:200px; overflow-y:auto; font-size:.85rem;">
                        <p class="text-muted">No changes yet.</p>
                    </div>
                </div>
            </div>
            <div class="card h-100" id="permission_placeholder">
                <div class="card-body d-flex align-items-center justify-content-center">
                    <p class="text-muted">Select an employee to manage their access rights.</p>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection

@push('scripts')
<script>
const ROUTE_USERS      = "{{ route('admin.permissions.users') }}";
const ROUTE_USER_PERMS = "{{ url('/admin/permissions/users') }}";
const CSRF             = "{{ csrf_token() }}";

let currentUserId  = null;
let userPage       = 1;
let searchTimeout  = null;

// ── User List ────────────────────────────────────────────────────────────────
async function loadUsers(page = 1) {
    const search = document.getElementById('user_search').value.trim();
    const url    = `${ROUTE_USERS}?page=${page}&search=${encodeURIComponent(search)}`;
    const res    = await fetch(url, { headers: { Accept: 'application/json' } });
    const body   = await res.json();

    const list = document.getElementById('user_list');
    list.innerHTML = body.data.length === 0
        ? '<p class="text-muted text-center">No employees found.</p>'
        : body.data.map(u => `
            <div class="d-flex align-items-center justify-content-between border-bottom py-2 user-row"
                 style="cursor:pointer" data-id="${u.id}" onclick="selectUser(${u.id})">
                <span>${u.lname}, ${u.fname}</span>
                <span class="badge bg-secondary">${u.level}</span>
            </div>`).join('');

    const pag = document.getElementById('user_pagination');
    pag.innerHTML = `
        <button class="btn btn-sm btn-outline-secondary" ${body.current_page <= 1 ? 'disabled' : ''}
                onclick="loadUsers(${body.current_page - 1})">‹</button>
        <small>Page ${body.current_page} of ${body.last_page}</small>
        <button class="btn btn-sm btn-outline-secondary" ${body.current_page >= body.last_page ? 'disabled' : ''}
                onclick="loadUsers(${body.current_page + 1})">›</button>`;
}

document.getElementById('user_search').addEventListener('input', function () {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => loadUsers(1), 350);
});

// ── Permission Panel ─────────────────────────────────────────────────────────
async function selectUser(id) {
    currentUserId = id;
    document.querySelectorAll('.user-row').forEach(r =>
        r.classList.toggle('table-active', parseInt(r.dataset.id) === id));

    const res  = await fetch(`${ROUTE_USER_PERMS}/${id}`, { headers: { Accept: 'application/json' } });
    const data = await res.json();

    document.getElementById('panel_user_name').textContent  = `${data.user.fname} ${data.user.lname}`;
    document.getElementById('panel_user_level').textContent = data.user.level;

    renderPermissions(data.permissions);
    renderAuditLog(data.audit_log);

    document.getElementById('permission_panel').style.display = '';
    document.getElementById('permission_placeholder').style.display = 'none';
}

function renderPermissions(grouped) {
    const MODULE_LABELS = {
        dtr: 'DTR', employee: 'Employee', leave: 'Leave', payroll: 'Payroll',
        overtime: 'Overtime', passslip: 'Pass Slip', schedule: 'Schedule',
        saln: 'SALN', hospital_order: 'Hospital Order', reports: 'Reports',
    };

    const html = Object.entries(grouped).map(([module, perms]) => `
        <div class="mb-3">
            <div class="fw-semibold text-uppercase text-muted small mb-1">
                ${MODULE_LABELS[module] || module}
            </div>
            ${perms.map(p => renderPermRow(p)).join('')}
        </div>`).join('');

    document.getElementById('permissions_by_module').innerHTML = html;
}

function renderPermRow(p) {
    let badge, btn;
    if (p.revoked) {
        badge = '<span class="badge bg-danger ms-2">Revoked</span>';
        btn   = `<button class="btn btn-sm btn-outline-secondary" onclick="setPermission('${p.code}','reset')">Restore</button>`;
    } else if (p.granted) {
        badge = '<span class="badge bg-success ms-2">Granted</span>';
        btn   = `<button class="btn btn-sm btn-outline-secondary" onclick="setPermission('${p.code}','reset')">Remove</button>`;
    } else if (p.from_level) {
        badge = '<span class="badge bg-light text-dark border ms-2">Level Default</span>';
        btn   = `<button class="btn btn-sm btn-outline-danger" onclick="setPermission('${p.code}','revoke')">Revoke</button>`;
    } else {
        badge = '<span class="badge bg-light text-muted border ms-2">No Access</span>';
        btn   = `<button class="btn btn-sm btn-outline-success" onclick="setPermission('${p.code}','grant')">Grant</button>`;
    }

    return `
        <div class="d-flex align-items-center justify-content-between py-1 border-bottom">
            <span>${p.description}${badge}</span>
            ${btn}
        </div>`;
}

async function setPermission(code, type) {
    await fetch(`${ROUTE_USER_PERMS}/${currentUserId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': CSRF,
        },
        body: JSON.stringify({ code, type }),
    });
    await selectUser(currentUserId);
}

document.getElementById('btn_reset_all').addEventListener('click', async function () {
    if (!currentUserId) return;
    if (!confirm('Reset all custom permissions for this user to level defaults?')) return;
    await fetch(`${ROUTE_USER_PERMS}/${currentUserId}/reset`, {
        method: 'DELETE',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
    });
    await selectUser(currentUserId);
});

function renderAuditLog(logs) {
    const el = document.getElementById('audit_log');
    if (!logs.length) {
        el.innerHTML = '<p class="text-muted mb-0">No changes yet.</p>';
        return;
    }
    el.innerHTML = logs.map(l => `
        <div class="border-bottom py-1">
            <span class="text-capitalize">${l.action}</span>
            <span class="text-muted ms-2">by ${l.performed_by}</span>
            <span class="text-muted float-end">${moment(l.created_at).format('MM/DD/YYYY HH:mm')}</span>
        </div>`).join('');
}

// ── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => loadUsers());
</script>
@endpush
```

- [ ] **Step 2: Add routes in `routes/web.php`**

Inside the `Route::group(['middleware' => 'auth'], function () {` block, add before the closing `});`:

```php
    # ADMIN — Access Rights Management
    Route::get('/admin/permissions', [\App\Http\Controllers\Admin\PermissionController::class, 'index'])
        ->name('admin.permissions');
    Route::get('/admin/permissions/users', [\App\Http\Controllers\Admin\PermissionController::class, 'users'])
        ->name('admin.permissions.users');
    Route::get('/admin/permissions/users/{id}', [\App\Http\Controllers\Admin\PermissionController::class, 'show'])
        ->name('admin.permissions.users.show');
    Route::post('/admin/permissions/users/{id}', [\App\Http\Controllers\Admin\PermissionController::class, 'update'])
        ->name('admin.permissions.users.update');
    Route::delete('/admin/permissions/users/{id}/reset', [\App\Http\Controllers\Admin\PermissionController::class, 'reset'])
        ->name('admin.permissions.users.reset');
```

- [ ] **Step 3: Add sidebar link**

Open `resources/views/layouts/sidebar.blade.php`. Find the admin-only section (look for `@if(in_array(session('access_rights'), ['admin']))`). Add the following inside that block (or create a new admin block if none exists):

```blade
@if(strtolower((string) session('access_rights')) === 'admin')
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.permissions') ? '' : 'collapsed' }}"
           href="{{ route('admin.permissions') }}">
            <i class="bi bi-shield-lock"></i>
            <span>Access Rights</span>
        </a>
    </li>
@endif
```

- [ ] **Step 4: Verify the page loads**

Visit `http://localhost/hris/admin/permissions` as an admin user. The left panel should show a paginated list of employees. Clicking an employee should load their permission toggles on the right.

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/permissions.blade.php routes/web.php resources/views/layouts/sidebar.blade.php
git commit -m "feat: add admin permissions UI page with user list, permission toggles, and audit log"
```

---

### Task 8: Module Migration — DTR

Replace the ad-hoc `in_array(session('access_rights'), [...])` pattern in `DTRController` with `PermissionService`.

**Files:**
- Modify: `app/Http/Controllers/DTRController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Inject PermissionService into DTRController**

Open `app/Http/Controllers/DTRController.php`. Find the constructor (or add one). Add `PermissionService` injection and replace the two private can-methods:

```php
// At top of file, add:
use App\Services\PermissionService;
use Illuminate\Support\Facades\Auth;

// Replace or add constructor:
public function __construct(private PermissionService $permissions) {}

// Replace canManageRawTimelog():
private function canManageRawTimelog(): bool
{
    return $this->permissions->can(Auth::id(), 'dtr.edit');
}

// Replace canAccessEmployeeDtrModule():
private function canAccessEmployeeDtrModule(): bool
{
    return $this->permissions->can(Auth::id(), 'dtr.view_others');
}
```

- [ ] **Step 2: Add middleware to DTR routes in `routes/web.php`**

Find the existing DTR route declarations and add middleware:

```php
Route::get('/dtr', function () {
    return view('dtr');
})->name('dtr')->middleware('permission:dtr.view_own');

Route::get('/employee_dtr', function () {
    return view('employee_dtr');
})->name('employee_dtr')->middleware('permission:dtr.view_others');
```

Also find the print DTR route and add:
```php
Route::get('/print_dtr', ...)->middleware('permission:dtr.print');
// Or wherever the print DTR endpoint is defined
```

- [ ] **Step 3: Run all tests to verify no regression**

```bash
php artisan test
```

Expected: all tests pass.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/DTRController.php routes/web.php
git commit -m "feat: migrate DTR module to PermissionService"
```

---

### Task 9: Module Migration — Employee + Leave

**Files:**
- Modify: `app/Http/Controllers/EmployeeController.php`
- Modify: `app/Http/Controllers/LeaveApplicationController.php`
- Modify: `app/Http/Controllers/LeaveCreditController.php` (if exists)
- Modify: `routes/web.php`

- [ ] **Step 1: Employee controller**

Open `app/Http/Controllers/EmployeeController.php`. Add the import and inject `PermissionService`:

```php
use App\Services\PermissionService;
use Illuminate\Support\Facades\Auth;

// Add to constructor (or add constructor if missing):
public function __construct(private PermissionService $permissions) {}
```

Find the update/edit method that currently checks:
```php
$access_rights = strtolower((string) session('access_rights'));
$can_update_others = in_array($access_rights, ['admin', 'hr', 'hrh']);
```

Replace with:
```php
$can_update_others = $this->permissions->can(Auth::id(), 'employee.edit');
```

- [ ] **Step 2: Leave routes**

In `routes/web.php`, add permission middleware to leave page routes:

```php
Route::get('/leave_application', function () {
    return view('leave_application');
})->name('leave_application')->middleware('permission:leave.file');

Route::get('/leave_approval', function () {
    return view('leave_approval');
})->name('leave_approval')->middleware('permission:leave.approve');
```

- [ ] **Step 3: Leave credit + type management**

In `LeaveApplicationController.php` (or the credit controller), find the admin/HR check for credit management and replace:

```php
// Before:
if (!in_array(strtolower(session('access_rights')), ['admin', 'hr', 'hrh'])) {
    return response()->json(['error' => 'Unauthorized'], 403);
}

// After (add PermissionService injection to constructor first):
if (!$this->permissions->can(Auth::id(), 'leave.manage_credits')) {
    return response()->json(['error' => 'Unauthorized'], 403);
}
```

- [ ] **Step 4: Run all tests**

```bash
php artisan test
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/EmployeeController.php app/Http/Controllers/LeaveApplicationController.php routes/web.php
git commit -m "feat: migrate Employee and Leave modules to PermissionService"
```

---

### Task 10: Module Migration — Payroll + Overtime + Pass Slip + Remaining

**Files:**
- Modify: `app/Http/Controllers/PayrollController.php`
- Modify: `app/Http/Controllers/OvertimeController.php`
- Modify: `app/Http/Controllers/PassSlipController.php`
- Modify: `app/Http/Controllers/SalnAdminController.php`
- Modify: `app/Http/Controllers/HospitalOrderController.php`
- Modify: `routes/web.php`

Apply the same pattern for each controller — the pattern is identical for all:

**Pattern to apply in each controller:**

1. Add `use App\Services\PermissionService;` at the top.
2. Add `public function __construct(private PermissionService $permissions) {}` (merge with existing constructor if one exists).
3. Find each `in_array(session('access_rights'), ['admin', ...])` guard and replace with the corresponding `$this->permissions->can(Auth::id(), 'xxx.yyy')` call.

**Permission code mappings:**

| Old check | New code |
|-----------|----------|
| `['admin']` in PayrollController::store/update | `payroll.manage` |
| `['admin', 'hr', 'hrh']` in PayrollController settings | `payroll.settings` |
| `['admin']` in HospitalOrderController | `hospital_order.approve` |
| Approval check in OvertimeController | `overtime.approve` |
| Filing check in OvertimeController | `overtime.file` |
| Approval check in PassSlipController | `passslip.approve` |
| `['admin', 'hr', 'hrh']` in SalnAdminController | `saln.admin` |

- [ ] **Step 1: Apply pattern to PayrollController**

```php
use App\Services\PermissionService;
use Illuminate\Support\Facades\Auth;

public function __construct(private PermissionService $permissions) {}

// Replace payroll management guards:
if (!$this->permissions->can(Auth::id(), 'payroll.manage')) {
    return response()->json(['error' => 'Unauthorized'], 403);
}

// Replace payroll settings guards:
if (!$this->permissions->can(Auth::id(), 'payroll.settings')) {
    return response()->json(['error' => 'Unauthorized'], 403);
}
```

- [ ] **Step 2: Apply pattern to OvertimeController**

```php
use App\Services\PermissionService;
use Illuminate\Support\Facades\Auth;

public function __construct(private PermissionService $permissions) {}

// Replace approval guards:
if (!$this->permissions->can(Auth::id(), 'overtime.approve')) {
    return response()->json(['error' => 'Unauthorized'], 403);
}
```

- [ ] **Step 3: Apply pattern to PassSlipController**

```php
use App\Services\PermissionService;
use Illuminate\Support\Facades\Auth;

public function __construct(private PermissionService $permissions) {}

if (!$this->permissions->can(Auth::id(), 'passslip.approve')) {
    return response()->json(['error' => 'Unauthorized'], 403);
}
```

- [ ] **Step 4: Apply pattern to SalnAdminController**

```php
use App\Services\PermissionService;
use Illuminate\Support\Facades\Auth;

public function __construct(private PermissionService $permissions) {}

if (!$this->permissions->can(Auth::id(), 'saln.admin')) {
    return response()->json(['error' => 'Unauthorized'], 403);
}
```

- [ ] **Step 5: Apply pattern to HospitalOrderController**

```php
use App\Services\PermissionService;
use Illuminate\Support\Facades\Auth;

public function __construct(private PermissionService $permissions) {}

if (!$this->permissions->can(Auth::id(), 'hospital_order.approve')) {
    return response()->json(['error' => 'Unauthorized'], 403);
}
```

- [ ] **Step 6: Add page-level middleware to remaining routes in `routes/web.php`**

```php
Route::get('/overtime/file', ...)->middleware('permission:overtime.file');
Route::get('/overtime/approval', ...)->middleware('permission:overtime.approve');
Route::get('/pass_slip', ...)->middleware('permission:passslip.file');
Route::get('/pass-slip-approver', ...)->middleware('permission:passslip.approve');
Route::get('/saln-admin', ...)->middleware('permission:saln.admin');
Route::get('/hospital_order', ...)->middleware('permission:hospital_order.file');
Route::get('/hospital_order_approval', ...)->middleware('permission:hospital_order.approve');
Route::get('/reports/quarterly-leave-balance', ...)->middleware('permission:reports.quarterly_leave');
Route::get('/payroll', ...)->middleware('permission:payroll.view');
Route::get('/payroll_settings', ...)->middleware('permission:payroll.settings');
Route::get('/schedule', ...)->middleware('permission:schedule.view');
Route::get('/schedule_master_list', ...)->middleware('permission:schedule.manage');
```

- [ ] **Step 7: Run all tests**

```bash
php artisan test
```

Expected: all tests pass.

- [ ] **Step 8: Final commit**

```bash
git add app/Http/Controllers/PayrollController.php \
        app/Http/Controllers/OvertimeController.php \
        app/Http/Controllers/PassSlipController.php \
        app/Http/Controllers/SalnAdminController.php \
        app/Http/Controllers/HospitalOrderController.php \
        routes/web.php
git commit -m "feat: migrate all remaining modules to PermissionService — dynamic access rights complete"
```
