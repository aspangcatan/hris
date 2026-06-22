# Employee Permission Descriptions & Sidebar Gating Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Update the `employee.view` / `employee.edit` permission descriptions to reflect their real meaning ("view other employees' PDS read-only" vs. "edit other employees' PDS"), and gate the sidebar's "Employee" link on `employee.view` so view-only levels (`dh`, `standard`) can reach the page.

**Architecture:** Two small edits — `database/seeders/PermissionSeeder.php` description strings, and `resources/views/layouts/sidebar.blade.php` permission directive — plus a feature test asserting the seeder writes the new descriptions, then re-run the seeder against the dev DB.

**Tech Stack:** Laravel 8, PHP 7.4, MySQL (`hris` connection), PHPUnit 9 with `DatabaseTransactions`.

**Reference spec:** `docs/superpowers/specs/2026-06-11-employee-permission-descriptions-design.md`

---

## File Structure

- Modify: `database/seeders/PermissionSeeder.php` — update `description` for `employee.view` and `employee.edit` in the `$catalog` array
- Modify: `resources/views/layouts/sidebar.blade.php` — change `@permission('employee.edit')` to `@permission('employee.view')` around the "HR Management → Employee" link
- Create: `tests/Feature/PermissionSeederDescriptionsTest.php` — asserts the seeder writes the updated descriptions

---

### Task 1: Update permission descriptions + test

**Files:**
- Modify: `database/seeders/PermissionSeeder.php`
- Test: `tests/Feature/PermissionSeederDescriptionsTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PermissionSeederDescriptionsTest.php`:

```php
<?php

namespace Tests\Feature;

use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PermissionSeederDescriptionsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_employee_permission_descriptions_are_updated_by_seeder(): void
    {
        (new PermissionSeeder())->run();

        $viewDescription = DB::table('hris.permissions')
            ->where('code', 'employee.view')
            ->value('description');

        $editDescription = DB::table('hris.permissions')
            ->where('code', 'employee.edit')
            ->value('description');

        $this->assertSame("View other employees' PDS (read-only)", $viewDescription);
        $this->assertSame("Edit other employees' PDS", $editDescription);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=PermissionSeederDescriptionsTest`
Expected: FAIL — actual descriptions are still "View employee profiles" / "Edit employee PDS data"

- [ ] **Step 3: Update the seeder descriptions**

In `database/seeders/PermissionSeeder.php`, update these two lines in the `$catalog` array:

```php
            ['module' => 'employee',       'code' => 'employee.view',           'description' => "View other employees' PDS (read-only)"],
            ['module' => 'employee',       'code' => 'employee.edit',           'description' => "Edit other employees' PDS"],
```

(These replace the existing lines for `employee.view` and `employee.edit`, which currently read `'View employee profiles'` and `'Edit employee PDS data'` respectively.)

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=PermissionSeederDescriptionsTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/seeders/PermissionSeeder.php tests/Feature/PermissionSeederDescriptionsTest.php
git commit -m "fix: clarify employee.view/employee.edit permission descriptions"
```

---

### Task 2: Gate "Employee" sidebar link on employee.view

**Files:**
- Modify: `resources/views/layouts/sidebar.blade.php:223-233`

- [ ] **Step 1: Update the sidebar directive**

In `resources/views/layouts/sidebar.blade.php`, find:

```blade
        {{-- HR MANAGEMENT --}}
        @permission('employee.edit')
            <li class="nav-heading">HR Management</li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('employee') ? '' : 'collapsed' }}"
                   href="{{ route('employee') }}">
                    <i class="bi bi-person-badge"></i>
                    <span>Employee</span>
                </a>
            </li>
        @endpermission
```

Replace `@permission('employee.edit')` / `@endpermission` with `@permission('employee.view')` / `@endpermission`:

```blade
        {{-- HR MANAGEMENT --}}
        @permission('employee.view')
            <li class="nav-heading">HR Management</li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('employee') ? '' : 'collapsed' }}"
                   href="{{ route('employee') }}">
                    <i class="bi bi-person-badge"></i>
                    <span>Employee</span>
                </a>
            </li>
        @endpermission
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/layouts/sidebar.blade.php
git commit -m "feat: show Employee sidebar link for employee.view permission"
```

---

### Task 3: Re-seed permissions and run full suite

- [ ] **Step 1: Re-run the permission seeder against the dev database**

Run: `php artisan db:seed --class="Database\Seeders\PermissionSeeder"`
Expected: `PermissionSeeder: 24 permissions seeded, auto-granted to 60 heads.` followed by `Database seeding completed successfully.`

- [ ] **Step 2: Run the full test suite**

Run: `php artisan test --filter=Permission`
Expected: All permission-related tests PASS, including the new `PermissionSeederDescriptionsTest`.

---

## Final Steps

- [ ] Manual check: log in as a `standard`-level user, confirm "HR Management → Employee" now appears in the sidebar and the Personnel page loads.
- [ ] Manual check: as that user, open another employee's record and confirm saving any change still returns "Unauthorized employee update request" (existing `employee.edit` gate in `EmployeeController::insertUpdateEmployee`).
- [ ] Use superpowers:requesting-code-review for a final review of the diff.
- [ ] Use superpowers:finishing-a-development-branch to wrap up.
