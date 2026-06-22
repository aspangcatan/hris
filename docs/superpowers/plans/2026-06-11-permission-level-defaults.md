# Permission Level Defaults Admin Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an admin page at `/admin/permission-levels` where an admin can view and toggle which permission codes are granted by default to each `tdh_user.user_priv.level` value, by editing `hris.access_level_permissions`.

**Architecture:** New `PermissionService::getLevelPermissionMatrix()` / `setLevelPermission()` methods back a new `Admin\PermissionLevelController` (admin-only middleware, same pattern as `Admin\PermissionController`), exposing `GET /admin/permission-levels` (view), `GET /admin/permission-levels/data` (JSON matrix), and `POST /admin/permission-levels/update` (toggle one cell). A new Blade view renders a permission × level grid with live-saving checkboxes, linked from the sidebar next to "Access Rights".

**Tech Stack:** Laravel 8, PHP 7.4, MySQL (`hris` + `tdh_user` via `users` connection), PHPUnit 9 with `DatabaseTransactions`, Blade + vanilla JS/fetch (no SPA framework), Bootstrap 5 (bootstrap-icons `bi-*`).

**Reference spec:** `docs/superpowers/specs/2026-06-11-permission-level-defaults-design.md`

---

## File Structure

- Modify: `app/Services/PermissionService.php` — add `getLevelPermissionMatrix(): array` and `setLevelPermission(string $level, string $code, bool $granted): void`
- Create: `app/Http/Controllers/Admin/PermissionLevelController.php` — `index()`, `data()`, `update(Request $request)`
- Modify: `routes/web.php` — add 3 routes inside the existing `# ADMIN — Access Rights Management` block
- Create: `resources/views/admin/permission_levels.blade.php` — the matrix UI
- Modify: `resources/views/layouts/sidebar.blade.php` — add "Permission Levels" link next to "Access Rights"
- Create: `tests/Feature/PermissionServiceLevelMatrixTest.php` — service-level tests
- Create: `tests/Feature/PermissionLevelControllerTest.php` — controller/route tests

---

### Task 1: PermissionService level-matrix methods

