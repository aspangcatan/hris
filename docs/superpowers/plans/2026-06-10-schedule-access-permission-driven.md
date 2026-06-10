# Schedule Access Permission-Driven Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `/schedule_access` list users by effective `schedule.manage` permission (not `user_priv.level = 'schedule'`), and require an assigned section in `hris.schedule_user_sections` before a `schedule.manage` holder sees any employees on Manage Schedule.

**Architecture:** Add a bulk permission-lookup method `PermissionService::getUserIdsWithPermission()`. Use it in `ScheduleController` to (a) populate the `/schedule_access` manager list and validate `saveScheduleManagerSections`, and (b) replace `$accessRights === 'schedule'` branches with `permission_check('schedule.manage')`. Remove the "fall back to own section" logic from `getManagedSectionIdsForCurrentUser()`.

**Tech Stack:** Laravel 8, PHPUnit (`DatabaseTransactions`), raw `DB::table()` queries (no Eloquent models in this module), Mockery `Auth::shouldReceive` for facade mocking in tests.

---

### Task 1: `PermissionService::getUserIdsWithPermission()`

**Files:**
- Modify: `app/Services/PermissionService.php`
- Test: `tests/Feature/PermissionServiceUserIdsTest.php` (new)

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/PermissionServiceUserIdsTest.php`:

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=PermissionServiceUserIdsTest`
Expected: FAIL — `Call to undefined method App\Services\PermissionService::getUserIdsWithPermission()`

- [ ] **Step 3: Implement `getUserIdsWithPermission()`**

In `app/Services/PermissionService.php`, add this method after `getUserLevel()` (currently the last method, lines 219-227), before the closing class brace:

