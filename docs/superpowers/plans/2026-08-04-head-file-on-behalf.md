# Section/Division Head File-On-Behalf Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let Section Heads and Division Heads file Pass Slips and Hospital Order filings on behalf of employees in their managed section/division, auto-approved on submission.

**Architecture:** Add a nullable `filed_by` column to `pass_slips` and `hospital_order_employees` (NULL = self-filed). Add a new `OrgHierarchyService` that resolves "which employee ids does this head manage" (division-first, else section, else none) — used both to validate on-behalf targets server-side and to power a new employee-picker endpoint. Two new permissions (`passslip.file_on_behalf`, `hospital_order.file_on_behalf`) gate the capability and are auto-granted to heads alongside the existing approval permissions. `PassSlipService::store()` and a new `HospitalOrderServices::fileForEmployee()` write the on-behalf record directly as `approved` (no pending-review step). Employee-facing lists show a "Filed by {Head}" badge; only the filing head or an admin can edit/delete an on-behalf record.

**Tech Stack:** Laravel 8, MySQL (cross-database queries between `hris` and `tdh_user`), Blade + jQuery + Select2, PHPUnit with `DatabaseTransactions` (several touched tables are MyISAM, so cleanup is explicit in `setUp`/`tearDown`, not relying on rollback).

---

### Task 1: Migration — add `filed_by` columns