**Files:**
- Modify: `app/Services/PermissionService.php`
- Test: `tests/Feature/PermissionServiceLevelMatrixTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/PermissionServiceLevelMatrixTest.php`:

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=PermissionServiceLevelMatrixTest`
Expected: FAIL — `Call to undefined method App\Services\PermissionService::getLevelPermissionMatrix()`

- [ ] **Step 3: Implement the methods**

In `app/Services/PermissionService.php`, add the following two public methods (place them near `getUserIdsWithPermission`, before the closing `}` of the class):

```php
    public function getLevelPermissionMatrix(): array
    {
        $levels = DB::table('tdh_user.user_priv')
            ->where('syscode', 'hris')
            ->select(DB::raw('LOWER(level) as level'))
            ->distinct()
            ->pluck('level')
            ->all();

        sort($levels);

        $grants = DB::table('hris.access_level_permissions')
            ->get(['level', 'permission_id'])
            ->map(fn($row) => strtolower($row->level) . ':' . $row->permission_id)
            ->flip();

        $modules = DB::table('hris.permissions')
            ->orderBy('module')
            ->orderBy('code')
            ->get()
            ->map(function ($p) use ($levels, $grants) {
                $levelMap = [];
                foreach ($levels as $level) {
                    $levelMap[$level] = $level === 'admin'
                        ? true
                        : $grants->has("{$level}:{$p->id}");
                }
                return [
                    'id'          => $p->id,
                    'module'      => $p->module,
                    'code'        => $p->code,
                    'description' => $p->description,
                    'levels'      => $levelMap,
                ];
            })
            ->groupBy('module')
            ->toArray();

        return [
            'levels'  => $levels,
            'modules' => $modules,
        ];
    }

    public function setLevelPermission(string $level, string $code, bool $granted): void
    {
        $permId = DB::table('hris.permissions')->where('code', $code)->value('id');
        if (!$permId) {
            throw new \InvalidArgumentException("Unknown permission code: {$code}");
        }

        if ($granted) {
            DB::table('hris.access_level_permissions')->updateOrInsert(
                ['level' => $level, 'permission_id' => $permId],
                ['level' => $level, 'permission_id' => $permId, 'created_at' => now(), 'updated_at' => now()]
            );
        } else {
            DB::table('hris.access_level_permissions')
                ->where('level', $level)
                ->where('permission_id', $permId)
                ->delete();
        }
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=PermissionServiceLevelMatrixTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/PermissionService.php tests/Feature/PermissionServiceLevelMatrixTest.php
git commit -m "feat: add PermissionService level-permission matrix methods"
```

---

### Task 2: PermissionLevelController + routes

**Files:**
- Create: `app/Http/Controllers/Admin/PermissionLevelController.php`
- Modify: `routes/web.php:549-558` (append after the existing `admin.permissions.users.reset` route, inside the same group)
- Test: `tests/Feature/PermissionLevelControllerTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/PermissionLevelControllerTest.php`:

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=PermissionLevelControllerTest`
Expected: FAIL — routes `/admin/permission-levels*` don't exist (404s / route not defined errors)

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Admin/PermissionLevelController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PermissionLevelController extends Controller
{
    private const EDITABLE_LEVELS = ['dh', 'hr', 'schedule', 'sh', 'standard'];

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

    public function index()
    {
        return view('admin.permission_levels');
    }

    public function data()
    {
        return response()->json($this->permissions->getLevelPermissionMatrix());
    }

    public function update(Request $request)
    {
        $request->validate([
            'level'   => 'required|string',
            'code'    => 'required|string',
            'granted' => 'required|boolean',
        ]);

        $level = strtolower($request->input('level'));

        if ($level === 'admin') {
            return response()->json(['message' => 'Cannot modify admin level.'], 422);
        }

        if (!in_array($level, self::EDITABLE_LEVELS, true)) {
            return response()->json(['message' => 'Invalid level.'], 422);
        }

        $code = $request->input('code');
        if (!DB::table('hris.permissions')->where('code', $code)->exists()) {
            return response()->json(['message' => 'Unknown permission code.'], 422);
        }

        $this->permissions->setLevelPermission($level, $code, (bool) $request->input('granted'));

        return response()->json(['message' => 'Updated.']);
    }
}
```

- [ ] **Step 4: Add the routes**

In `routes/web.php`, the existing Access Rights block ends like this (around lines 548-559):

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
});
```

Add three new routes immediately after the `admin.permissions.users.reset` route, before the closing `});`:

```php
    Route::delete('/admin/permissions/users/{id}/reset', [\App\Http\Controllers\Admin\PermissionController::class, 'reset'])
        ->name('admin.permissions.users.reset');

    Route::get('/admin/permission-levels', [\App\Http\Controllers\Admin\PermissionLevelController::class, 'index'])
        ->name('admin.permission_levels');
    Route::get('/admin/permission-levels/data', [\App\Http\Controllers\Admin\PermissionLevelController::class, 'data'])
        ->name('admin.permission_levels.data');
    Route::post('/admin/permission-levels/update', [\App\Http\Controllers\Admin\PermissionLevelController::class, 'update'])
        ->name('admin.permission_levels.update');
});
```

- [ ] **Step 5: Run tests to verify they fail with a missing-view error**

Run: `php artisan test --filter=PermissionLevelControllerTest`
Expected: `test_admin_can_view_index` FAILs with "View [admin.permission_levels] not found" (or similar). All other tests (403s, data shape, update grant/revoke/validation) should now PASS since they don't depend on the view.

- [ ] **Step 6: Commit (view created in Task 3 will fix the remaining failure)**

```bash
git add app/Http/Controllers/Admin/PermissionLevelController.php routes/web.php tests/Feature/PermissionLevelControllerTest.php
git commit -m "feat: add PermissionLevelController and permission-levels routes"
```

---

### Task 3: Permission levels view + sidebar link

**Files:**
- Create: `resources/views/admin/permission_levels.blade.php`
- Modify: `resources/views/layouts/sidebar.blade.php:306-314`

- [ ] **Step 1: Create the view**

Create `resources/views/admin/permission_levels.blade.php`:

```blade
@extends('master')

@section('content')
<div class="pagetitle">
    <h1>Permission Levels</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.permissions') }}">Access Rights</a></li>
            <li class="breadcrumb-item active">Permission Levels</li>
        </ol>
    </nav>
</div>

<style>
    .perm-level-row + .perm-level-row { border-top: 1px solid #e9ecef; }
    #permission_levels_table th, #permission_levels_table td { vertical-align: middle; }
</style>

<section class="section">
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Default Permissions by Level</h5>
            <p class="text-muted">
                Toggle which permissions are granted by default to each access level.
                Changes here do not affect individual users' custom permission overrides.
            </p>
            <div class="table-responsive">
                <table class="table table-bordered table-sm" id="permission_levels_table">
                    <thead id="matrix_thead"></thead>
                    <tbody id="matrix_tbody">
                        <tr><td class="text-muted text-center">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection

@section('js')
<script>
const ROUTE_DATA   = "{{ route('admin.permission_levels.data') }}";
const ROUTE_UPDATE = "{{ route('admin.permission_levels.update') }}";
const CSRF         = "{{ csrf_token() }}";

const MODULE_INFO = {
    dtr:            { label: 'DTR',            icon: 'bi-calendar-check' },
    employee:       { label: 'Employee',       icon: 'bi-person-badge' },
    leave:          { label: 'Leave',          icon: 'bi-calendar-event' },
    payroll:        { label: 'Payroll',        icon: 'bi-cash-stack' },
    overtime:       { label: 'Overtime',       icon: 'bi-clock-history' },
    passslip:       { label: 'Pass Slip',      icon: 'bi-door-open' },
    schedule:       { label: 'Schedule',       icon: 'bi-calendar3' },
    saln:           { label: 'SALN',           icon: 'bi-file-earmark-text' },
    hospital_order: { label: 'Hospital Order', icon: 'bi-hospital' },
    reports:        { label: 'Reports',        icon: 'bi-bar-chart-line' },
};

const LEVEL_LABELS = {
    admin: 'Admin', dh: 'DH', hr: 'HR', schedule: 'Schedule', sh: 'SH', standard: 'Standard',
};

async function loadMatrix() {
    const res  = await fetch(ROUTE_DATA, { headers: { Accept: 'application/json' } });
    const data = await res.json();
    renderMatrix(data);
}

function renderMatrix(data) {
    const levels = data.levels;

    const headerCells = levels.map(l => `<th class="text-center">${LEVEL_LABELS[l] || l.toUpperCase()}</th>`).join('');
    document.getElementById('matrix_thead').innerHTML = `<tr><th>Permission</th>${headerCells}</tr>`;

    const bodyRows = Object.entries(data.modules).map(([module, perms]) => {
        const info = MODULE_INFO[module] || { label: module, icon: 'bi-gear' };
        const moduleHeader = `
            <tr class="table-light">
                <th colspan="${levels.length + 1}"><i class="bi ${info.icon} me-1"></i> ${info.label}</th>
            </tr>`;
        const permRows = perms.map(p => renderPermRow(p, levels)).join('');
        return moduleHeader + permRows;
    }).join('');

    document.getElementById('matrix_tbody').innerHTML = bodyRows;
}

function renderPermRow(p, levels) {
    const cells = levels.map(level => {
        if (level === 'admin') {
            return `<td class="text-center"><input type="checkbox" class="form-check-input" checked disabled></td>`;
        }
        const checked = p.levels[level] ? 'checked' : '';
        return `<td class="text-center">
            <input type="checkbox" class="form-check-input" style="cursor:pointer"
                   ${checked}
                   onchange="toggleLevelPermission(this, '${level}', '${p.code}')">
        </td>`;
    }).join('');

    return `<tr class="perm-level-row"><td>${p.description}</td>${cells}</tr>`;
}

async function toggleLevelPermission(checkbox, level, code) {
    const granted = checkbox.checked;

    const res = await fetch(ROUTE_UPDATE, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': CSRF,
        },
        body: JSON.stringify({ level, code, granted }),
    });

    if (!res.ok) {
        checkbox.checked = !granted;
        alert('Failed to update permission. Please try again.');
    }
}

loadMatrix();
</script>
@endsection
```

- [ ] **Step 2: Add the sidebar link**

In `resources/views/layouts/sidebar.blade.php`, find this block (around lines 306-314):

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

Replace it with:

```blade
                    @if(strtolower((string) session('access_rights')) === 'admin')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.permissions') ? '' : 'collapsed' }}"
                               href="{{ route('admin.permissions') }}">
                                <i class="bi bi-shield-lock"></i>
                                <span>Access Rights</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.permission_levels') ? '' : 'collapsed' }}"
                               href="{{ route('admin.permission_levels') }}">
                                <i class="bi bi-diagram-3"></i>
                                <span>Permission Levels</span>
                            </a>
                        </li>
                    @endif
```

- [ ] **Step 3: Run the full controller test suite to verify everything passes**

Run: `php artisan test --filter=PermissionLevelControllerTest`
Expected: PASS (all tests, including `test_admin_can_view_index`)

- [ ] **Step 4: Commit**

```bash
git add resources/views/admin/permission_levels.blade.php resources/views/layouts/sidebar.blade.php
git commit -m "feat: add permission levels admin page and sidebar link"
```

- [ ] **Step 5: Manual verification in browser**

1. Log in as a user with `access_rights = admin`.
2. Navigate to **Permission Levels** in the sidebar (under Access Rights).
3. Confirm the table renders with one column per level (`Admin`, `DH`, `HR`, `Schedule`, `SH`, `Standard`) grouped by module, and the `Admin` column is fully checked and disabled.
4. Toggle a checkbox for a non-admin level (e.g. uncheck `leave.approve` for `hr`); confirm it persists after a page reload.
5. Toggle it back on; confirm it persists.
6. Confirm a non-admin user gets a 403 when visiting `/admin/permission-levels` directly.

---

## Final Steps

- [ ] Run the full test suite: `php artisan test`
- [ ] Use superpowers:requesting-code-review for a final review of the diff
- [ ] Use superpowers:finishing-a-development-branch to wrap up