```php

    public function getUserIdsWithPermission(string $code, bool $excludeAdminLevel = true): array
    {
        $permId = DB::table('hris.permissions')->where('code', $code)->value('id');
        if (!$permId) {
            return [];
        }

        $grantedLevels = DB::table('hris.access_level_permissions')
            ->where('permission_id', $permId)
            ->pluck('level')
            ->map(fn($level) => strtolower($level))
            ->all();

        $grantedUserIds = DB::table('hris.user_permissions')
            ->where('permission_id', $permId)
            ->where('type', 'grant')
            ->pluck('user_id')
            ->all();

        $revokedUserIds = DB::table('hris.user_permissions')
            ->where('permission_id', $permId)
            ->where('type', 'revoke')
            ->pluck('user_id')
            ->all();

        return DB::table('tdh_user.user_priv')
            ->where('syscode', 'hris')
            ->get(['user_id', 'level'])
            ->filter(function ($row) use ($grantedLevels, $grantedUserIds, $excludeAdminLevel) {
                $level = strtolower((string) $row->level);
                if ($excludeAdminLevel && $level === 'admin') {
                    return false;
                }
                return in_array($level, $grantedLevels, true) || in_array($row->user_id, $grantedUserIds, true);
            })
            ->pluck('user_id')
            ->reject(fn($id) => in_array($id, $revokedUserIds, true))
            ->unique()
            ->map(fn($id) => (int) $id)
            ->values()
            ->all();
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=PermissionServiceUserIdsTest`
Expected: PASS (6 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/PermissionService.php tests/Feature/PermissionServiceUserIdsTest.php
git commit -m "feat: add PermissionService::getUserIdsWithPermission for bulk lookups"
```

---

### Task 2: Remove "own section" fallback from `getManagedSectionIdsForCurrentUser()`

**Files:**
- Modify: `app/Http/Controllers/ScheduleController.php:19-34`
- Test: `tests/Feature/ScheduleAccessTest.php` (new)

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ScheduleAccessTest.php` with shared fixtures and the first test:

```php
<?php

namespace Tests\Feature;

use App\Http\Controllers\ScheduleController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScheduleAccessTest extends TestCase
{
    use DatabaseTransactions;

    private int $managerUserId;
    private int $employeeUserId;
    private int $divisionId;
    private int $sectionAId;
    private int $sectionBId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->managerUserId = DB::table('tdh_user.users')->insertGetId([
            'fname' => 'Manager', 'lname' => 'Test', 'mname' => '',
            'username' => 'sched_mgr_' . uniqid(), 'password' => bcrypt('password'),
            'designation' => 0, 'division' => 0, 'section' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->employeeUserId = DB::table('tdh_user.users')->insertGetId([
            'fname' => 'Employee', 'lname' => 'Test', 'mname' => '',
            'username' => 'sched_emp_' . uniqid(), 'password' => bcrypt('password'),
            'designation' => 0, 'division' => 0, 'section' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('tdh_user.user_priv')->updateOrInsert(
            ['user_id' => $this->managerUserId],
            ['user_id' => $this->managerUserId, 'syscode' => 'hris', 'level' => 'schedule']
        );
        DB::table('tdh_user.user_priv')->updateOrInsert(
            ['user_id' => $this->employeeUserId],
            ['user_id' => $this->employeeUserId, 'syscode' => 'hris', 'level' => 'standard']
        );

        $this->divisionId = DB::table('tdh_user.division')->insertGetId([
            'description' => 'ScheduleTestDivision', 'head' => $this->managerUserId, 'code' => 'SCHDIV',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->sectionAId = DB::table('tdh_user.section')->insertGetId([
            'description' => 'ScheduleTestSectionA', 'division' => $this->divisionId, 'head' => $this->managerUserId, 'code' => 'SCHSECA',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->sectionBId = DB::table('tdh_user.section')->insertGetId([
            'description' => 'ScheduleTestSectionB', 'division' => $this->divisionId, 'head' => $this->managerUserId, 'code' => 'SCHSECB',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('tdh_user.users')->where('id', $this->managerUserId)->update([
            'division' => $this->divisionId, 'section' => $this->sectionAId,
        ]);
        DB::table('tdh_user.users')->where('id', $this->employeeUserId)->update([
            'division' => $this->divisionId, 'section' => $this->sectionAId,
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('hris.schedule_user_sections')->whereIn('user_id', [$this->managerUserId, $this->employeeUserId])->delete();
        DB::table('hris.user_permissions')->whereIn('user_id', [$this->managerUserId, $this->employeeUserId])->delete();
        DB::table('tdh_user.section')->whereIn('id', [$this->sectionAId, $this->sectionBId])->delete();
        DB::table('tdh_user.division')->where('id', $this->divisionId)->delete();
        DB::table('tdh_user.user_priv')->whereIn('user_id', [$this->managerUserId, $this->employeeUserId])->delete();
        DB::table('tdh_user.users')->whereIn('id', [$this->managerUserId, $this->employeeUserId])->delete();
        parent::tearDown();
    }

    private function actAsManager(): void
    {
        Auth::shouldReceive('id')->andReturn($this->managerUserId);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'schedule']);
    }

    private function callPrivate(object $object, string $method, array $args = [])
    {
        $ref = new \ReflectionMethod($object, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($object, $args);
    }

    /** @test */
    public function manager_with_no_assigned_sections_has_no_managed_sections(): void
    {
        $this->actAsManager();
        $controller = app(ScheduleController::class);

        // Manager's own section is sectionA (set in setUp). The old fallback
        // would have returned [sectionAId] here; the new behavior must not.
        $this->assertSame([], $this->callPrivate($controller, 'getManagedSectionIdsForCurrentUser'));
    }

    /** @test */
    public function manager_with_assigned_sections_returns_only_those_sections(): void
    {
        $this->actAsManager();

        DB::table('hris.schedule_user_sections')->insert([
            'user_id' => $this->managerUserId,
            'section_id' => $this->sectionBId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $controller = app(ScheduleController::class);

        // Only sectionB (the assigned one) should come back — not sectionA
        // (the manager's own section), since the fallback is gone.
        $this->assertSame([$this->sectionBId], $this->callPrivate($controller, 'getManagedSectionIdsForCurrentUser'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ScheduleAccessTest::manager_with_no_assigned_sections_has_no_managed_sections`
Expected: FAIL — `Failed asserting that an array contains ... [sectionAId]` (because of the existing own-section fallback)

- [ ] **Step 3: Remove the fallback**

In `app/Http/Controllers/ScheduleController.php`, replace lines 19-34:

```php
    private function getManagedSectionIdsForCurrentUser(bool $includeOwnSectionFallback = true): array
    {
        $managedSectionIds = DB::table('hris.schedule_user_sections')
            ->where('user_id', Auth::id())
            ->pluck('section_id')
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();

        if (empty($managedSectionIds) && $includeOwnSectionFallback && Auth::check() && Auth::user()->section) {
            $managedSectionIds = [(int) Auth::user()->section];
        }

        return $managedSectionIds;
    }
```

with:

```php
    private function getManagedSectionIdsForCurrentUser(): array
    {
        return DB::table('hris.schedule_user_sections')
            ->where('user_id', Auth::id())
            ->pluck('section_id')
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=ScheduleAccessTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ScheduleController.php tests/Feature/ScheduleAccessTest.php
git commit -m "fix: remove own-section fallback from schedule manager section lookup"
```

---

### Task 3: Replace `$accessRights === 'schedule'` with `permission_check('schedule.manage')`

**Files:**
- Modify: `app/Http/Controllers/ScheduleController.php` (4 spots: `canManageEmployee`, `getSchedules`, `printSchedule` x2, `getManagedSections`)
- Test: `tests/Feature/ScheduleAccessTest.php`

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/ScheduleAccessTest.php` (inside the class, after the Task 2 tests):

```php

    /** @test */
    public function cannot_manage_employee_when_no_sections_assigned(): void
    {
        $this->actAsManager();
        $controller = app(ScheduleController::class);

        $this->assertFalse($this->callPrivate($controller, 'canManageEmployee', [$this->employeeUserId]));
    }

    /** @test */
    public function can_manage_employee_in_assigned_section(): void
    {
        $this->actAsManager();

        DB::table('hris.schedule_user_sections')->insert([
            'user_id' => $this->managerUserId,
            'section_id' => $this->sectionAId, // employee is in sectionA
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $controller = app(ScheduleController::class);

        $this->assertTrue($this->callPrivate($controller, 'canManageEmployee', [$this->employeeUserId]));
    }

    /** @test */
    public function cannot_manage_employee_outside_assigned_sections(): void
    {
        $this->actAsManager();

        DB::table('hris.schedule_user_sections')->insert([
            'user_id' => $this->managerUserId,
            'section_id' => $this->sectionBId, // employee is in sectionA, not sectionB
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $controller = app(ScheduleController::class);

        $this->assertFalse($this->callPrivate($controller, 'canManageEmployee', [$this->employeeUserId]));
    }

    /** @test */
    public function get_schedules_returns_empty_when_manager_has_no_assigned_sections(): void
    {
        $this->actAsManager();
        $controller = app(ScheduleController::class);

        $response = $controller->getSchedules(new Request());
        $data = $response->getData(true);

        $this->assertSame([], $data['schedules']);
        $this->assertArrayHasKey('days', $data);
    }

    /** @test */
    public function get_managed_sections_returns_empty_when_manager_has_no_assigned_sections(): void
    {
        $this->actAsManager();
        $controller = app(ScheduleController::class);

        $response = $controller->getManagedSections();
        $data = $response->getData(true);

        $this->assertSame([], $data['sections']);
    }

    /** @test */
    public function get_managed_sections_returns_assigned_sections_for_manager(): void
    {
        $this->actAsManager();

        DB::table('hris.schedule_user_sections')->insert([
            'user_id' => $this->managerUserId,
            'section_id' => $this->sectionAId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $controller = app(ScheduleController::class);
        $response = $controller->getManagedSections();
        $data = $response->getData(true);

        $sectionIds = collect($data['sections'])->pluck('id')->map(fn($id) => (int) $id)->all();
        $this->assertSame([$this->sectionAId], $sectionIds);
    }

    /** @test */
    public function get_managed_sections_returns_unfiltered_list_for_admin(): void
    {
        Auth::shouldReceive('id')->andReturn($this->managerUserId);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'admin']);

        $controller = app(ScheduleController::class);
        $response = $controller->getManagedSections();
        $data = $response->getData(true);

        $sectionIds = collect($data['sections'])->pluck('id')->map(fn($id) => (int) $id)->all();
        $this->assertContains($this->sectionAId, $sectionIds);
        $this->assertContains($this->sectionBId, $sectionIds);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ScheduleAccessTest`
Expected: FAIL on the new tests — `canManageEmployee`/`getSchedules`/`getManagedSections` still branch on `$accessRights === 'schedule'`, which is `'schedule'` in these tests, so behavior may partially pass; the key failures will show once Step 3 below is verified against `cannot_manage_employee_when_no_sections_assigned` (currently the old code already returns `false` for empty sections, so this one may already pass — but `can_manage_employee_in_assigned_section` and the `getManagedSections`/`getSchedules` empty-result tests confirm current behavior before the refactor). Note any unexpected passes and proceed — Step 3 must not break them.

- [ ] **Step 3: Add `use App\Services\PermissionService;` import**

In `app/Http/Controllers/ScheduleController.php`, replace the import block at lines 1-10:

```php
<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Barryvdh\DomPDF\Facade\Pdf;
```

with:

```php
<?php

namespace App\Http\Controllers;

use App\Services\PermissionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Barryvdh\DomPDF\Facade\Pdf;
```

- [ ] **Step 4: Update `canManageEmployee()`**

Replace (current lines 36-55):

```php
    private function canManageEmployee(int $employeeId): bool
    {
        if ($this->getCurrentAccessRights() === 'admin') {
            return true;
        }

        if ($this->getCurrentAccessRights() !== 'schedule') {
            return false;
        }

        $managedSections = $this->getManagedSectionIdsForCurrentUser();
        if (empty($managedSections)) {
            return false;
        }

        return DB::table('tdh_user.users')
            ->where('id', $employeeId)
            ->whereIn('section', $managedSections)
            ->exists();
    }
```

with:

```php
    private function canManageEmployee(int $employeeId): bool
    {
        if ($this->getCurrentAccessRights() === 'admin') {
            return true;
        }

        if (!permission_check('schedule.manage')) {
            return false;
        }

        $managedSections = $this->getManagedSectionIdsForCurrentUser();
        if (empty($managedSections)) {
            return false;
        }

        return DB::table('tdh_user.users')
            ->where('id', $employeeId)
            ->whereIn('section', $managedSections)
            ->exists();
    }
```

- [ ] **Step 5: Update `getSchedules()`**

Replace (current lines 248-275):

```php
            if ($accessRights === 'admin') {
                if (!is_null($requestedSection)) {
                    $employees->where('u.section', $requestedSection);
                } elseif (!is_null($requestedDivision)) {
                    $employees->where('u.division', $requestedDivision);
                } else {
                    $employees->where('u.section', Auth::user()->section);
                }
            } elseif ($accessRights === 'schedule') {
                $managedSections = $this->getManagedSectionIdsForCurrentUser();
                if (empty($managedSections)) {
                    return response()->json(['schedules' => [], 'days' => $daysInMonth]);
                }

                if (!is_null($requestedSection)) {
                    if (!in_array($requestedSection, $managedSections, true)) {
                        return response()->json(['schedules' => [], 'days' => $daysInMonth]);
                    }
                    $employees->where('u.section', $requestedSection);
                } else {
                    $employees->whereIn('u.section', $managedSections);
                    if (!is_null($requestedDivision)) {
                        $employees->where('u.division', $requestedDivision);
                    }
                }
            } else {
                $employees->where('u.section', Auth::user()->section);
            }
```

with:

```php
            if ($accessRights === 'admin') {
                if (!is_null($requestedSection)) {
                    $employees->where('u.section', $requestedSection);
                } elseif (!is_null($requestedDivision)) {
                    $employees->where('u.division', $requestedDivision);
                } else {
                    $employees->where('u.section', Auth::user()->section);
                }
            } elseif (permission_check('schedule.manage')) {
                $managedSections = $this->getManagedSectionIdsForCurrentUser();
                if (empty($managedSections)) {
                    return response()->json(['schedules' => [], 'days' => $daysInMonth]);
                }

                if (!is_null($requestedSection)) {
                    if (!in_array($requestedSection, $managedSections, true)) {
                        return response()->json(['schedules' => [], 'days' => $daysInMonth]);
                    }
                    $employees->where('u.section', $requestedSection);
                } else {
                    $employees->whereIn('u.section', $managedSections);
                    if (!is_null($requestedDivision)) {
                        $employees->where('u.division', $requestedDivision);
                    }
                }
            } else {
                $employees->where('u.section', Auth::user()->section);
            }
```

- [ ] **Step 6: Update `printSchedule()` employee-query branch**

Replace (current lines 365-392):

```php
            if ($accessRights === 'admin') {
                if (!is_null($requestedSection)) {
                    $employeesQuery->where('u.section', $requestedSection);
                } elseif (!is_null($requestedDivision)) {
                    $employeesQuery->where('u.division', $requestedDivision);
                } else {
                    $employeesQuery->where('u.section', Auth::user()->section);
                }
            } elseif ($accessRights === 'schedule') {
                $managedSections = $this->getManagedSectionIdsForCurrentUser();
                if (empty($managedSections)) {
                    return response()->json(['message' => 'No managed sections assigned.'], 422);
                }

                if (!is_null($requestedSection)) {
                    if (!in_array($requestedSection, $managedSections, true)) {
                        return response()->json(['message' => 'You are not allowed to print this section.'], 403);
                    }
                    $employeesQuery->where('u.section', $requestedSection);
                } else {
                    $employeesQuery->whereIn('u.section', $managedSections);
                    if (!is_null($requestedDivision)) {
                        $employeesQuery->where('u.division', $requestedDivision);
                    }
                }
            } else {
                $employeesQuery->where('u.section', Auth::user()->section);
            }
```

with:

```php
            if ($accessRights === 'admin') {
                if (!is_null($requestedSection)) {
                    $employeesQuery->where('u.section', $requestedSection);
                } elseif (!is_null($requestedDivision)) {
                    $employeesQuery->where('u.division', $requestedDivision);
                } else {
                    $employeesQuery->where('u.section', Auth::user()->section);
                }
            } elseif (permission_check('schedule.manage')) {
                $managedSections = $this->getManagedSectionIdsForCurrentUser();
                if (empty($managedSections)) {
                    return response()->json(['message' => 'No managed sections assigned.'], 422);
                }

                if (!is_null($requestedSection)) {
                    if (!in_array($requestedSection, $managedSections, true)) {
                        return response()->json(['message' => 'You are not allowed to print this section.'], 403);
                    }
                    $employeesQuery->where('u.section', $requestedSection);
                } else {
                    $employeesQuery->whereIn('u.section', $managedSections);
                    if (!is_null($requestedDivision)) {
                        $employeesQuery->where('u.division', $requestedDivision);
                    }
                }
            } else {
                $employeesQuery->where('u.section', Auth::user()->section);
            }
```

- [ ] **Step 7: Update `printSchedule()` label branch**

Replace (current lines 452-470):

```php
            } else {
                if ($accessRights === 'schedule') {
                    $managedSectionLabel = $this->getSectionLabelByIds($managedSections, $requestedDivision);
                    $managedDivisionLabel = $this->getDivisionLabelBySectionIds($managedSections);

                    $divisionLabel = $managedDivisionLabel;
                    $sectionLabel = $managedSectionLabel;
                    $divisionId = Auth::user()->division ?? null;
                } else {
                    $sectionInfo = DB::table('tdh_user.section as s')
                        ->join('tdh_user.division as d', 's.division', '=', 'd.id')
                        ->where('s.id', Auth::user()->section)
                        ->select('s.description as section', 'd.description as division', 'd.id as division_id')
                        ->first();
                    $divisionLabel = $sectionInfo->division ?? '';
                    $sectionLabel = $sectionInfo->section ?? '';
                    $divisionId = $sectionInfo->division_id ?? (Auth::user()->division ?? null);
                }
            }
```

with:

```php
            } else {
                if ($accessRights !== 'admin' && permission_check('schedule.manage')) {
                    $managedSectionLabel = $this->getSectionLabelByIds($managedSections, $requestedDivision);
                    $managedDivisionLabel = $this->getDivisionLabelBySectionIds($managedSections);

                    $divisionLabel = $managedDivisionLabel;
                    $sectionLabel = $managedSectionLabel;
                    $divisionId = Auth::user()->division ?? null;
                } else {
                    $sectionInfo = DB::table('tdh_user.section as s')
                        ->join('tdh_user.division as d', 's.division', '=', 'd.id')
                        ->where('s.id', Auth::user()->section)
                        ->select('s.description as section', 'd.description as division', 'd.id as division_id')
                        ->first();
                    $divisionLabel = $sectionInfo->division ?? '';
                    $sectionLabel = $sectionInfo->section ?? '';
                    $divisionId = $sectionInfo->division_id ?? (Auth::user()->division ?? null);
                }
            }
```

(`$accessRights === 'admin'` is excluded here because admin always reaches this branch with `$managedSections` undefined — admin's own-section label must come from the `else` arm, same as before.)

- [ ] **Step 8: Update `getManagedSections()`**

Replace (current lines 800-808):

```php
            if ($accessRights === 'schedule') {
                $managedSections = $this->getManagedSectionIdsForCurrentUser();
                if (empty($managedSections)) {
                    return response()->json(['sections' => []]);
                }
                $sectionQuery->whereIn('s.id', $managedSections);
            } elseif ($accessRights !== 'admin') {
                $sectionQuery->where('s.id', Auth::user()->section);
            }
```

with:

```php
            if ($accessRights !== 'admin' && permission_check('schedule.manage')) {
                $managedSections = $this->getManagedSectionIdsForCurrentUser();
                if (empty($managedSections)) {
                    return response()->json(['sections' => []]);
                }
                $sectionQuery->whereIn('s.id', $managedSections);
            } elseif ($accessRights !== 'admin') {
                $sectionQuery->where('s.id', Auth::user()->section);
            }
```

- [ ] **Step 9: Run tests to verify they pass**

Run: `php artisan test --filter=ScheduleAccessTest`
Expected: PASS (all tests so far)

- [ ] **Step 10: Run the full test suite for regressions**

Run: `php artisan test`
Expected: PASS (no new failures vs. baseline — `getCurrentAccessRights`/`canManageEmployee` callers like `setEmployeeSchedule`/`saveAdvanceSchedule` are exercised indirectly only through `canManageEmployee`, already covered above)

- [ ] **Step 11: Commit**

```bash
git add app/Http/Controllers/ScheduleController.php tests/Feature/ScheduleAccessTest.php
git commit -m "feat: gate schedule management on schedule.manage permission instead of level"
```

---

### Task 4: Permission-driven `/schedule_access` manager list

**Files:**
- Modify: `app/Http/Controllers/ScheduleController.php` (`getScheduleManagerAssignments`, lines 816-874)
- Test: `tests/Feature/ScheduleAccessTest.php`

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/ScheduleAccessTest.php`:

```php

    /** @test */
    public function manager_assignments_lists_users_with_schedule_manage_permission(): void
    {
        Auth::shouldReceive('id')->andReturn($this->managerUserId);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'admin']);

        $controller = app(ScheduleController::class);
        $response = $controller->getScheduleManagerAssignments();
        $data = $response->getData(true);

        $managerIds = collect($data['managers'])->pluck('id')->map(fn($id) => (int) $id)->all();

        $this->assertContains($this->managerUserId, $managerIds);
        $this->assertNotContains($this->employeeUserId, $managerIds);
    }

    /** @test */
    public function manager_assignments_includes_per_user_granted_employee(): void
    {
        $permId = DB::table('hris.permissions')->where('code', 'schedule.manage')->value('id');
        DB::table('hris.user_permissions')->insert([
            'user_id' => $this->employeeUserId,
            'permission_id' => $permId,
            'type' => 'grant',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Auth::shouldReceive('id')->andReturn($this->managerUserId);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'admin']);

        $controller = app(ScheduleController::class);
        $response = $controller->getScheduleManagerAssignments();
        $data = $response->getData(true);

        $managerIds = collect($data['managers'])->pluck('id')->map(fn($id) => (int) $id)->all();
        $this->assertContains($this->employeeUserId, $managerIds);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ScheduleAccessTest::manager_assignments_includes_per_user_granted_employee`
Expected: FAIL — the current query only matches `user_priv.level = 'schedule'`, so the per-user-granted `employeeUserId` (level `standard`) is absent from `managers`.

- [ ] **Step 3: Replace the manager query**

In `app/Http/Controllers/ScheduleController.php`, replace the `$managers` query inside `getScheduleManagerAssignments()` (current lines 835-852):

```php
            $managers = DB::table('tdh_user.user_priv as up')
                ->join('tdh_user.users as u', 'up.user_id', '=', 'u.id')
                ->leftJoin('tdh_user.designation as de', 'u.designation', '=', 'de.id')
                ->where('up.syscode', 'hris')
                ->where('up.level', 'schedule')
                ->selectRaw("
                    u.id,
                    CONCAT(
                        u.fname,
                        CASE WHEN u.mname IS NOT NULL AND u.mname != '' THEN CONCAT(' ', u.mname) ELSE '' END,
                        ' ', u.lname,
                        CASE WHEN u.suffix IS NOT NULL AND u.suffix != '' THEN CONCAT(' ', u.suffix) ELSE '' END
                    ) AS name,
                    COALESCE(de.description, 'No Designation') AS designation
                ")
                ->orderBy('u.lname')
                ->orderBy('u.fname')
                ->get();
```

with:

```php
            $managerIds = app(PermissionService::class)->getUserIdsWithPermission('schedule.manage');

            $managers = DB::table('tdh_user.users as u')
                ->leftJoin('tdh_user.designation as de', 'u.designation', '=', 'de.id')
                ->whereIn('u.id', $managerIds)
                ->selectRaw("
                    u.id,
                    CONCAT(
                        u.fname,
                        CASE WHEN u.mname IS NOT NULL AND u.mname != '' THEN CONCAT(' ', u.mname) ELSE '' END,
                        ' ', u.lname,
                        CASE WHEN u.suffix IS NOT NULL AND u.suffix != '' THEN CONCAT(' ', u.suffix) ELSE '' END
                    ) AS name,
                    COALESCE(de.description, 'No Designation') AS designation
                ")
                ->orderBy('u.lname')
                ->orderBy('u.fname')
                ->get();
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=ScheduleAccessTest`
Expected: PASS (all tests so far)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ScheduleController.php tests/Feature/ScheduleAccessTest.php
git commit -m "feat: list schedule_access managers by schedule.manage permission"
```

---

### Task 5: Permission-driven validation in `saveScheduleManagerSections`

**Files:**
- Modify: `app/Http/Controllers/ScheduleController.php` (`saveScheduleManagerSections`, lines 876-898)
- Test: `tests/Feature/ScheduleAccessTest.php`

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/ScheduleAccessTest.php`:

```php

    /** @test */
    public function save_manager_sections_rejects_user_without_schedule_manage_permission(): void
    {
        Auth::shouldReceive('id')->andReturn($this->managerUserId);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'admin']);

        $controller = app(ScheduleController::class);
        $request = new Request([], [
            'manager_id' => $this->employeeUserId,
            'section_ids' => [$this->sectionAId],
        ]);

        $response = $controller->saveScheduleManagerSections($request);

        $this->assertEquals(422, $response->getStatusCode());
    }

    /** @test */
    public function save_manager_sections_accepts_schedule_manage_holder_and_persists(): void
    {
        Auth::shouldReceive('id')->andReturn($this->managerUserId);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'admin']);

        $controller = app(ScheduleController::class);
        $request = new Request([], [
            'manager_id' => $this->managerUserId,
            'section_ids' => [$this->sectionAId, $this->sectionBId],
        ]);

        $response = $controller->saveScheduleManagerSections($request);

        $this->assertEquals(200, $response->getStatusCode());

        $saved = DB::table('hris.schedule_user_sections')
            ->where('user_id', $this->managerUserId)
            ->pluck('section_id')
            ->map(fn($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $this->assertEquals([$this->sectionAId, $this->sectionBId], $saved);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ScheduleAccessTest::save_manager_sections_rejects_user_without_schedule_manage_permission`
Expected: PASS already (the `employeeUserId` has `level = 'standard'`, not `'schedule'`, so the old check also rejects it) — note this and proceed; the refactor must keep this passing while Task 4/5's other tests confirm the new lookup is actually used.

Run: `php artisan test --filter=ScheduleAccessTest::save_manager_sections_accepts_schedule_manage_holder_and_persists`
Expected: PASS already (the `managerUserId` has `level = 'schedule'`) — same note as above.

- [ ] **Step 3: Replace the validation query**

In `app/Http/Controllers/ScheduleController.php`, replace (current lines 890-898):

```php
            $isScheduleManager = DB::table('tdh_user.user_priv')
                ->where('user_id', $userId)
                ->where('syscode', 'hris')
                ->where('level', 'schedule')
                ->exists();

            if (!$isScheduleManager) {
                return response()->json(['message' => 'Selected user is not a schedule manager.'], 422);
            }
```

with:

```php
            $isScheduleManager = in_array(
                $userId,
                app(PermissionService::class)->getUserIdsWithPermission('schedule.manage'),
                true
            );

            if (!$isScheduleManager) {
                return response()->json(['message' => 'Selected user is not a schedule manager.'], 422);
            }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=ScheduleAccessTest`
Expected: PASS (all tests)

- [ ] **Step 5: Run the full test suite**

Run: `php artisan test`
Expected: PASS (no regressions)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ScheduleController.php tests/Feature/ScheduleAccessTest.php
git commit -m "feat: validate schedule manager assignment via schedule.manage permission"
```

---

## Spec Coverage Check

- Spec item 1 (`/schedule_access` lists `schedule.manage` holders, excludes admin, no more `level = 'schedule'`) → Tasks 1, 4.
- Spec item 2 (no employees shown until a section is assigned) → Tasks 2, 3 (`getSchedules`, `printSchedule`, `getManagedSections`, `canManageEmployee`).
- `saveScheduleManagerSections` validation consistency → Task 5.
- All 4 call sites of `getManagedSectionIdsForCurrentUser()` confirmed to use the no-arg signature → Task 2.