**Files:**
- Create: `database/migrations/2026_08_04_120000_add_filed_by_to_pass_slips_and_hospital_order_employees_table.php`

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('pass_slips', function (Blueprint $table) {
            $table->integer('filed_by')->nullable()->after('user_id');
        });

        Schema::table('hospital_order_employees', function (Blueprint $table) {
            $table->integer('filed_by')->nullable()->after('userid');
        });
    }

    public function down()
    {
        Schema::table('pass_slips', function (Blueprint $table) {
            $table->dropColumn('filed_by');
        });

        Schema::table('hospital_order_employees', function (Blueprint $table) {
            $table->dropColumn('filed_by');
        });
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `php artisan migrate --path=database/migrations/2026_08_04_120000_add_filed_by_to_pass_slips_and_hospital_order_employees_table.php`
Expected: output ends with `Migrated:  2026_08_04_120000_add_filed_by_to_pass_slips_and_hospital_order_employees_table` and no errors.

- [ ] **Step 3: Verify the columns exist**

Run: `php artisan tinker --execute="echo collect(DB::select('SHOW COLUMNS FROM pass_slips'))->pluck('Field')->implode(','); echo PHP_EOL; echo collect(DB::select('SHOW COLUMNS FROM hospital_order_employees'))->pluck('Field')->implode(',');"`
Expected: both output lines contain `filed_by`.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_08_04_120000_add_filed_by_to_pass_slips_and_hospital_order_employees_table.php
git commit -m "feat: add filed_by column to pass_slips and hospital_order_employees"
```

---

### Task 2: `OrgHierarchyService` — resolve managed employees

**Files:**
- Create: `app/Services/OrgHierarchyService.php`
- Test: `tests/Unit/OrgHierarchyServiceTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Unit;

use App\Services\OrgHierarchyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrgHierarchyServiceTest extends TestCase
{
    use DatabaseTransactions;

    private int $shUserId = 9001001;
    private int $dhUserId = 9001002;
    private int $plainEmployeeId = 9001003;
    private int $sectionEmployeeId = 9001004;
    private int $otherSectionEmployeeId = 9001005;
    private int $sectionId = 8001001;
    private int $otherSectionId = 8001002;
    private int $divisionId = 9101001;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tdh_user.users')->insertOrIgnore([
            ['id' => $this->shUserId, 'fname' => 'SH', 'lname' => 'OrgTest', 'section' => $this->sectionId, 'division' => 0, 'username' => 'sh_org_test', 'password' => 'x'],
            ['id' => $this->dhUserId, 'fname' => 'DH', 'lname' => 'OrgTest', 'section' => 0, 'division' => $this->divisionId, 'username' => 'dh_org_test', 'password' => 'x'],
            ['id' => $this->plainEmployeeId, 'fname' => 'Plain', 'lname' => 'OrgTest', 'section' => 0, 'division' => 0, 'username' => 'plain_org_test', 'password' => 'x'],
            ['id' => $this->sectionEmployeeId, 'fname' => 'Alpha', 'lname' => 'OrgTest', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'sec_org_test', 'password' => 'x'],
            ['id' => $this->otherSectionEmployeeId, 'fname' => 'Beta', 'lname' => 'OrgTest', 'section' => $this->otherSectionId, 'division' => 0, 'username' => 'osec_org_test', 'password' => 'x'],
        ]);

        DB::table('tdh_user.division')->insertOrIgnore([
            ['id' => $this->divisionId, 'description' => 'OrgTestDivision', 'head' => $this->dhUserId, 'code' => 'OTD'],
        ]);

        DB::table('tdh_user.section')->insertOrIgnore([
            ['id' => $this->sectionId, 'description' => 'OrgTestSection', 'head' => $this->shUserId, 'division' => 0, 'code' => 'OTS'],
            ['id' => $this->otherSectionId, 'description' => 'OrgTestOtherSection', 'head' => 0, 'division' => 0, 'code' => 'OTO'],
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('tdh_user.section')->whereIn('id', [$this->sectionId, $this->otherSectionId])->delete();
        DB::table('tdh_user.division')->where('id', $this->divisionId)->delete();
        DB::table('tdh_user.users')->whereIn('id', [
            $this->shUserId, $this->dhUserId, $this->plainEmployeeId, $this->sectionEmployeeId, $this->otherSectionEmployeeId,
        ])->delete();
        parent::tearDown();
    }

    /** @test */
    public function section_head_manages_only_employees_in_their_section()
    {
        $service = new OrgHierarchyService();
        $ids = $service->getManagedEmployeeIds($this->shUserId);

        $this->assertContains($this->sectionEmployeeId, $ids);
        $this->assertNotContains($this->otherSectionEmployeeId, $ids);
        $this->assertNotContains($this->shUserId, $ids);
    }

    /** @test */
    public function division_head_manages_every_employee_in_their_division_regardless_of_section()
    {
        $service = new OrgHierarchyService();
        $ids = $service->getManagedEmployeeIds($this->dhUserId);

        $this->assertContains($this->sectionEmployeeId, $ids);
        $this->assertNotContains($this->otherSectionEmployeeId, $ids);
    }

    /** @test */
    public function plain_employee_manages_nobody()
    {
        $service = new OrgHierarchyService();
        $ids = $service->getManagedEmployeeIds($this->plainEmployeeId);

        $this->assertSame([], $ids);
    }

    /** @test */
    public function get_managed_employees_returns_formatted_full_names()
    {
        $service = new OrgHierarchyService();
        $employees = $service->getManagedEmployees($this->shUserId);

        $match = $employees->firstWhere('id', $this->sectionEmployeeId);
        $this->assertNotNull($match);
        $this->assertEquals('OrgTest, Alpha', $match->full_name);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Unit/OrgHierarchyServiceTest.php`
Expected: FAIL — `Class "App\Services\OrgHierarchyService" not found`.

- [ ] **Step 3: Write the service**

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class OrgHierarchyService
{
    public function getManagedEmployeeIds(int $headUserId): array
    {
        $managedDivisionIds = DB::table('tdh_user.division')
            ->where('head', $headUserId)
            ->pluck('id')
            ->all();

        if (!empty($managedDivisionIds)) {
            return DB::table('tdh_user.users')
                ->whereIn('division', $managedDivisionIds)
                ->where('id', '!=', $headUserId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $managedSectionIds = DB::table('tdh_user.section')
            ->where('head', $headUserId)
            ->pluck('id')
            ->all();

        if (!empty($managedSectionIds)) {
            return DB::table('tdh_user.users')
                ->whereIn('section', $managedSectionIds)
                ->where('id', '!=', $headUserId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return [];
    }

    public function getManagedEmployees(int $headUserId)
    {
        $managedIds = $this->getManagedEmployeeIds($headUserId);

        if (empty($managedIds)) {
            return collect();
        }

        return DB::table('tdh_user.users')
            ->whereIn('id', $managedIds)
            ->orderBy('lname')
            ->orderBy('fname')
            ->get([
                'id',
                'fname',
                'lname',
                DB::raw("CONCAT(COALESCE(lname, ''), ', ', COALESCE(fname, '')) as full_name"),
            ]);
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Unit/OrgHierarchyServiceTest.php`
Expected: `Tests: 4 passed`.

- [ ] **Step 5: Commit**

```bash
git add app/Services/OrgHierarchyService.php tests/Unit/OrgHierarchyServiceTest.php
git commit -m "feat: add OrgHierarchyService to resolve a head's managed employees"
```

---

### Task 3: Permissions — `*.file_on_behalf` catalog entries and auto-grant

**Files:**
- Modify: `database/seeders/PermissionSeeder.php`
- Modify: `app/Services/PermissionService.php:173-217` (`syncHeadshipGrants`)
- Test: `tests/Feature/HeadFileOnBehalfPermissionsTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Services\PermissionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HeadFileOnBehalfPermissionsTest extends TestCase
{
    use DatabaseTransactions;

    private int $shUserId = 9101001;
    private int $dhUserId = 9101002;
    private int $sectionId = 8101001;
    private int $divisionId = 9201001;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tdh_user.users')->insertOrIgnore([
            ['id' => $this->shUserId, 'fname' => 'SH', 'lname' => 'Behalf', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'sh_behalf_test', 'password' => 'x'],
            ['id' => $this->dhUserId, 'fname' => 'DH', 'lname' => 'Behalf', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'dh_behalf_test', 'password' => 'x'],
        ]);

        DB::table('tdh_user.division')->insertOrIgnore([
            ['id' => $this->divisionId, 'description' => 'BehalfTestDivision', 'head' => $this->dhUserId, 'code' => 'BTD'],
        ]);

        DB::table('tdh_user.section')->insertOrIgnore([
            ['id' => $this->sectionId, 'description' => 'BehalfTestSection', 'head' => $this->shUserId, 'division' => $this->divisionId, 'code' => 'BTS'],
        ]);

        (new PermissionSeeder())->run();
    }

    protected function tearDown(): void
    {
        DB::table('hris.user_permissions')->whereIn('user_id', [$this->shUserId, $this->dhUserId])->delete();
        DB::table('hris.permission_audit_logs')->whereIn('target_user_id', [$this->shUserId, $this->dhUserId])->delete();
        DB::table('tdh_user.section')->where('id', $this->sectionId)->delete();
        DB::table('tdh_user.division')->where('id', $this->divisionId)->delete();
        DB::table('tdh_user.users')->whereIn('id', [$this->shUserId, $this->dhUserId])->delete();
        parent::tearDown();
    }

    /** @test */
    public function catalog_includes_the_new_file_on_behalf_permissions()
    {
        $codes = DB::table('hris.permissions')
            ->whereIn('code', ['passslip.file_on_behalf', 'hospital_order.file_on_behalf'])
            ->pluck('code')
            ->all();

        $this->assertContains('passslip.file_on_behalf', $codes);
        $this->assertContains('hospital_order.file_on_behalf', $codes);
    }

    /** @test */
    public function sync_headship_grants_gives_section_head_the_file_on_behalf_permissions()
    {
        $service = new PermissionService();
        $service->syncHeadshipGrants($this->shUserId, true, false);

        $this->assertTrue($service->can($this->shUserId, 'passslip.file_on_behalf'));
        $this->assertTrue($service->can($this->shUserId, 'hospital_order.file_on_behalf'));
    }

    /** @test */
    public function sync_headship_grants_gives_division_head_the_file_on_behalf_permissions()
    {
        $service = new PermissionService();
        $service->syncHeadshipGrants($this->dhUserId, false, true);

        $this->assertTrue($service->can($this->dhUserId, 'passslip.file_on_behalf'));
        $this->assertTrue($service->can($this->dhUserId, 'hospital_order.file_on_behalf'));
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/HeadFileOnBehalfPermissionsTest.php`
Expected: FAIL — `catalog_includes_the_new_file_on_behalf_permissions` fails (codes not found).

- [ ] **Step 3: Add the two permission codes to the catalog**

In `database/seeders/PermissionSeeder.php`, add two rows to the `$catalog` array right after the existing `passslip.approve` and `hospital_order.approve` entries:

```php
            ['module' => 'passslip',       'code' => 'passslip.file',           'description' => 'File pass slips'],
            ['module' => 'passslip',       'code' => 'passslip.approve',        'description' => 'Approve pass slips'],
            ['module' => 'passslip',       'code' => 'passslip.file_on_behalf', 'description' => 'File pass slips on behalf of managed employees'],
```

```php
            ['module' => 'hospital_order', 'code' => 'hospital_order.file',     'description' => 'File hospital orders'],
            ['module' => 'hospital_order', 'code' => 'hospital_order.approve',  'description' => 'Approve/manage hospital orders'],
            ['module' => 'hospital_order', 'code' => 'hospital_order.file_on_behalf', 'description' => 'File hospital orders on behalf of managed employees'],
```

- [ ] **Step 4: Auto-grant the two codes to heads inside `syncHeadshipGrants`**

In `app/Services/PermissionService.php:179`, change:

```php
        $codes = ['leave.approve', 'overtime.approve', 'passslip.approve', 'hospital_order.approve'];
```

to:

```php
        $codes = [
            'leave.approve', 'overtime.approve', 'passslip.approve', 'hospital_order.approve',
            'passslip.file_on_behalf', 'hospital_order.file_on_behalf',
        ];
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/HeadFileOnBehalfPermissionsTest.php`
Expected: `Tests: 3 passed`.

- [ ] **Step 6: Apply the seeder to the live database**

Run: `php artisan db:seed --class=PermissionSeeder`
Expected: output ends with `PermissionSeeder: N permissions seeded, auto-granted to M heads.` (idempotent — same mechanism this seeder has always used; safe to re-run).

- [ ] **Step 7: Commit**

```bash
git add database/seeders/PermissionSeeder.php app/Services/PermissionService.php tests/Feature/HeadFileOnBehalfPermissionsTest.php
git commit -m "feat: add passslip.file_on_behalf and hospital_order.file_on_behalf permissions"
```

---

### Task 4: `ManagedEmployeeController` — employee-picker endpoint

**Files:**
- Create: `app/Http/Controllers/ManagedEmployeeController.php`
- Modify: `routes/web.php` (add one route)
- Test: `tests/Feature/ManagedEmployeeControllerTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Http\Controllers\ManagedEmployeeController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ManagedEmployeeControllerTest extends TestCase
{
    use DatabaseTransactions;

    private int $shUserId = 9901001;
    private int $employeeId = 9901002;
    private int $outsideEmployeeId = 9901003;
    private int $sectionId = 8901001;
    private int $otherSectionId = 8901002;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tdh_user.users')->insertOrIgnore([
            ['id' => $this->shUserId, 'fname' => 'SH', 'lname' => 'MgdTest', 'section' => $this->sectionId, 'division' => 0, 'username' => 'sh_mgd_test', 'password' => 'x'],
            ['id' => $this->employeeId, 'fname' => 'Zeta', 'lname' => 'MgdTest', 'section' => $this->sectionId, 'division' => 0, 'username' => 'emp_mgd_test', 'password' => 'x'],
            ['id' => $this->outsideEmployeeId, 'fname' => 'Outside', 'lname' => 'MgdTest', 'section' => $this->otherSectionId, 'division' => 0, 'username' => 'outside_mgd_test', 'password' => 'x'],
        ]);

        DB::table('tdh_user.section')->insertOrIgnore([
            ['id' => $this->sectionId, 'description' => 'MgdTestSection', 'head' => $this->shUserId, 'division' => 0, 'code' => 'MTS'],
            ['id' => $this->otherSectionId, 'description' => 'MgdTestOtherSection', 'head' => 0, 'division' => 0, 'code' => 'MTO'],
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('tdh_user.section')->whereIn('id', [$this->sectionId, $this->otherSectionId])->delete();
        DB::table('tdh_user.users')->whereIn('id', [$this->shUserId, $this->employeeId, $this->outsideEmployeeId])->delete();
        parent::tearDown();
    }

    /** @test */
    public function returns_only_employees_in_the_heads_managed_section()
    {
        Auth::shouldReceive('id')->andReturn($this->shUserId);
        Auth::shouldReceive('check')->andReturn(true);

        $controller = app(ManagedEmployeeController::class);
        $response = $controller->index();
        $data = collect(json_decode($response->getContent(), true));

        $this->assertTrue($data->contains('id', $this->employeeId));
        $this->assertFalse($data->contains('id', $this->outsideEmployeeId));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/ManagedEmployeeControllerTest.php`
Expected: FAIL — `Class "App\Http\Controllers\ManagedEmployeeController" not found`.

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Services\OrgHierarchyService;
use Illuminate\Support\Facades\Auth;

class ManagedEmployeeController extends Controller
{
    protected OrgHierarchyService $orgHierarchy;

    public function __construct(OrgHierarchyService $orgHierarchy)
    {
        $this->orgHierarchy = $orgHierarchy;
    }

    public function index()
    {
        return response()->json($this->orgHierarchy->getManagedEmployees(Auth::id()));
    }
}
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, right before the `#HOSPITAL ORDERS` comment block (around line 500), add:

```php
    Route::get('/managed-employees', [\App\Http\Controllers\ManagedEmployeeController::class, 'index'])->name('managed-employees.index');
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test tests/Feature/ManagedEmployeeControllerTest.php`
Expected: `Tests: 1 passed`.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ManagedEmployeeController.php routes/web.php tests/Feature/ManagedEmployeeControllerTest.php
git commit -m "feat: add managed-employees endpoint for the file-on-behalf picker"
```

---

### Task 5: `PassSlipService` — on-behalf filing, visibility, and delete authorization

**Files:**
- Modify: `app/Models/PassSlip.php`
- Modify: `app/Services/PassSlipService.php`
- Test: `tests/Feature/PassSlipOnBehalfTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\PassSlip;
use App\Services\PassSlipService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PassSlipOnBehalfTest extends TestCase
{
    use DatabaseTransactions;

    private int $employeeId = 9301001;
    private int $otherSectionEmployeeId = 9301002;
    private int $shUserId = 9301003;
    private int $dhUserId = 9301004;
    private int $sectionId = 8301001;
    private int $otherSectionId = 8301002;
    private int $divisionId = 9401001;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('hris.pass_slips')->whereIn('user_id', [$this->employeeId, $this->otherSectionEmployeeId, $this->shUserId, $this->dhUserId])->delete();

        DB::table('tdh_user.users')->insertOrIgnore([
            ['id' => $this->shUserId, 'fname' => 'SH', 'lname' => 'PassTest', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'sh_pass_test', 'password' => 'x'],
            ['id' => $this->dhUserId, 'fname' => 'DH', 'lname' => 'PassTest', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'dh_pass_test', 'password' => 'x'],
            ['id' => $this->employeeId, 'fname' => 'Emp', 'lname' => 'PassTest', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'emp_pass_test', 'password' => 'x'],
            ['id' => $this->otherSectionEmployeeId, 'fname' => 'Other', 'lname' => 'PassTest', 'section' => $this->otherSectionId, 'division' => 0, 'username' => 'other_pass_test', 'password' => 'x'],
        ]);

        DB::table('tdh_user.division')->insertOrIgnore([
            ['id' => $this->divisionId, 'description' => 'PassTestDivision', 'head' => $this->dhUserId, 'code' => 'PTD'],
        ]);

        DB::table('tdh_user.section')->insertOrIgnore([
            ['id' => $this->sectionId, 'description' => 'PassTestSection', 'head' => $this->shUserId, 'division' => $this->divisionId, 'code' => 'PTS'],
            ['id' => $this->otherSectionId, 'description' => 'PassTestOtherSection', 'head' => 0, 'division' => 0, 'code' => 'PTO'],
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('hris.pass_slips')->whereIn('user_id', [$this->employeeId, $this->otherSectionEmployeeId, $this->shUserId, $this->dhUserId])->delete();
        DB::table('tdh_user.section')->whereIn('id', [$this->sectionId, $this->otherSectionId])->delete();
        DB::table('tdh_user.division')->where('id', $this->divisionId)->delete();
        DB::table('tdh_user.users')->whereIn('id', [$this->shUserId, $this->dhUserId, $this->employeeId, $this->otherSectionEmployeeId])->delete();
        parent::tearDown();
    }

    private function actAs(int $userId, bool $isAdmin = false): void
    {
        Auth::shouldReceive('id')->andReturn($userId);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => $isAdmin ? 'admin' : 'employee']);
    }

    /** @test */
    public function self_filing_is_unaffected_by_the_new_target_parameter()
    {
        $this->actAs($this->employeeId);
        $service = app(PassSlipService::class);

        $slip = $service->store([
            'slip_date' => '2026-08-04',
            'time_out' => '13:00',
            'time_in' => '14:00',
            'type' => 'Personal',
        ]);

        $this->assertEquals($this->employeeId, $slip->user_id);
        $this->assertNull($slip->filed_by);
    }

    /** @test */
    public function section_head_can_file_for_a_managed_employee_and_it_is_auto_approved()
    {
        $this->actAs($this->shUserId);
        $service = app(PassSlipService::class);

        $slip = $service->store([
            'slip_date' => '2026-08-04',
            'time_out' => '13:00',
            'time_in' => '14:00',
            'type' => 'Official',
        ], $this->employeeId);

        $this->assertEquals($this->employeeId, $slip->user_id);
        $this->assertEquals($this->shUserId, $slip->filed_by);
        $this->assertEquals('approved', $slip->status);
        $this->assertEquals($this->shUserId, $slip->approver_id);
        $this->assertNotNull($slip->approved_at);
    }

    /** @test */
    public function section_head_cannot_file_for_an_employee_outside_their_section()
    {
        $this->actAs($this->shUserId);
        $service = app(PassSlipService::class);

        $this->expectException(ValidationException::class);

        $service->store([
            'slip_date' => '2026-08-04',
            'time_out' => '13:00',
            'time_in' => '14:00',
            'type' => 'Official',
        ], $this->otherSectionEmployeeId);
    }

    /** @test */
    public function division_head_can_file_for_any_employee_in_their_division()
    {
        $this->actAs($this->dhUserId);
        $service = app(PassSlipService::class);

        $slip = $service->store([
            'slip_date' => '2026-08-04',
            'time_out' => '13:00',
            'time_in' => '14:00',
            'type' => 'Official',
        ], $this->employeeId);

        $this->assertEquals($this->dhUserId, $slip->filed_by);
        $this->assertEquals('approved', $slip->status);
    }

    /** @test */
    public function employee_cannot_delete_a_filing_made_on_their_behalf()
    {
        $this->actAs($this->shUserId);
        $service = app(PassSlipService::class);
        $slip = $service->store([
            'slip_date' => '2026-08-04',
            'time_out' => '13:00',
            'time_in' => '14:00',
            'type' => 'Official',
        ], $this->employeeId);

        $this->actAs($this->employeeId);
        $deleted = $service->destroy($slip->id);

        $this->assertFalse($deleted);
        $this->assertNotNull(PassSlip::find($slip->id));
    }

    /** @test */
    public function filing_head_can_delete_their_own_on_behalf_filing()
    {
        $this->actAs($this->shUserId);
        $service = app(PassSlipService::class);
        $slip = $service->store([
            'slip_date' => '2026-08-04',
            'time_out' => '13:00',
            'time_in' => '14:00',
            'type' => 'Official',
        ], $this->employeeId);

        $deleted = $service->destroy($slip->id);

        $this->assertTrue($deleted);
        $this->assertNull(PassSlip::find($slip->id));
    }

    /** @test */
    public function admin_can_delete_any_filing()
    {
        $this->actAs($this->shUserId);
        $service = app(PassSlipService::class);
        $slip = $service->store([
            'slip_date' => '2026-08-04',
            'time_out' => '13:00',
            'time_in' => '14:00',
            'type' => 'Official',
        ], $this->employeeId);

        $this->actAs($this->dhUserId, true);
        $deleted = $service->destroy($slip->id);

        $this->assertTrue($deleted);
    }

    /** @test */
    public function employees_own_list_includes_filings_made_on_their_behalf_with_filer_loaded()
    {
        $this->actAs($this->shUserId);
        $service = app(PassSlipService::class);
        $service->store([
            'slip_date' => '2026-08-04',
            'time_out' => '13:00',
            'time_in' => '14:00',
            'type' => 'Official',
        ], $this->employeeId);

        $this->actAs($this->employeeId);
        $slips = $service->getUserPassSlips();

        $this->assertCount(1, $slips);
        $this->assertEquals($this->shUserId, $slips->first()->filedBy->id);
    }

    /** @test */
    public function section_heads_review_queue_includes_their_own_auto_approved_on_behalf_filing()
    {
        $this->actAs($this->shUserId);
        $service = app(PassSlipService::class);
        $slip = $service->store([
            'slip_date' => '2026-08-04',
            'time_out' => '13:00',
            'time_in' => '14:00',
            'type' => 'Official',
        ], $this->employeeId);

        $result = $service->getPendingSlips();

        $found = $result->getCollection()->firstWhere('id', $slip->id);
        $this->assertNotNull($found);
        $this->assertEquals('approved', $found->status);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/PassSlipOnBehalfTest.php`
Expected: FAIL — `store()` does not accept a second argument, `filed_by` undefined, etc.

- [ ] **Step 3: Add `filed_by` to the `PassSlip` model**

In `app/Models/PassSlip.php`, update `$fillable` and add a `filedBy()` relation:

```php
    protected $fillable = [
        'user_id',
        'slip_date',
        'time_out',
        'time_in',
        'type',
        'status',
        'approver_id',
        'approved_at',
        'filed_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function filedBy()
    {
        return $this->belongsTo(User::class, 'filed_by');
    }
```

- [ ] **Step 4: Rewrite `PassSlipService`**

Replace the full contents of `app/Services/PassSlipService.php` with:

```php
<?php

namespace App\Services;

use App\Models\PassSlip;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PassSlipService
{
    private OrgHierarchyService $orgHierarchy;

    public function __construct(OrgHierarchyService $orgHierarchy)
    {
        $this->orgHierarchy = $orgHierarchy;
    }

    private function isAdmin(): bool
    {
        return in_array(session('access_rights'), ['admin'], true);
    }

    public function store(array $data, ?int $targetUserId = null)
    {
        $actingUserId = Auth::id();
        $subjectUserId = $actingUserId;
        $filedBy = null;

        if ($targetUserId !== null && $targetUserId !== $actingUserId) {
            $managedIds = $this->orgHierarchy->getManagedEmployeeIds($actingUserId);
            if (!in_array($targetUserId, $managedIds, true)) {
                throw ValidationException::withMessages([
                    'target_user_id' => 'You are not authorized to file a pass slip for this employee.',
                ]);
            }
            $subjectUserId = $targetUserId;
            $filedBy = $actingUserId;
        }

        $payload = [
            'user_id' => $subjectUserId,
            'slip_date' => $data['slip_date'],
            'time_out' => $data['time_out'],
            'time_in' => $data['time_in'] ?? null,
            'type' => $data['type'],
            'filed_by' => $filedBy,
        ];

        if ($filedBy !== null) {
            $payload['status'] = 'approved';
            $payload['approver_id'] = $filedBy;
            $payload['approved_at'] = now();
        } elseif (config('hris.pass_slip_auto_approve')) {
            $payload['status'] = 'approved';
            $payload['approved_at'] = now();
        }

        return PassSlip::create($payload);
    }

    public function getUserPassSlips()
    {
        return PassSlip::with('filedBy')
            ->where('user_id', Auth::id())
            ->orderByDesc('slip_date')
            ->get();
    }

    public function destroy($id)
    {
        $passSlip = PassSlip::where('id', $id)
            ->where(function ($q) {
                $q->where(function ($self) {
                    $self->where('user_id', Auth::id())->whereNull('filed_by');
                })->orWhere('filed_by', Auth::id());
            })
            ->first();

        if (!$passSlip && $this->isAdmin()) {
            $passSlip = PassSlip::find($id);
        }

        if ($passSlip) {
            return $passSlip->delete();
        }

        return false;
    }

    public function getPendingSlips(string $search = '', int $perPage = 10)
    {
        $isAdmin = $this->isAdmin();
        $managedIds = $this->orgHierarchy->getManagedEmployeeIds(Auth::id());

        if ($isAdmin && $search === '') {
            return PassSlip::with(['user', 'filedBy'])
                ->where(function ($q) {
                    $q->where('status', 'pending')->orWhere('filed_by', Auth::id());
                })
                ->orderByDesc('created_at')
                ->paginate($perPage);
        }

        $userQuery = User::query();
        if (!$isAdmin) {
            if (empty($managedIds)) {
                return PassSlip::query()->whereRaw('1 = 0')->paginate($perPage);
            }
            $userQuery->whereIn('id', $managedIds);
        }

        if ($search !== '') {
            $userQuery->where(function ($q) use ($search) {
                $q->where('fname', 'like', "%{$search}%")
                    ->orWhere('mname', 'like', "%{$search}%")
                    ->orWhere('lname', 'like', "%{$search}%")
                    ->orWhere('suffix', 'like', "%{$search}%");
            });
        }

        $userIds = $userQuery->pluck('id')->all();
        if (empty($userIds)) {
            return PassSlip::query()->whereRaw('1 = 0')->paginate($perPage);
        }

        return PassSlip::with(['user', 'filedBy'])
            ->where(function ($q) use ($userIds) {
                $q->where('status', 'pending')->orWhere('filed_by', Auth::id());
            })
            ->whereIn('user_id', $userIds)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function updateStatus($id, $status)
    {
        if ($this->isAdmin()) {
            $slip = PassSlip::find($id);
            if (!$slip) {
                return null;
            }

            $slip->status = $status;
            $slip->approver_id = Auth::id();
            $slip->approved_at = now();
            $slip->save();

            return $slip;
        }

        $managedIds = $this->orgHierarchy->getManagedEmployeeIds(Auth::id());
        if (empty($managedIds)) {
            return null;
        }

        $slip = PassSlip::find($id);
        if (!$slip) {
            return null;
        }

        if (!in_array((int) $slip->user_id, $managedIds, true)) {
            return null;
        }

        $slip->status = $status;
        $slip->approver_id = Auth::id();
        $slip->approved_at = now();
        $slip->save();

        return $slip;
    }
}
```

Note: this replaces the old section-only `getManagedSectionIds()` scoping in `getPendingSlips()`/`updateStatus()` with `OrgHierarchyService::getManagedEmployeeIds()`. This is a deliberate fix, not scope creep — the old helper never supported division heads (only `hospital_order.approve` had that), which would have silently broken this exact feature for division heads: their own auto-approved on-behalf pass slips would never have shown up in their review queue.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/PassSlipOnBehalfTest.php`
Expected: `Tests: 9 passed`.

- [ ] **Step 6: Run the existing pass slip approver regression check**

Run: `php artisan test --filter=PassSlip`
Expected: all pass slip tests pass (this confirms `updateStatus`/`getPendingSlips` still work correctly for plain section heads after the refactor).

- [ ] **Step 7: Commit**

```bash
git add app/Models/PassSlip.php app/Services/PassSlipService.php tests/Feature/PassSlipOnBehalfTest.php
git commit -m "feat: support filing pass slips on behalf of managed employees"
```

---

### Task 6: `PassSlipController` — wire on-behalf filing through the API

**Files:**
- Modify: `app/Http/Controllers/PassSlipController.php`
- Test: `tests/Feature/PassSlipControllerOnBehalfTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Http\Controllers\PassSlipController;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PassSlipControllerOnBehalfTest extends TestCase
{
    use DatabaseTransactions;

    private int $employeeId = 9501001;
    private int $shUserId = 9501002;
    private int $unauthorizedHeadId = 9501003;
    private int $sectionId = 8501001;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('hris.pass_slips')->whereIn('user_id', [$this->employeeId, $this->shUserId])->delete();

        DB::table('tdh_user.users')->insertOrIgnore([
            ['id' => $this->shUserId, 'fname' => 'SH', 'lname' => 'CtrlTest', 'section' => $this->sectionId, 'division' => 0, 'username' => 'sh_ctrl_test', 'password' => 'x'],
            ['id' => $this->unauthorizedHeadId, 'fname' => 'NoPerm', 'lname' => 'CtrlTest', 'section' => 0, 'division' => 0, 'username' => 'noperm_ctrl_test', 'password' => 'x'],
            ['id' => $this->employeeId, 'fname' => 'Emp', 'lname' => 'CtrlTest', 'section' => $this->sectionId, 'division' => 0, 'username' => 'emp_ctrl_test', 'password' => 'x'],
        ]);

        DB::table('tdh_user.section')->insertOrIgnore([
            ['id' => $this->sectionId, 'description' => 'CtrlTestSection', 'head' => $this->shUserId, 'division' => 0, 'code' => 'CTS'],
        ]);

        (new PermissionSeeder())->run();

        $permId = DB::table('hris.permissions')->where('code', 'passslip.file_on_behalf')->value('id');
        DB::table('hris.user_permissions')->updateOrInsert(
            ['user_id' => $this->shUserId, 'permission_id' => $permId],
            ['user_id' => $this->shUserId, 'permission_id' => $permId, 'type' => 'grant', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    protected function tearDown(): void
    {
        DB::table('hris.pass_slips')->whereIn('user_id', [$this->employeeId, $this->shUserId])->delete();
        DB::table('hris.user_permissions')->whereIn('user_id', [$this->shUserId, $this->unauthorizedHeadId])->delete();
        DB::table('tdh_user.section')->where('id', $this->sectionId)->delete();
        DB::table('tdh_user.users')->whereIn('id', [$this->shUserId, $this->unauthorizedHeadId, $this->employeeId])->delete();
        parent::tearDown();
    }

    private function actAs(int $userId): void
    {
        Auth::shouldReceive('id')->andReturn($userId);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'employee']);
    }

    /** @test */
    public function head_with_permission_can_file_on_behalf_via_the_controller()
    {
        $this->actAs($this->shUserId);
        $controller = app(PassSlipController::class);

        $request = Request::create('/pass-slip/store', 'POST', [
            'slip_date' => '2026-08-04',
            'time_out' => '13:00',
            'time_in' => '14:00',
            'type' => 'Official',
            'target_user_id' => $this->employeeId,
        ]);

        $response = $controller->store($request);

        $this->assertEquals(200, $response->getStatusCode());

        $exists = DB::table('hris.pass_slips')
            ->where('user_id', $this->employeeId)
            ->where('filed_by', $this->shUserId)
            ->where('status', 'approved')
            ->exists();
        $this->assertTrue($exists);
    }

    /** @test */
    public function user_without_the_permission_gets_403_when_targeting_another_employee()
    {
        $this->actAs($this->unauthorizedHeadId);
        $controller = app(PassSlipController::class);

        $request = Request::create('/pass-slip/store', 'POST', [
            'slip_date' => '2026-08-04',
            'time_out' => '13:00',
            'time_in' => '14:00',
            'type' => 'Official',
            'target_user_id' => $this->employeeId,
        ]);

        $response = $controller->store($request);

        $this->assertEquals(403, $response->getStatusCode());

        $exists = DB::table('hris.pass_slips')
            ->where('user_id', $this->employeeId)
            ->where('filed_by', $this->unauthorizedHeadId)
            ->exists();
        $this->assertFalse($exists);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/PassSlipControllerOnBehalfTest.php`
Expected: FAIL — controller ignores `target_user_id`, first test's `filed_by` assertion fails; second test gets 200 instead of 403.

- [ ] **Step 3: Update the controller's `store()` method**

In `app/Http/Controllers/PassSlipController.php`, replace the `store()` method:

```php
    public function store(Request $request)
    {
        $validated = $request->validate([
            'slip_date' => 'required|date',
            'time_out' => 'required',
            'time_in' => 'nullable',
            'type' => 'required|in:Personal,Official',
            'target_user_id' => 'nullable|integer',
        ]);

        $targetUserId = $validated['target_user_id'] ?? null;
        unset($validated['target_user_id']);

        if ($targetUserId !== null && $targetUserId !== Auth::id()
            && !$this->permissions->can(Auth::id(), 'passslip.file_on_behalf')) {
            return response()->json(['message' => 'You are not allowed to file pass slips on behalf of others.'], 403);
        }

        try {
            $this->service->store($validated, $targetUserId);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(['message' => 'Pass slip submitted successfully!']);
    }
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/PassSlipControllerOnBehalfTest.php`
Expected: `Tests: 2 passed`.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/PassSlipController.php tests/Feature/PassSlipControllerOnBehalfTest.php
git commit -m "feat: authorize and wire target_user_id through PassSlipController::store"
```

---

### Task 7: `pass_slip.blade.php` — employee picker and "Filed by" badge

**Files:**
- Modify: `resources/views/pass_slip.blade.php`

- [ ] **Step 1: Add the employee picker markup**

In `resources/views/pass_slip.blade.php`, add a `@php` block right after `@section('content')` (line 2):

```blade
@section('content')
    @php
        $canFileOnBehalf = (bool) session('is_section_head') || (bool) session('is_division_head');
    @endphp
```

Then, inside the "Create Pass Slip" panel, right before the `Date` `form-floating` block (before line 90 `<div class="form-floating mb-3">` that contains `#date`), add:

```blade
                    @if($canFileOnBehalf)
                        <div class="form-floating mb-3">
                            <select id="target_user_id" class="form-select">
                                <option value="">Myself</option>
                            </select>
                            <label for="target_user_id">File For</label>
                        </div>
                    @endif
```

- [ ] **Step 2: Widen the table header and loading/empty rows**

Replace the table header block:

```blade
                                <tr>
                                    <th>Date</th>
                                    <th>Time Out</th>
                                    <th>Time In</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th style="width: 4%"></th>
                                </tr>
```

with:

```blade
                                <tr>
                                    <th>Date</th>
                                    <th>Time Out</th>
                                    <th>Time In</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Filed By</th>
                                    <th style="width: 4%"></th>
                                </tr>
```

Replace the initial loading row:

```blade
                                <tr>
                                    <td colspan="5" class="text-center text-muted">Loading...</td>
                                </tr>
```

with:

```blade
                                <tr>
                                    <td colspan="7" class="text-center text-muted">Loading...</td>
                                </tr>
```

- [ ] **Step 3: Wire up the picker and badge in JS**

In the `@section('js')` block, right after `const $list = $('#pass_slip_list');` (around line 170), add:

```javascript
            const $target = $('#target_user_id');

            if ($target.length) {
                $target.select2({
                    placeholder: 'Myself',
                    allowClear: true,
                    width: '100%'
                });

                fetch('{{ route('managed-employees.index') }}')
                    .then(res => res.json())
                    .then(data => {
                        (data || []).forEach(emp => {
                            $target.append(new Option(emp.full_name, emp.id, false, false));
                        });
                    });
            }
```

Update the clear-button handler to also reset the picker:

```javascript
            $('#clearBtn').on('click', function () {
                $date.val('');
                $timeOut.val('');
                $timeIn.val('');
                $type.prop('selectedIndex', 0);
                if ($target.length) {
                    $target.val('').trigger('change');
                }
            });
```

Update the submit handler's `data` object to include the target when set:

```javascript
            $('#submitBtn').on('click', async function () {
                const data = {
                    slip_date: $date.val(),
                    time_out: $timeOut.val(),
                    time_in: $timeIn.val(),
                    type: $type.val()
                };

                if ($target.length && $target.val()) {
                    data.target_user_id = $target.val();
                }
```

Update the loading/empty-state row's colspan (in `renderPassSlipList`):

```javascript
                if (slips.length === 0) {
                    $list.html(`<tr><td colspan="7" class="text-center text-muted">No pass slips found.</td></tr>`);
                    return;
                }
```

Update the row template to add the "Filed By" cell:

```javascript
                $.each(slips, function (_, slip) {
                    const badgeColor = getStatusColor(slip.status);
                    const deleteButton = slip.status === 'pending'
                        ? `<button class="btn btn-action btn-delete delete-btn" data-id="${slip.id}" title="Delete"><i class="bi bi-trash"></i></button>`
                        : '';
                    const filedByLabel = slip.filedBy
                        ? `<span class="badge bg-info text-dark">Filed by ${slip.filedBy.fname} ${slip.filedBy.lname}</span>`
                        : '-';

                    const tr = `
            <tr>
                <td>${moment(slip.slip_date, "YYYY-MM-DD").format("MMM D, YYYY")}</td>
                <td>${moment(slip.time_out, "HH:mm:ss").format("hh:mm A")}</td>
                <td>${slip.time_in ? moment(slip.time_in, "HH:mm:ss").format("hh:mm A") : '-'}</td>
                <td class="text-capitalize">${slip.type}</td>
                <td><span class="badge bg-${badgeColor} text-uppercase">${slip.status}</span></td>
                <td>${filedByLabel}</td>
                <td>${deleteButton}</td>
            </tr>
        `;
                    $list.append(tr);
                });
```

- [ ] **Step 4: Sanity-check the view renders without errors**

Run: `php artisan view:clear && php artisan test tests/Feature/PassSlipOnBehalfTest.php tests/Feature/PassSlipControllerOnBehalfTest.php`
Expected: `Tests: 11 passed` (confirms nothing in the shared `PassSlip` model/service broke while editing the view; Blade compilation errors would surface as a fatal `ErrorException` here too since the view is loaded transitively by the app boot in some test setups — if not, proceed to the manual smoke test in Task 13).

- [ ] **Step 5: Commit**

```bash
git add resources/views/pass_slip.blade.php
git commit -m "feat: add file-on-behalf employee picker and filed-by badge to pass slip page"
```

---

### Task 8: `pass_slip/approver.blade.php` — status column and "Filed by you" visibility

**Files:**
- Modify: `resources/views/pass_slip/approver.blade.php`

- [ ] **Step 1: Widen the table header**

Replace:

```blade
                    <tr>
                        <th>Employee</th>
                        <th>Date</th>
                        <th>Time Out</th>
                        <th>Time In</th>
                        <th>Type</th>
                        <th>Actions</th>
                    </tr>
```

with:

```blade
                    <tr>
                        <th>Employee</th>
                        <th>Date</th>
                        <th>Time Out</th>
                        <th>Time In</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Filed By</th>
                        <th>Actions</th>
                    </tr>
```

Update the initial loading row's colspan from `6` to `8`:

```blade
                    <tr>
                        <td colspan="8" class="text-muted text-center">Loading pending slips...</td>
                    </tr>
```

- [ ] **Step 2: Update the JS row rendering**

In the `loadPendingSlips()` function, update the empty-state and row-building code:

```javascript
                if (slips.length === 0) {
                    table.html(`<tr><td colspan="8" class="text-center text-muted">No slips to review.</td></tr>`);
                    return;
                }

                slips.forEach(slip => {
                    const formattedDate = moment(slip.slip_date, "YYYY-MM-DD").format("MMM D, YYYY");
                    const status = (slip.status || 'pending').toLowerCase();
                    const statusColor = status === 'approved' ? 'success' : (status === 'rejected' ? 'danger' : 'secondary');
                    const filedByLabel = slip.filedBy
                        ? `<span class="badge bg-info text-dark">${formatFullName(slip.filedBy)}</span>`
                        : '-';
                    const actions = status === 'pending'
                        ? `
                        <button class="btn btn-action btn-edit approve-btn" data-id="${slip.id}" title="Approve">
                            <i class="bi bi-check2"></i>
                        </button>
                        <button class="btn btn-action btn-delete reject-btn" data-id="${slip.id}" title="Reject">
                            <i class="bi bi-x"></i>
                        </button>
                    `
                        : '-';

                    const row = `
                <tr>
                    <td>${formatFullName(slip.user)}</td>
                    <td>${formattedDate}</td>
                    <td>${slip.time_out}</td>
                    <td>${slip.time_in ?? '-'}</td>
                    <td class="text-capitalize">${slip.type}</td>
                    <td><span class="badge bg-${statusColor} text-uppercase">${status}</span></td>
                    <td>${filedByLabel}</td>
                    <td>${actions}</td>
                </tr>
            `;
                    table.append(row);
                });
```

- [ ] **Step 3: Sanity-check**

Run: `php artisan view:clear && php artisan test tests/Feature/PassSlipOnBehalfTest.php`
Expected: `Tests: 9 passed`.

- [ ] **Step 4: Commit**

```bash
git add resources/views/pass_slip/approver.blade.php
git commit -m "feat: show status and filed-by badge in the pass slip approver queue"
```

---

### Task 9: `HospitalOrderServices` — on-behalf filing, visibility, and delete authorization

**Files:**
- Modify: `app/Services/HospitalOrderServices.php`
- Test: `tests/Feature/HospitalOrderOnBehalfTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Services\HospitalOrderServices;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HospitalOrderOnBehalfTest extends TestCase
{
    use DatabaseTransactions;

    private int $employeeId = 9701001;
    private int $otherSectionEmployeeId = 9701002;
    private int $shUserId = 9701003;
    private int $dhUserId = 9701004;
    private int $sectionId = 8701001;
    private int $otherSectionId = 8701002;
    private int $divisionId = 9801001;
    private int $hoId;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tdh_user.users')->insertOrIgnore([
            ['id' => $this->shUserId, 'fname' => 'SH', 'lname' => 'HoTest', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'sh_ho_test', 'password' => 'x'],
            ['id' => $this->dhUserId, 'fname' => 'DH', 'lname' => 'HoTest', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'dh_ho_test', 'password' => 'x'],
            ['id' => $this->employeeId, 'fname' => 'Emp', 'lname' => 'HoTest', 'section' => $this->sectionId, 'division' => $this->divisionId, 'username' => 'emp_ho_test', 'password' => 'x'],
            ['id' => $this->otherSectionEmployeeId, 'fname' => 'Other', 'lname' => 'HoTest', 'section' => $this->otherSectionId, 'division' => 0, 'username' => 'other_ho_test', 'password' => 'x'],
        ]);

        DB::table('tdh_user.division')->insertOrIgnore([
            ['id' => $this->divisionId, 'description' => 'HoTestDivision', 'head' => $this->dhUserId, 'code' => 'HTD'],
        ]);

        DB::table('tdh_user.section')->insertOrIgnore([
            ['id' => $this->sectionId, 'description' => 'HoTestSection', 'head' => $this->shUserId, 'division' => $this->divisionId, 'code' => 'HTS'],
            ['id' => $this->otherSectionId, 'description' => 'HoTestOtherSection', 'head' => 0, 'division' => 0, 'code' => 'HTO'],
        ]);

        $this->hoId = DB::table('hris.hospital_orders')->insertGetId([
            'order_number' => 'HO-TEST-' . uniqid(),
            'order_date' => '2026-08-04',
            'time_from' => '08:00',
            'time_to' => '17:00',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-10',
            'created_by' => $this->shUserId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('hris.hospital_order_employees')->where('ho_id', $this->hoId)->delete();
        DB::table('hris.hospital_orders')->where('id', $this->hoId)->delete();
        DB::table('tdh_user.section')->whereIn('id', [$this->sectionId, $this->otherSectionId])->delete();
        DB::table('tdh_user.division')->where('id', $this->divisionId)->delete();
        DB::table('tdh_user.users')->whereIn('id', [$this->shUserId, $this->dhUserId, $this->employeeId, $this->otherSectionEmployeeId])->delete();
        parent::tearDown();
    }

    private function actAs(int $userId, bool $isAdmin = false): void
    {
        Auth::shouldReceive('id')->andReturn($userId);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => $isAdmin ? 'admin' : 'employee']);
    }

    /** @test */
    public function file_for_self_is_unaffected_and_stays_pending()
    {
        $this->actAs($this->employeeId);
        $service = app(HospitalOrderServices::class);

        $service->fileForSelf($this->hoId, '2026-08-05', '08:00', '17:00');

        $row = DB::table('hris.hospital_order_employees')
            ->where('ho_id', $this->hoId)->where('userid', $this->employeeId)->first();

        $this->assertNotNull($row);
        $this->assertNull($row->filed_by);
        $this->assertEquals('pending', $row->status);
    }

    /** @test */
    public function section_head_can_file_for_a_managed_employee_and_it_is_auto_approved()
    {
        $this->actAs($this->shUserId);
        $service = app(HospitalOrderServices::class);

        $service->fileForEmployee($this->hoId, $this->employeeId, '2026-08-05', '08:00', '17:00');

        $row = DB::table('hris.hospital_order_employees')
            ->where('ho_id', $this->hoId)->where('userid', $this->employeeId)->first();

        $this->assertEquals($this->shUserId, $row->filed_by);
        $this->assertEquals('approved', $row->status);
        $this->assertEquals($this->shUserId, $row->reviewed_by);
        $this->assertNotNull($row->reviewed_at);
    }

    /** @test */
    public function section_head_cannot_file_for_an_employee_outside_their_section()
    {
        $this->actAs($this->shUserId);
        $service = app(HospitalOrderServices::class);

        $this->expectException(ValidationException::class);

        $service->fileForEmployee($this->hoId, $this->otherSectionEmployeeId, '2026-08-05', '08:00', '17:00');
    }

    /** @test */
    public function division_head_can_file_for_any_employee_in_their_division()
    {
        $this->actAs($this->dhUserId);
        $service = app(HospitalOrderServices::class);

        $service->fileForEmployee($this->hoId, $this->employeeId, '2026-08-05', '08:00', '17:00');

        $row = DB::table('hris.hospital_order_employees')
            ->where('ho_id', $this->hoId)->where('userid', $this->employeeId)->first();

        $this->assertEquals($this->dhUserId, $row->filed_by);
        $this->assertEquals('approved', $row->status);
    }

    /** @test */
    public function employee_cannot_delete_a_filing_made_on_their_behalf()
    {
        $this->actAs($this->shUserId);
        $service = app(HospitalOrderServices::class);
        $service->fileForEmployee($this->hoId, $this->employeeId, '2026-08-05', '08:00', '17:00');
        $filingId = DB::table('hris.hospital_order_employees')
            ->where('ho_id', $this->hoId)->where('userid', $this->employeeId)->value('id');

        $this->actAs($this->employeeId);

        $this->expectException(ValidationException::class);
        $service->deleteMyFiling($filingId);
    }

    /** @test */
    public function filing_head_can_delete_their_own_approved_on_behalf_filing()
    {
        $this->actAs($this->shUserId);
        $service = app(HospitalOrderServices::class);
        $service->fileForEmployee($this->hoId, $this->employeeId, '2026-08-05', '08:00', '17:00');
        $filingId = DB::table('hris.hospital_order_employees')
            ->where('ho_id', $this->hoId)->where('userid', $this->employeeId)->value('id');

        $service->deleteMyFiling($filingId);

        $this->assertNull(DB::table('hris.hospital_order_employees')->where('id', $filingId)->first());
    }

    /** @test */
    public function admin_can_delete_any_on_behalf_filing()
    {
        $this->actAs($this->shUserId);
        $service = app(HospitalOrderServices::class);
        $service->fileForEmployee($this->hoId, $this->employeeId, '2026-08-05', '08:00', '17:00');
        $filingId = DB::table('hris.hospital_order_employees')
            ->where('ho_id', $this->hoId)->where('userid', $this->employeeId)->value('id');

        $this->actAs($this->dhUserId, true);
        $service->deleteMyFiling($filingId);

        $this->assertNull(DB::table('hris.hospital_order_employees')->where('id', $filingId)->first());
    }

    /** @test */
    public function employees_own_filings_list_includes_the_filer_name()
    {
        $this->actAs($this->shUserId);
        $service = app(HospitalOrderServices::class);
        $service->fileForEmployee($this->hoId, $this->employeeId, '2026-08-05', '08:00', '17:00');

        $this->actAs($this->employeeId);
        $filings = $service->getMyFilings();

        $filing = $filings->firstWhere('ho_id', $this->hoId);
        $this->assertNotNull($filing);
        $this->assertEquals('SH HoTest', trim($filing->filed_by_name));
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/HospitalOrderOnBehalfTest.php`
Expected: FAIL — `fileForEmployee` does not exist, `filed_by`/`filed_by_name` undefined, delete-authorization tests fail.

- [ ] **Step 3: Rewrite `HospitalOrderServices`**

Replace the full contents of `app/Services/HospitalOrderServices.php` with:

```php
<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HospitalOrderServices
{
    private OrgHierarchyService $orgHierarchy;

    public function __construct(OrgHierarchyService $orgHierarchy)
    {
        $this->orgHierarchy = $orgHierarchy;
    }

    private function isAdmin()
    {
        return in_array(session('access_rights'), ['admin'], true);
    }

    private function isSectionHead()
    {
        return (bool) session('is_section_head');
    }

    private function isDivisionHead()
    {
        return (bool) session('is_division_head');
    }

    private function applyManagedEmployeeScope($query, $employeeAlias = 'u')
    {
        if ($this->isDivisionHead()) {
            $managedDivisionIds = DB::table('tdh_user.division')
                ->where('head', Auth::id())
                ->pluck('id')
                ->toArray();

            if (empty($managedDivisionIds)) {
                $query->whereRaw('1=0');
                return;
            }

            $query->whereIn($employeeAlias . '.division', $managedDivisionIds);
            return;
        }

        if ($this->isSectionHead()) {
            $managedSectionIds = DB::table('tdh_user.section')
                ->where('head', Auth::id())
                ->pluck('id')
                ->toArray();

            if (empty($managedSectionIds)) {
                $query->whereRaw('1=0');
                return;
            }

            $query->whereIn($employeeAlias . '.section', $managedSectionIds);
            return;
        }

        $query->whereRaw('1=0');
    }

    public function createHospitalOrder($orderNumber, $startDate, $endDate, $timeFrom, $timeTo)
    {
        DB::table('hris.hospital_orders')->insert([
            'order_number' => $orderNumber,
            'order_date' => $startDate,
            'time_from' => $timeFrom,
            'time_to' => $timeTo,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'created_by' => Auth::id(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    public function getHospitalOrders($orderNumber = '')
    {
        return DB::table('hris.hospital_orders as ho')
            ->where(function ($query) use ($orderNumber) {
                if ($orderNumber !== '') {
                    $query->where('ho.order_number', 'LIKE', '%' . $orderNumber . '%');
                }
            })
            ->orderBy('ho.start_date', 'desc')
            ->orderBy('ho.id', 'desc')
            ->limit(10)
            ->get([
                'ho.id',
                'ho.order_number',
                'ho.start_date',
                'ho.end_date',
                'ho.time_from',
                'ho.time_to',
                'ho.created_by',
            ]);
    }

    public function fileForSelf($hoId, $filedDate, $timeFrom, $timeTo)
    {
        return $this->file($hoId, Auth::id(), null, $filedDate, $timeFrom, $timeTo);
    }

    public function fileForEmployee($hoId, $targetUserId, $filedDate, $timeFrom, $timeTo)
    {
        $managedIds = $this->orgHierarchy->getManagedEmployeeIds(Auth::id());
        if (!in_array((int) $targetUserId, $managedIds, true)) {
            throw ValidationException::withMessages([
                'target_user_id' => 'You are not authorized to file a hospital order for this employee.',
            ]);
        }

        return $this->file($hoId, (int) $targetUserId, Auth::id(), $filedDate, $timeFrom, $timeTo);
    }

    private function file($hoId, $subjectUserId, $filedBy, $filedDate, $timeFrom, $timeTo)
    {
        $hospitalOrder = DB::table('hris.hospital_orders')
            ->where('id', $hoId)
            ->first();

        if (!$hospitalOrder) {
            throw ValidationException::withMessages([
                'ho_id' => 'Hospital order not found.',
            ]);
        }

        if ($hospitalOrder->start_date && $hospitalOrder->end_date) {
            $startDate = Carbon::parse($hospitalOrder->start_date)->startOfDay();
            $endDate = Carbon::parse($hospitalOrder->end_date)->endOfDay();
            $toFileDate = Carbon::parse($filedDate);

            if (!$toFileDate->betweenIncluded($startDate, $endDate)) {
                throw ValidationException::withMessages([
                    'filed_date' => 'Filed date must be within the hospital order date range.',
                ]);
            }
        } elseif ($hospitalOrder->order_date && $filedDate !== $hospitalOrder->order_date) {
            throw ValidationException::withMessages([
                'filed_date' => 'Filed date must match the hospital order date.',
            ]);
        }

        $exists = DB::table('hris.hospital_order_employees')
            ->where('ho_id', $hoId)
            ->where('userid', $subjectUserId)
            ->where('filed_date', $filedDate)
            ->where('time_from', $timeFrom)
            ->where('time_to', $timeTo)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'filed_date' => 'This employee already has a filing with the same date and time range for this hospital order.',
            ]);
        }

        $payload = [
            'ho_id' => $hoId,
            'userid' => $subjectUserId,
            'filed_date' => $filedDate,
            'time_from' => $timeFrom,
            'time_to' => $timeTo,
            'filed_by' => $filedBy,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        if ($filedBy !== null) {
            $payload['status'] = 'approved';
            $payload['reviewed_by'] = $filedBy;
            $payload['reviewed_at'] = Carbon::now();
        } else {
            $payload['status'] = 'pending';
        }

        DB::table('hris.hospital_order_employees')->insert($payload);
    }

    public function getMyFilings()
    {
        return DB::table('hris.hospital_order_employees as hoe')
            ->join('hris.hospital_orders as ho', 'ho.id', '=', 'hoe.ho_id')
            ->leftJoin('tdh_user.users as filer', 'filer.id', '=', 'hoe.filed_by')
            ->where('hoe.userid', Auth::id())
            ->orderBy('hoe.created_at', 'desc')
            ->get([
                'hoe.id',
                'hoe.ho_id',
                'ho.order_number',
                'hoe.filed_date',
                'hoe.time_from',
                'hoe.time_to',
                'hoe.status',
                'hoe.remarks',
                'hoe.reviewed_at',
                'hoe.filed_by',
                DB::raw("CONCAT(COALESCE(filer.fname, ''), ' ', COALESCE(filer.lname, '')) as filed_by_name"),
            ]);
    }

    public function getFilingsForReview()
    {
        $query = DB::table('hris.hospital_order_employees as hoe')
            ->join('tdh_user.users as u', 'u.id', '=', 'hoe.userid')
            ->leftJoin('tdh_user.users as filer', 'filer.id', '=', 'hoe.filed_by')
            ->join('hris.hospital_orders as ho', 'ho.id', '=', 'hoe.ho_id')
            ->orderByRaw("CASE WHEN hoe.status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('hoe.created_at', 'desc');

        if (!$this->isAdmin()) {
            $query->where(function ($where) {
                $this->applyManagedEmployeeScope($where, 'u');
            });
        }

        return $query->get([
            'hoe.id',
            'ho.order_number',
            'ho.created_by',
            'hoe.userid',
            DB::raw("CONCAT(COALESCE(u.fname, ''), ' ', COALESCE(LEFT(u.mname,1), ''), CASE WHEN u.mname IS NULL OR u.mname = '' THEN '' ELSE '. ' END, COALESCE(u.lname, ''), CASE WHEN u.suffix IS NULL OR u.suffix = '' THEN '' ELSE CONCAT(' ', u.suffix) END) as employee_name"),
            'hoe.filed_date',
            'hoe.time_from',
            'hoe.time_to',
            'hoe.status',
            'hoe.remarks',
            'hoe.reviewed_at',
            'hoe.filed_by',
            DB::raw("CONCAT(COALESCE(filer.fname, ''), ' ', COALESCE(filer.lname, '')) as filed_by_name"),
        ]);
    }

    public function reviewFiling($filingId, $status, $remarks = null)
    {
        $filing = DB::table('hris.hospital_order_employees as hoe')
            ->join('tdh_user.users as u', 'u.id', '=', 'hoe.userid')
            ->where('hoe.id', $filingId)
            ->select('hoe.id', 'hoe.status')
            ;

        if (!$this->isAdmin()) {
            $filing->where(function ($where) {
                $this->applyManagedEmployeeScope($where, 'u');
            });
        }

        $filing = $filing->first();

        if (!$filing) {
            throw ValidationException::withMessages([
                'id' => 'Filing record not found.',
            ]);
        }

        if (!in_array(strtolower((string) $filing->status), ['pending'], true)) {
            throw ValidationException::withMessages([
                'id' => 'Only pending filings can be reviewed.',
            ]);
        }

        DB::table('hris.hospital_order_employees')
            ->where('id', $filingId)
            ->update([
                'status' => $status,
                'remarks' => $remarks,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
    }

    public function deleteMyFiling($filingId)
    {
        $filing = DB::table('hris.hospital_order_employees')
            ->where('id', $filingId)
            ->first(['id', 'status', 'userid', 'filed_by']);

        if (!$filing) {
            throw ValidationException::withMessages([
                'id' => 'Filing record not found.',
            ]);
        }

        $isSelfFiled = (int) $filing->userid === (int) Auth::id() && empty($filing->filed_by);
        $isFilingHead = !empty($filing->filed_by) && (int) $filing->filed_by === (int) Auth::id();

        if (!$isSelfFiled && !$isFilingHead && !$this->isAdmin()) {
            throw ValidationException::withMessages([
                'id' => 'Filing record not found.',
            ]);
        }

        if ($isSelfFiled) {
            $status = strtolower((string) $filing->status);
            if (!in_array($status, ['pending', 'rejected'], true)) {
                throw ValidationException::withMessages([
                    'id' => 'Only pending or rejected filings can be deleted.',
                ]);
            }
        }

        DB::table('hris.hospital_order_employees')
            ->where('id', $filingId)
            ->delete();
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/HospitalOrderOnBehalfTest.php`
Expected: `Tests: 8 passed`.

- [ ] **Step 5: Commit**

```bash
git add app/Services/HospitalOrderServices.php tests/Feature/HospitalOrderOnBehalfTest.php
git commit -m "feat: support filing hospital orders on behalf of managed employees"
```

---

### Task 10: `HospitalOrderController` — wire on-behalf filing through the API

**Files:**
- Modify: `app/Http/Controllers/HospitalOrderController.php`
- Test: `tests/Feature/HospitalOrderControllerOnBehalfTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Http\Controllers\HospitalOrderController;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HospitalOrderControllerOnBehalfTest extends TestCase
{
    use DatabaseTransactions;

    private int $employeeId = 9601001;
    private int $shUserId = 9601002;
    private int $unauthorizedHeadId = 9601003;
    private int $sectionId = 8601001;
    private int $hoId;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tdh_user.users')->insertOrIgnore([
            ['id' => $this->shUserId, 'fname' => 'SH', 'lname' => 'HoCtrlTest', 'section' => $this->sectionId, 'division' => 0, 'username' => 'sh_hoctrl_test', 'password' => 'x'],
            ['id' => $this->unauthorizedHeadId, 'fname' => 'NoPerm', 'lname' => 'HoCtrlTest', 'section' => 0, 'division' => 0, 'username' => 'noperm_hoctrl_test', 'password' => 'x'],
            ['id' => $this->employeeId, 'fname' => 'Emp', 'lname' => 'HoCtrlTest', 'section' => $this->sectionId, 'division' => 0, 'username' => 'emp_hoctrl_test', 'password' => 'x'],
        ]);

        DB::table('tdh_user.section')->insertOrIgnore([
            ['id' => $this->sectionId, 'description' => 'HoCtrlTestSection', 'head' => $this->shUserId, 'division' => 0, 'code' => 'HCS'],
        ]);

        (new PermissionSeeder())->run();

        $permId = DB::table('hris.permissions')->where('code', 'hospital_order.file_on_behalf')->value('id');
        DB::table('hris.user_permissions')->updateOrInsert(
            ['user_id' => $this->shUserId, 'permission_id' => $permId],
            ['user_id' => $this->shUserId, 'permission_id' => $permId, 'type' => 'grant', 'created_at' => now(), 'updated_at' => now()]
        );

        $this->hoId = DB::table('hris.hospital_orders')->insertGetId([
            'order_number' => 'HOCTRL-TEST-' . uniqid(),
            'order_date' => '2026-08-04',
            'time_from' => '08:00',
            'time_to' => '17:00',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-10',
            'created_by' => $this->shUserId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('hris.hospital_order_employees')->where('ho_id', $this->hoId)->delete();
        DB::table('hris.hospital_orders')->where('id', $this->hoId)->delete();
        DB::table('hris.user_permissions')->whereIn('user_id', [$this->shUserId, $this->unauthorizedHeadId])->delete();
        DB::table('tdh_user.section')->where('id', $this->sectionId)->delete();
        DB::table('tdh_user.users')->whereIn('id', [$this->shUserId, $this->unauthorizedHeadId, $this->employeeId])->delete();
        parent::tearDown();
    }

    private function actAs(int $userId): void
    {
        Auth::shouldReceive('id')->andReturn($userId);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'employee']);
    }

    /** @test */
    public function head_with_permission_can_file_on_behalf_via_the_controller()
    {
        $this->actAs($this->shUserId);
        $controller = app(HospitalOrderController::class);

        $request = Request::create('/hospital-orders/file', 'POST', [
            'ho_id' => $this->hoId,
            'filed_date' => '2026-08-05',
            'time_from' => '08:00',
            'time_to' => '17:00',
            'target_user_id' => $this->employeeId,
        ]);

        $response = $controller->fileForSelf($request);

        $this->assertEquals(200, $response->getStatusCode());

        $exists = DB::table('hris.hospital_order_employees')
            ->where('ho_id', $this->hoId)
            ->where('userid', $this->employeeId)
            ->where('filed_by', $this->shUserId)
            ->where('status', 'approved')
            ->exists();
        $this->assertTrue($exists);
    }

    /** @test */
    public function user_without_the_permission_gets_403_when_targeting_another_employee()
    {
        $this->actAs($this->unauthorizedHeadId);
        $controller = app(HospitalOrderController::class);

        $request = Request::create('/hospital-orders/file', 'POST', [
            'ho_id' => $this->hoId,
            'filed_date' => '2026-08-05',
            'time_from' => '08:00',
            'time_to' => '17:00',
            'target_user_id' => $this->employeeId,
        ]);

        $response = $controller->fileForSelf($request);

        $this->assertEquals(403, $response->getStatusCode());

        $exists = DB::table('hris.hospital_order_employees')
            ->where('ho_id', $this->hoId)
            ->where('userid', $this->employeeId)
            ->exists();
        $this->assertFalse($exists);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/HospitalOrderControllerOnBehalfTest.php`
Expected: FAIL — controller ignores `target_user_id`; both assertions fail.

- [ ] **Step 3: Update the controller's `fileForSelf()` method**

In `app/Http/Controllers/HospitalOrderController.php`, replace the `fileForSelf()` method:

```php
    public function fileForSelf(Request $request)
    {
        try {
            $request->validate([
                'ho_id' => 'required|integer',
                'filed_date' => 'required|date',
                'time_from' => 'required|date_format:H:i',
                'time_to' => 'required|date_format:H:i|after:time_from',
                'target_user_id' => 'nullable|integer',
            ]);

            $targetUserId = $request->target_user_id;

            if ($targetUserId && (int) $targetUserId !== Auth::id()) {
                if (!$this->permissions->can(Auth::id(), 'hospital_order.file_on_behalf')) {
                    return response()->json(['error' => 'You are not allowed to file hospital orders on behalf of others.'], 403);
                }

                $this->hospitalService->fileForEmployee(
                    $request->ho_id,
                    (int) $targetUserId,
                    $request->filed_date,
                    $request->time_from,
                    $request->time_to
                );

                return response()->json(['message' => 'Hospital order filing submitted successfully.']);
            }

            $this->hospitalService->fileForSelf(
                $request->ho_id,
                $request->filed_date,
                $request->time_from,
                $request->time_to
            );

            return response()->json(['message' => 'Hospital order filing submitted successfully.']);
        } catch (ValidationException $exception) {
            return response()->json(['errors' => $exception->errors()], 422);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/HospitalOrderControllerOnBehalfTest.php`
Expected: `Tests: 2 passed`.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/HospitalOrderController.php tests/Feature/HospitalOrderControllerOnBehalfTest.php
git commit -m "feat: authorize and wire target_user_id through HospitalOrderController::fileForSelf"
```

---

### Task 11: `hospital_order_file.blade.php` — employee picker and "Filed by" badge

**Files:**
- Modify: `resources/views/hospital_order_file.blade.php`

- [ ] **Step 1: Add the `$canFileOnBehalf` flag and the picker markup**

Right after `@section('content')` (line 3), add:

```blade
@section('content')
    @php
        $canFileOnBehalf = (bool) session('is_section_head') || (bool) session('is_division_head');
    @endphp
```

In the filing form, right before the `Hospital Order #` column (`<div class="col-lg-4">` around line 118), add:

```blade
                @if($canFileOnBehalf)
                    <div class="col-lg-3">
                        <label class="ho-label">File For</label>
                        <select class="form-select" name="target_user_id" id="targetUserSelect">
                            <option value="">Myself</option>
                        </select>
                    </div>
                @endif
```

- [ ] **Step 2: Widen the "My Filings" table**

Replace the table header:

```blade
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Time Range</th>
                        <th>Status</th>
                        <th>Remarks</th>
                        <th>Action</th>
                    </tr>
```

with:

```blade
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Time Range</th>
                        <th>Status</th>
                        <th>Filed By</th>
                        <th>Remarks</th>
                        <th>Action</th>
                    </tr>
```

Update the initial loading row's colspan from `6` to `7`:

```blade
                    <tbody id="my_filing_list">
                    <tr><td colspan="7" class="ho-empty">Loading...</td></tr>
                    </tbody>
```

- [ ] **Step 3: Wire up the picker in JS**

In the `$(document).ready(...)` block, right after `getMyFilings();` (around line 192), add:

```javascript
        if ($('#targetUserSelect').length) {
            $('#targetUserSelect').select2({
                placeholder: 'Myself',
                allowClear: true,
                width: '100%'
            });
            loadManagedEmployees();
        }
```

Add the `loadManagedEmployees` function near the other top-level functions (after `getHospitalOrders()`):

```javascript
    function loadManagedEmployees() {
        fetch("{{ route('managed-employees.index') }}")
            .then(res => res.json())
            .then(data => {
                const $select = $('#targetUserSelect');
                (data || []).forEach(emp => {
                    $select.append(new Option(emp.full_name, emp.id, false, false));
                });
            });
    }
```

- [ ] **Step 4: Update the empty-state colspan and add the "Filed By" cell**

In `renderFilings()`, update the empty-state message:

```javascript
        if (!filteredFilings.length) {
            $list.html('<tr><td colspan="7" class="ho-empty">No filings found.</td></tr>');
            $('#filingSummary').text('0 results');
            return;
        }
```

Update the row template:

```javascript
        pageItems.forEach(it => {
            const filedByLabel = it.filed_by
                ? `<span class="ho-chip">Filed by ${escapeHtml((it.filed_by_name || '').trim())}</span>`
                : '-';
            $list.append(`
                <tr>
                    <td><span class="ho-chip">#${escapeHtml(it.order_number || '')}</span></td>
                    <td>${formatDate(it.filed_date)}</td>
                    <td>${formatTimeRange(it.time_from, it.time_to)}</td>
                    <td>${statusBadge(it.status)}</td>
                    <td>${filedByLabel}</td>
                    <td>${escapeHtml(it.remarks || '-')}</td>
                    <td>${deleteAction(it)}</td>
                </tr>
            `);
        });
```

Also update the failure state in `getMyFilings()`:

```javascript
            .catch(() => {
                $('#my_filing_list').html('<tr><td colspan="7" class="ho-empty text-danger">Failed to load.</td></tr>');
                $('#filingPagination').empty();
                $('#filingSummary').text('');
            });
```

- [ ] **Step 5: Sanity-check**

Run: `php artisan view:clear && php artisan test tests/Feature/HospitalOrderOnBehalfTest.php tests/Feature/HospitalOrderControllerOnBehalfTest.php`
Expected: `Tests: 10 passed`.

- [ ] **Step 6: Commit**

```bash
git add resources/views/hospital_order_file.blade.php
git commit -m "feat: add file-on-behalf employee picker and filed-by badge to hospital order filing page"
```

---

### Task 12: `hospital_order_approval.blade.php` — "Filed by you" visibility

**Files:**
- Modify: `resources/views/hospital_order_approval.blade.php`

- [ ] **Step 1: Widen the review table header**

Replace:

```blade
                        <tr>
                            <th>Employee</th>
                            <th>Order #</th>
                            <th>Date</th>
                            <th>Time Range</th>
                            <th>Status</th>
                            <th>Remarks</th>
                            <th>Action</th>
                        </tr>
```

with:

```blade
                        <tr>
                            <th>Employee</th>
                            <th>Order #</th>
                            <th>Date</th>
                            <th>Time Range</th>
                            <th>Status</th>
                            <th>Filed By</th>
                            <th>Remarks</th>
                            <th>Action</th>
                        </tr>
```

Update the initial loading row's colspan from `7` to `8`:

```blade
                        <tbody id="review_list">
                        <tr><td colspan="8" class="ho-empty">Loading...</td></tr>
                        </tbody>
```

- [ ] **Step 2: Update JS rendering**

In `getFilingsForReview()`'s failure handler and in `renderReviewRows()`, update colspans and add the "Filed By" cell:

```javascript
            .catch(() => {
                $('#review_list').html('<tr><td colspan="8" class="ho-empty text-danger">Failed to load.</td></tr>');
                $('#reviewPagination').empty();
                $('#reviewSummary').text('');
            });
```

```javascript
        if (!filteredFilingsForReview.length) {
            $list.html('<tr><td colspan="8" class="ho-empty">No filings for review.</td></tr>');
            $('#reviewSummary').text('0 results');
            return;
        }
```

```javascript
        pageItems.forEach(it => {
            const canReview = String(it.status || '').toLowerCase() === 'pending';
            const filedByLabel = it.filed_by
                ? `<span class="ho-chip">${escapeHtml((it.filed_by_name || '').trim())}</span>`
                : '-';
            $list.append(`
                <tr>
                    <td>${escapeHtml((it.employee_name || '').replace(/\s+/g, ' ').trim())}</td>
                    <td><span class="ho-chip">#${escapeHtml(it.order_number || '')}</span></td>
                    <td>${formatDate(it.filed_date)}</td>
                    <td>${formatTimeRange(it.time_from, it.time_to)}</td>
                    <td>${statusBadge(it.status)}</td>
                    <td>${filedByLabel}</td>
                    <td>${escapeHtml(it.remarks || '-')}</td>
                    <td>
                        ${canReview ? `
                            <button type="button"  class="btn btn-action btn-edit me-1 mb-1" onclick="reviewFiling(${it.id}, 'approve')" title="Approve"><i class="bi bi-check2"></i></button>
                            <button type="button"  class="btn btn-action btn-delete mb-1" onclick="reviewFiling(${it.id}, 'reject')" title="Reject"><i class="bi bi-x"></i></button>
                        ` : '-'}
                    </td>
                </tr>
            `);
        });
```

- [ ] **Step 3: Sanity-check**

Run: `php artisan view:clear && php artisan test tests/Feature/HospitalOrderOnBehalfTest.php`
Expected: `Tests: 8 passed`.

- [ ] **Step 4: Commit**

```bash
git add resources/views/hospital_order_approval.blade.php
git commit -m "feat: show filed-by badge in the hospital order review queue"
```

---

### Task 13: Final regression check and manual smoke test

**Files:** none (verification only)

- [ ] **Step 1: Run the full set of new/touched automated tests**

Run: `php artisan test tests/Unit/OrgHierarchyServiceTest.php tests/Feature/HeadFileOnBehalfPermissionsTest.php tests/Feature/ManagedEmployeeControllerTest.php tests/Feature/PassSlipOnBehalfTest.php tests/Feature/PassSlipControllerOnBehalfTest.php tests/Feature/HospitalOrderOnBehalfTest.php tests/Feature/HospitalOrderControllerOnBehalfTest.php`
Expected: `Tests: 29 passed` (4 + 3 + 1 + 9 + 2 + 8 + 2 = 29 across the seven files; if the number differs, confirm every test in each file passed, none skipped).

- [ ] **Step 2: Run the pre-existing pass slip and hospital order regression surface**

Run: `php artisan test --filter=PassSlip`
Run: `php artisan test --filter=HospitalOrder`
Expected: all pass — confirms the `getPendingSlips()`/`updateStatus()` scoping refactor (Task 5) and the `fileForSelf()`/`file()` refactor (Task 9) didn't break existing self-filing/approval behavior.

- [ ] **Step 3: Confirm no stray temp files remain**

Run: `git status --short`
Expected: no untracked files outside what this plan created, no unexpected modifications.

- [ ] **Step 4: Manual smoke test**

Using a real Section Head test account (or the designated test employee, if elevated temporarily) against the running app:

1. Go to **Pass Slips**. Confirm a "File For" dropdown appears with "Myself" plus your managed employees. Pick an employee, fill in date/time/type, submit — confirm the success toast, and that the new row appears immediately in the personal list (if you're viewing as the employee) with status "Approved" and a "Filed by {Head}" badge.
2. Go to **Pass Slip Approvals**. Confirm the on-behalf filing you just made shows up in the queue with status "Approved" and no Approve/Reject buttons (since it's already resolved), and a "Filed By" column showing your name.
3. Repeat both checks for **Hospital Order Filings** (pick an existing hospital order, file for a managed employee) and **Hospital Order Filings Review**.
4. Log in as a plain employee (not a head) and confirm the "File For" dropdown does **not** appear on either page, and that self-filing still works exactly as before.
5. As the employee the filing was made for, confirm you cannot delete the on-behalf pass slip or hospital order filing (no delete button, since status is "Approved").
