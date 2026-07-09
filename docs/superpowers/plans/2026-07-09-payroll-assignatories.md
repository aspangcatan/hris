# Payroll Assignatories Settings Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace payroll's hardcoded 3-phase SH→DH→HR approval chain (with HR literally hardcoded to user id `15`) with an admin-configurable ordered list of named approvers ("assignatories"), settable from a new settings page.

**Architecture:** A new `payroll_assignatories` table holds the configurable ordered list (`order_no`, `label`, `user_id`, `is_active`). `payroll_approvals.role` (enum `SH`/`DH`/`HR`) becomes a free-text `label` column. `PayrollController::submitForApproval()` builds the chain from the active assignatories list instead of deriving SH/DH from org structure and hardcoding HR. Approval gating (`getUserPendingApproval()` / `getPendingApprovalMapForCurrentUser()`) is rewritten from per-role SQL to a generic "lowest `order_no` among pending rows for this payroll, with `approver_id` matching the current user" pattern (admin session bypasses the `approver_id` check, preserving existing admin-can-approve-anything behavior). Clean cutover — no in-flight `payroll_approvals` data needs preserving.

**Tech Stack:** Laravel 8, PHPUnit (`DatabaseTransactions` against the real shared `hris`/`tdh_user` MySQL databases — no separate test DB, see [[feedback-refreshdatabase-wipes-live-db]]), vanilla JS/fetch + Bootstrap modals (no new frontend framework, matching `payroll_tax_rules.blade.php`).

**Spec:** `docs/superpowers/specs/2026-07-09-payroll-assignatories-design.md`

**Important environment note:** `payroll_assignatories` is a *global settings table*, not scoped by a per-test unique code the way DTR test fixtures are. Since this is a shared live database (no separate test DB — see [[feedback-refreshdatabase-wipes-live-db]]), any test that needs a *specific* configured state (including "nothing configured") must snapshot the table's current contents in `setUp()`, mutate freely, and restore the snapshot in `tearDown()`. Task 5's test file does this via `snapshotAndClearAssignatories()` / `restoreAssignatories()`.

---

### Task 1: Migrations

**Files:**
- Create: `database/migrations/2026_07_09_090000_create_payroll_assignatories_table.php`
- Create: `database/migrations/2026_07_09_090100_rename_payroll_approvals_role_to_label.php`

- [ ] **Step 1: Write the new-table migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payroll_assignatories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedTinyInteger('order_no');
            $table->string('label');
            $table->unsignedInteger('user_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('payroll_assignatories');
    }
};
```

- [ ] **Step 2: Write the `payroll_approvals.role` → `label` rename migration**

`role` is currently `enum('SH','DH','HR')`. Renaming an enum column via Laravel's Schema builder (`renameColumn` + `change`) is unreliable with Doctrine DBAL's enum introspection, so this uses a raw `ALTER TABLE ... CHANGE` statement instead — MySQL preserves existing row data across the rename.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE payroll_approvals CHANGE role label VARCHAR(255) NOT NULL");
    }

    public function down()
    {
        DB::statement("ALTER TABLE payroll_approvals CHANGE label role ENUM('SH','DH','HR') NOT NULL");
    }
};
```

- [ ] **Step 3: Run the migrations**

Run: `php artisan migrate --force`
Expected: both `2026_07_09_090000_create_payroll_assignatories_table` and `2026_07_09_090100_rename_payroll_approvals_role_to_label` show `Migrated:`.

Verify the column rename landed correctly:
Run: `php artisan tinker --execute="echo DB::select(\"SHOW COLUMNS FROM payroll_approvals WHERE Field='label'\")[0]->Type;"`
Expected: `varchar(255)`

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_07_09_090000_create_payroll_assignatories_table.php database/migrations/2026_07_09_090100_rename_payroll_approvals_role_to_label.php
git commit -m "feat: add payroll_assignatories table, rename payroll_approvals.role to label"
```

---

### Task 2: Settings backend — list + save endpoints

**Files:**
- Modify: `app/Http/Controllers/PayrollController.php` (add two methods after `savePayrollTaxRules()`, i.e. after line 394)
- Modify: `routes/web.php` (add routes near the existing `payroll-tax-rules` routes, after line 286)
- Create: `tests/Feature/PayrollAssignatoryTest.php`

- [ ] **Step 1: Write the test file**

```php
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
```

- [ ] **Step 2: Run the tests to confirm they fail (routes/methods don't exist yet)**

Run: `php artisan test --filter=PayrollAssignatoryTest`
Expected: failures (404s or method-not-found) — nothing implemented yet.

- [ ] **Step 3: Add the routes**

In `routes/web.php`, immediately after line 286 (`Route::post('/payroll-tax-rules', ...)`), add:

```php
    Route::get('/payroll-assignatories', [\App\Http\Controllers\PayrollController::class, 'getPayrollAssignatories'])->name('getPayrollAssignatories');
    Route::post('/payroll-assignatories', [\App\Http\Controllers\PayrollController::class, 'savePayrollAssignatories'])->name('savePayrollAssignatories');
```

- [ ] **Step 4: Add the controller methods**

In `app/Http/Controllers/PayrollController.php`, immediately after `savePayrollTaxRules()` closes (after line 394, before `getCutoffSummaries()`), add:

```php
    public function getPayrollAssignatories()
    {
        try {
            $assignatories = DB::table('payroll_assignatories as pa')
                ->leftJoin('tdh_user.users as u', 'u.id', '=', 'pa.user_id')
                ->where('pa.is_active', 1)
                ->orderBy('pa.order_no')
                ->select('pa.id', 'pa.order_no', 'pa.label', 'pa.user_id', 'pa.is_active', 'u.fname', 'u.lname')
                ->get();

            return response()->json(['success' => true, 'data' => $assignatories]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function savePayrollAssignatories(Request $request)
    {
        try {
            $rows = $request->input('assignatories', []);
            if (!is_array($rows)) {
                return response()->json(['success' => false, 'message' => 'Invalid payload.'], 422);
            }

            $normalized = [];
            $errors = [];
            foreach ($rows as $index => $row) {
                $label = trim((string) ($row['label'] ?? ''));
                $userId = isset($row['user_id']) ? (int) $row['user_id'] : null;

                if ($label === '') {
                    $errors[] = 'Row ' . ($index + 1) . ': label is required.';
                    continue;
                }
                if (!$userId || !DB::table('tdh_user.users')->where('id', $userId)->exists()) {
                    $errors[] = 'Row ' . ($index + 1) . ': a valid assigned user is required.';
                    continue;
                }

                $normalized[] = [
                    'id' => !empty($row['id']) ? (int) $row['id'] : null,
                    'label' => $label,
                    'user_id' => $userId,
                    'order_no' => isset($row['order_no']) ? (int) $row['order_no'] : 0,
                    'is_active' => array_key_exists('is_active', $row) ? (bool) $row['is_active'] : true,
                ];
            }

            if (!empty($errors)) {
                return response()->json(['success' => false, 'message' => implode(' ', $errors)], 422);
            }

            foreach ($normalized as $row) {
                $payload = [
                    'label' => $row['label'],
                    'user_id' => $row['user_id'],
                    'order_no' => $row['order_no'],
                    'is_active' => $row['is_active'] ? 1 : 0,
                    'updated_at' => now(),
                ];

                if (!empty($row['id'])) {
                    DB::table('payroll_assignatories')->where('id', $row['id'])->update($payload);
                } else {
                    $payload['created_at'] = now();
                    DB::table('payroll_assignatories')->insert($payload);
                }
            }

            return response()->json(['success' => true, 'message' => 'Assignatories saved successfully.']);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }
```

- [ ] **Step 5: Run the tests again**

Run: `php artisan test --filter=PayrollAssignatoryTest`
Expected: `5 passed`

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/PayrollController.php routes/web.php tests/Feature/PayrollAssignatoryTest.php
git commit -m "feat: add payroll assignatories settings list/save endpoints"
```

---

### Task 3: Search-users endpoint for the assignee picker

**Files:**
- Modify: `app/Http/Controllers/PayrollController.php` (add one method after `savePayrollAssignatories()`)
- Modify: `routes/web.php` (add one route after the two added in Task 2)
- Modify: `tests/Feature/PayrollAssignatoryTest.php` (add one test method)

- [ ] **Step 1: Add the test**

Append to `tests/Feature/PayrollAssignatoryTest.php`, inside the class body:

```php
    /** @test */
    public function search_users_finds_by_last_name(): void
    {
        $this->actAsAdmin();

        $response = $this->withoutMiddleware()->getJson('/payroll-assignatories/search-users?q=Tester');
        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertTrue(collect($data)->contains('id', $this->approverUserId));
    }

    /** @test */
    public function search_users_returns_empty_for_blank_query(): void
    {
        $this->actAsAdmin();

        $response = $this->withoutMiddleware()->getJson('/payroll-assignatories/search-users?q=');
        $response->assertOk();
        $response->assertJson(['success' => true, 'data' => []]);
    }
```

- [ ] **Step 2: Run to confirm failure**

Run: `php artisan test --filter=PayrollAssignatoryTest`
Expected: the two new tests fail (route doesn't exist), the five from Task 2 still pass.

- [ ] **Step 3: Add the route**

In `routes/web.php`, immediately after the `savePayrollAssignatories` route added in Task 2, add:

```php
    Route::get('/payroll-assignatories/search-users', [\App\Http\Controllers\PayrollController::class, 'searchPayrollAssignatoryUsers'])->name('searchPayrollAssignatoryUsers');
```

- [ ] **Step 4: Add the controller method**

In `app/Http/Controllers/PayrollController.php`, immediately after `savePayrollAssignatories()` closes, add:

```php
    public function searchPayrollAssignatoryUsers(Request $request)
    {
        try {
            $search = trim((string) $request->query('q', ''));
            if ($search === '') {
                return response()->json(['success' => true, 'data' => []]);
            }

            $users = DB::table('tdh_user.users as u')
                ->select('u.id', 'u.fname', 'u.lname')
                ->where(function ($q) use ($search) {
                    $q->where('u.fname', 'like', "%{$search}%")
                        ->orWhere('u.lname', 'like', "%{$search}%");
                })
                ->orderBy('u.lname')
                ->orderBy('u.fname')
                ->limit(10)
                ->get();

            return response()->json(['success' => true, 'data' => $users]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }
```

- [ ] **Step 5: Run again**

Run: `php artisan test --filter=PayrollAssignatoryTest`
Expected: `7 passed`

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/PayrollController.php routes/web.php tests/Feature/PayrollAssignatoryTest.php
git commit -m "feat: add user search endpoint for payroll assignatories picker"
```

---

### Task 4: Settings UI

**Files:**
- Create: `resources/views/settings/payroll_assignatories.blade.php`
- Modify: `routes/web.php` (add the page route)
- Modify: `resources/views/layouts/sidebar.blade.php` (add nav link, after line 273)

- [ ] **Step 1: Add the page route**

In `routes/web.php`, immediately after the `savePayrollTaxRules` route block (i.e. right before the `/payroll-assignatories` API routes added in Task 2 — place the view route directly above them for readability), add:

```php
    Route::get('/payroll-assignatories/manage', function () {
        return view('settings.payroll_assignatories');
    })->name('payroll_assignatories')->middleware('permission:payroll.settings');
```

- [ ] **Step 2: Write the Blade view**

```php
@extends('master')
@section('content')
    <style>
        .assignatory-board {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
        }

        .assignatory-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }

        .assignatory-head h4 {
            margin: 0;
            font-weight: 700;
            color: #0f172a;
        }

        .assignatory-head span {
            font-size: 0.9rem;
            color: #64748b;
        }

        .assignatory-table-shell {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
        }

        .assignatory-table-shell table {
            margin-bottom: 0;
        }

        .assignatory-table-shell table thead th {
            background: #f8fafc;
            color: #0f172a;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .assignatory-table-shell table tbody td {
            color: #1e293b;
            font-weight: 500;
            vertical-align: middle;
        }

        .payroll-modal-theme .modal-content {
            border: 0;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 24px 50px rgba(15, 23, 42, 0.2);
        }

        .payroll-modal-theme .modal-header {
            background: linear-gradient(135deg, #0f766e, #0ea5a4);
            color: #fff;
            border: 0;
        }

        .payroll-modal-theme .modal-title {
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .payroll-modal-theme .btn-close {
            filter: invert(1);
            opacity: 0.9;
        }

        .payroll-modal-theme .modal-body {
            background: #f8fafc;
        }

        #assignatory_user_results {
            position: absolute;
            z-index: 20;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            width: 100%;
            max-height: 220px;
            overflow-y: auto;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.12);
            display: none;
        }

        #assignatory_user_results .result-row {
            padding: 0.5rem 0.75rem;
            cursor: pointer;
        }

        #assignatory_user_results .result-row:hover {
            background: #f1f5f9;
        }
    </style>

    <div class="assignatory-board">
        <div class="assignatory-head">
            <div>
                <h4>Payroll Assignatories</h4>
                <span>Configure how many approval phases a payroll goes through after submission, and who approves each phase.</span>
            </div>
            <button class="btn btn-success btn-edit" id="btn_add_assignatory">
                <i class="bi bi-plus-circle me-1"></i>
                Add Phase
            </button>
        </div>

        <div class="table-responsive assignatory-table-shell">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th style="width: 10%;">Order</th>
                    <th style="width: 25%;">Label</th>
                    <th style="width: 25%;">Assigned To</th>
                    <th style="width: 10%;">Active</th>
                    <th style="width: 10%;"></th>
                </tr>
                </thead>
                <tbody id="assignatories_body"></tbody>
            </table>
        </div>
    </div>

    <div class="modal fade payroll-modal-theme" id="assignatory_modal" tabindex="-1" aria-labelledby="assignatoryModalLabel"
         aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header sticky-top">
                    <h4 class="modal-title text-white" id="assignatoryModalLabel">Add Phase</h4>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto; padding: 1.5rem;">
                    <input type="hidden" id="assignatory_id">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Order</label>
                            <input type="number" class="form-control" id="assignatory_order" value="1" min="1">
                        </div>
                        <div class="col-md-9">
                            <label class="form-label">Label</label>
                            <input type="text" class="form-control" id="assignatory_label" placeholder="e.g. Section Head">
                        </div>
                        <div class="col-md-12 position-relative">
                            <label class="form-label">Assigned User</label>
                            <input type="text" class="form-control" id="assignatory_user_search" placeholder="Search by name..." autocomplete="off">
                            <input type="hidden" id="assignatory_user_id">
                            <div id="assignatory_user_results"></div>
                            <small class="text-muted" id="assignatory_user_selected"></small>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="assignatory_active" checked>
                                <label class="form-check-label" for="assignatory_active">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-view" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success btn-edit" id="btn_save_assignatory">Save Phase</button>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script>
        let assignatories = [];
        let userSearchTimeout = null;

        $(document).ready(function () {
            loadAssignatories();

            $("#btn_add_assignatory").click(function () {
                openAssignatoryModal();
            });

            $("#btn_save_assignatory").click(function () {
                saveAssignatory();
            });

            $(document).on("click", ".btn-edit-assignatory", function () {
                const id = $(this).data("id");
                const row = assignatories.find(item => item.id === id);
                if (!row) {
                    toastr.error("Assignatory not found.", "Error");
                    return;
                }
                openAssignatoryModal(row);
            });

            $(document).on("click", ".btn-disable-assignatory", function () {
                const id = $(this).data("id");
                const row = assignatories.find(item => item.id === id);
                if (!row) {
                    return;
                }
                submitAssignatories([{
                    id: row.id,
                    label: row.label,
                    user_id: row.user_id,
                    order_no: row.order_no,
                    is_active: false
                }]);
            });

            $("#assignatory_user_search").on("input", function () {
                clearTimeout(userSearchTimeout);
                const query = $(this).val().trim();
                $("#assignatory_user_id").val("");
                if (!query) {
                    $("#assignatory_user_results").hide().empty();
                    return;
                }
                userSearchTimeout = setTimeout(() => searchAssignatoryUsers(query), 300);
            });

            $(document).on("click", "#assignatory_user_results .result-row", function () {
                $("#assignatory_user_id").val($(this).data("id"));
                $("#assignatory_user_search").val($(this).text());
                $("#assignatory_user_results").hide().empty();
            });
        });

        function loadAssignatories() {
            fetch('{{ route('getPayrollAssignatories') }}')
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        return;
                    }
                    assignatories = data.data || [];
                    renderAssignatories();
                })
                .catch(err => console.error(err));
        }

        function renderAssignatories() {
            const $body = $("#assignatories_body");
            $body.empty();

            if (!assignatories.length) {
                $body.append("<tr><td colspan='5' class='text-muted text-center py-3'>No assignatories configured.</td></tr>");
                return;
            }

            assignatories.forEach(row => {
                const assignedName = row.fname ? `${row.lname}, ${row.fname}` : `User #${row.user_id}`;
                const active = row.is_active ? "Yes" : "No";

                const tr = `
                    <tr>
                        <td>${row.order_no}</td>
                        <td>${row.label}</td>
                        <td>${assignedName}</td>
                        <td>${active}</td>
                        <td class="text-end">
                            <button type="button" class="btn btn-action btn-edit btn-edit-assignatory" data-id="${row.id}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-action btn-delete btn-disable-assignatory" data-id="${row.id}" title="Deactivate">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </td>
                    </tr>`;
                $body.append(tr);
            });
        }

        function openAssignatoryModal(row = null) {
            $("#assignatoryModalLabel").text(row ? "Edit Phase" : "Add Phase");
            $("#assignatory_id").val(row ? row.id : "");
            $("#assignatory_order").val(row ? row.order_no : (assignatories.length + 1));
            $("#assignatory_label").val(row ? row.label : "");
            $("#assignatory_user_id").val(row ? row.user_id : "");
            $("#assignatory_user_search").val(row && row.fname ? `${row.lname}, ${row.fname}` : "");
            $("#assignatory_user_selected").text("");
            $("#assignatory_active").prop("checked", row ? !!row.is_active : true);
            $("#assignatory_user_results").hide().empty();
            $("#assignatory_modal").modal("show");
        }

        function searchAssignatoryUsers(query) {
            fetch('{{ route('searchPayrollAssignatoryUsers') }}?q=' + encodeURIComponent(query))
                .then(res => res.json())
                .then(data => {
                    const $results = $("#assignatory_user_results");
                    $results.empty();
                    if (!data.success || !data.data.length) {
                        $results.hide();
                        return;
                    }
                    data.data.forEach(user => {
                        $results.append(`<div class="result-row" data-id="${user.id}">${user.lname}, ${user.fname}</div>`);
                    });
                    $results.show();
                })
                .catch(err => console.error(err));
        }

        function saveAssignatory() {
            const id = $("#assignatory_id").val();
            const orderNo = parseInt($("#assignatory_order").val() || "1", 10);
            const label = ($("#assignatory_label").val() || "").trim();
            const userId = $("#assignatory_user_id").val();
            const active = $("#assignatory_active").is(":checked");

            if (!label) {
                toastr.error("Label is required.", "Validation");
                return;
            }
            if (!userId) {
                toastr.error("Please select an assigned user from the search results.", "Validation");
                return;
            }

            submitAssignatories([{
                id: id || null,
                label: label,
                user_id: parseInt(userId, 10),
                order_no: orderNo,
                is_active: active
            }], true);
        }

        function submitAssignatories(rows, closeModal = false) {
            fetch('{{ route('savePayrollAssignatories') }}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify({assignatories: rows})
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        toastr.success(data.message || "Assignatory saved.", "Information");
                        if (closeModal) {
                            $("#assignatory_modal").modal("hide");
                        }
                        loadAssignatories();
                    } else {
                        toastr.error(data.message || "Failed to save assignatory.", "Error");
                    }
                })
                .catch(err => {
                    console.error(err);
                    toastr.error("Failed to save assignatory.", "Error");
                });
        }
    </script>
@stop
```

- [ ] **Step 3: Add the sidebar nav link**

In `resources/views/layouts/sidebar.blade.php`, immediately after the `@permission('payroll.settings')` block for `payroll_tax_rules` closes (after line 273), add:

```php
            @permission('payroll.settings')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('payroll_assignatories') ? '' : 'collapsed' }}"
                       href="{{ route('payroll_assignatories') }}">
                        <i class="bi bi-person-check"></i>
                        <span>Payroll Assignatories</span>
                    </a>
                </li>
            @endpermission
```

- [ ] **Step 4: Verify the route list registers correctly**

Run: `php artisan route:list --name=payroll_assignatories`
Expected: shows `GET|HEAD payroll-assignatories/manage` bound to the closure, plus the `getPayrollAssignatories`/`savePayrollAssignatories`/`searchPayrollAssignatoryUsers` routes from Tasks 2–3.

- [ ] **Step 5: Commit**

```bash
git add resources/views/settings/payroll_assignatories.blade.php routes/web.php resources/views/layouts/sidebar.blade.php
git commit -m "feat: add payroll assignatories settings page"
```

---

### Task 5: Rewrite `submitForApproval()` to build the chain from `payroll_assignatories`

**Files:**
- Modify: `app/Http/Controllers/PayrollController.php:790-877` (current `submitForApproval()` — line numbers as of the end of Task 3; re-locate by function name if they've drifted)
- Create: `tests/Feature/PayrollApprovalChainTest.php`

- [ ] **Step 1: Write the test file**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PayrollApprovalChainTest extends TestCase
{
    use DatabaseTransactions;

    private int $preparerId = 9600;
    private int $approver1Id = 9601;
    private int $approver2Id = 9602;
    private int $approver3Id = 9603;
    private array $assignatorySnapshot = [];
    private ?int $payrollId = null;
    private ?int $masterId = null;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);

        $this->assignatorySnapshot = DB::table('payroll_assignatories')->get()->map(fn($row) => (array) $row)->all();
        DB::table('payroll_assignatories')->delete();

        foreach ([$this->preparerId, $this->approver1Id, $this->approver2Id, $this->approver3Id] as $id) {
            DB::table('tdh_user.users')->where('id', $id)->delete();
            DB::table('tdh_user.users')->insert([
                'id' => $id,
                'fname' => 'Approver',
                'lname' => 'User' . $id,
                'username' => 'approveruser' . $id,
                'password' => 'x',
                'division' => 2,
                'section' => 21,
                'designation' => 52,
            ]);
        }
    }

    protected function tearDown(): void
    {
        if ($this->payrollId) {
            DB::table('payroll_approvals')->where('payroll_id', $this->payrollId)->delete();
            DB::table('payroll_employee_components')->whereIn(
                'payroll_employee_id',
                DB::table('payroll_employees')->where('payroll_id', $this->payrollId)->pluck('id')
            )->delete();
            DB::table('payroll_employees')->where('payroll_id', $this->payrollId)->delete();
            DB::table('payrolls')->where('id', $this->payrollId)->delete();
        }
        if ($this->masterId) {
            DB::table('hris.cutoff_summaries')->where('cutoff_id', $this->masterId)->delete();
            DB::table('hris.cutoff_summaries_master')->where('id', $this->masterId)->delete();
        }
        DB::table('payroll_assignatories')->delete();
        if (!empty($this->assignatorySnapshot)) {
            DB::table('payroll_assignatories')->insert($this->assignatorySnapshot);
        }
        foreach ([$this->preparerId, $this->approver1Id, $this->approver2Id, $this->approver3Id] as $id) {
            DB::table('tdh_user.users')->where('id', $id)->delete();
        }
        parent::tearDown();
    }

    private function actAs(int $userId, string $accessRights = 'admin'): void
    {
        Auth::shouldReceive('id')->andReturn($userId);
        Auth::shouldReceive('check')->andReturn(true);
        // Stubbed defensively even though the rewritten submitForApproval() no
        // longer calls Auth::user() — an unstubbed call on a Mockery-replaced
        // facade throws instead of returning null (see PayrollCutoffTest's
        // actAsUser(), which does the same for this reason).
        Auth::shouldReceive('user')->andReturn(null);
        session(['access_rights' => $accessRights]);
    }

    private function seedAssignatories(array $rows): void
    {
        foreach ($rows as $row) {
            DB::table('payroll_assignatories')->insert(array_merge($row, [
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function seedComputedPayroll(): int
    {
        DB::table('hris.cutoff_summaries_master')
            ->where('period_start', '2027-02-01')
            ->where('period_end', '2027-02-15')
            ->where('title', 'Assignatory Test Cutoff')
            ->delete();
        $this->masterId = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title' => 'Assignatory Test Cutoff',
            'period_start' => '2027-02-01',
            'period_end' => '2027-02-15',
            'generated_by' => $this->preparerId,
            'generated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(\App\Services\PayrollServices::class)->generateCutoffSummary(
            $this->preparerId,
            '2027-02-01',
            '2027-02-15',
            'Assignatory Test Cutoff',
            $this->preparerId
        );

        $this->actAs($this->preparerId, 'admin');

        $saveResponse = $this->withoutMiddleware()->postJson('/save_payroll', [
            'title' => 'Assignatory Test Payroll',
            'cutoff_id' => $this->masterId,
            'payroll_type' => 'Regular',
            'rate_type' => 'Non Pro-rated',
        ]);
        $saveResponse->assertOk();

        $payroll = DB::table('payrolls')->where('title', 'Assignatory Test Payroll')->latest('id')->first();
        $this->payrollId = $payroll->id;

        $this->withoutMiddleware()->postJson('/save_payroll_employee', [
            'payroll_id' => $this->payrollId,
            'employees' => [['userid' => $this->preparerId]],
        ])->assertOk();

        return $this->payrollId;
    }

    /** @test */
    public function submit_for_approval_fails_when_no_assignatories_configured(): void
    {
        $payrollId = $this->seedComputedPayroll();

        $response = $this->withoutMiddleware()->postJson('/payroll/submit_for_approval', [
            'payroll_id' => $payrollId,
        ]);

        $response->assertStatus(422);
        $this->assertEquals(0, DB::table('payroll_approvals')->where('payroll_id', $payrollId)->count());
    }

    /** @test */
    public function submit_for_approval_creates_one_row_per_configured_assignatory_in_order(): void
    {
        $this->seedAssignatories([
            ['order_no' => 2, 'label' => 'Division Head', 'user_id' => $this->approver2Id],
            ['order_no' => 1, 'label' => 'Section Head', 'user_id' => $this->approver1Id],
            ['order_no' => 3, 'label' => 'HR', 'user_id' => $this->approver3Id],
        ]);
        $payrollId = $this->seedComputedPayroll();

        $this->withoutMiddleware()->postJson('/payroll/submit_for_approval', [
            'payroll_id' => $payrollId,
        ])->assertOk();

        $approvals = DB::table('payroll_approvals')->where('payroll_id', $payrollId)->orderBy('order_no')->get();
        $this->assertCount(3, $approvals);
        $this->assertEquals(['Section Head', 'Division Head', 'HR'], $approvals->pluck('label')->toArray());
        $this->assertEquals([$this->approver1Id, $this->approver2Id, $this->approver3Id], $approvals->pluck('approver_id')->map(fn($v) => (int) $v)->toArray());
        $this->assertEquals(['pending', 'pending', 'pending'], $approvals->pluck('status')->toArray());
    }
}
```

- [ ] **Step 2: Run to confirm the empty-chain test passes already (422 already happens today, just for the wrong reason) and the ordering test fails**

Run: `php artisan test --filter=PayrollApprovalChainTest`
Expected: `submit_for_approval_fails_when_no_assignatories_configured` passes (today's code fails submission for a *different* reason — missing section/division — which is fine, it's still a 422). `submit_for_approval_creates_one_row_per_configured_assignatory_in_order` fails, since today's code ignores `payroll_assignatories` entirely and always creates the hardcoded SH/DH/HR rows.

- [ ] **Step 3: Rewrite `submitForApproval()`**

Replace the current body of `submitForApproval()` in `app/Http/Controllers/PayrollController.php` (the whole method, currently lines 790-877) with:

```php
    public function submitForApproval(Request $request)
    {
        try {
            $validated = $request->validate([
                'payroll_id' => 'required|integer|exists:payrolls,id',
            ]);

            $payroll = DB::table('payrolls')->where('id', $validated['payroll_id'])->first();
            if (!$payroll) {
                return response()->json(['error' => 'Payroll not found.'], 404);
            }

            $isAdmin = $this->permissions->can(Auth::id(), 'payroll.manage');
            if ((int) $payroll->prepared_by !== (int) Auth::id() && !$isAdmin) {
                return response()->json(['error' => 'Only the preparer can submit this payroll for approval.'], 403);
            }

            $status = strtolower((string) ($payroll->status ?? 'draft'));
            if (!in_array($status, ['draft', 'computed', 'rejected'], true)) {
                return response()->json(['error' => 'Payroll cannot be submitted in its current status.'], 422);
            }

            if ($this->payrollServices->getPayrollEmployeeCount($validated['payroll_id']) === 0) {
                return response()->json(['error' => 'Please compute payroll employees before submitting for approval.'], 422);
            }

            $assignatories = DB::table('payroll_assignatories')
                ->where('is_active', 1)
                ->orderBy('order_no')
                ->get(['label', 'user_id']);

            if ($assignatories->isEmpty()) {
                return response()->json(['error' => 'No approval assignatories are configured. Configure them in Payroll Assignatories settings before submitting.'], 422);
            }

            DB::transaction(function () use ($validated, $assignatories) {
                DB::table('payroll_approvals')->where('payroll_id', $validated['payroll_id'])->delete();

                $rows = [];
                foreach ($assignatories->values() as $index => $assignatory) {
                    $rows[] = [
                        'payroll_id' => $validated['payroll_id'],
                        'approver_id' => $assignatory->user_id,
                        'label' => $assignatory->label,
                        'status' => 'pending',
                        'order_no' => $index + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                DB::table('payroll_approvals')->insert($rows);

                DB::table('payrolls')
                    ->where('id', $validated['payroll_id'])
                    ->update([
                        'status' => 'for_approval',
                        'reviewed_by' => null,
                        'approved_at' => null,
                        'released_at' => null,
                        'updated_at' => now(),
                    ]);
            });

            return response()->json(['message' => 'Payroll submitted for approval.']);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }
```

Note this removes the `$user`/`$sectionId`/`$divisionId` derivation entirely — the preparer's own org structure no longer has anything to do with who approves.

- [ ] **Step 4: Run again**

Run: `php artisan test --filter=PayrollApprovalChainTest`
Expected: `2 passed`

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/PayrollController.php tests/Feature/PayrollApprovalChainTest.php
git commit -m "refactor: submitForApproval builds chain from payroll_assignatories"
```

---

### Task 6: Rewrite approval gating to a generic order-based check

**Files:**
- Modify: `app/Http/Controllers/PayrollController.php` (`getPendingApprovalMapForCurrentUser()` and `getUserPendingApproval()` — line numbers will have shifted after Task 5's edit; re-locate by function name)
- Modify: `app/Http/Controllers/PayrollController.php:658` (`getPayroll()`'s `pending_approval_role` line — re-locate by content, it reads `$item->pending_approval_role = $item->can_approve ? $pendingApprovalMap[$item->id]->role : null;`)
- Modify: `tests/Feature/PayrollApprovalChainTest.php` (add test methods)

- [ ] **Step 1: Add the tests**

Append to `tests/Feature/PayrollApprovalChainTest.php`, inside the class body:

```php
    /** @test */
    public function later_phase_approver_cannot_act_before_an_earlier_phase_is_approved(): void
    {
        $this->seedAssignatories([
            ['order_no' => 1, 'label' => 'Section Head', 'user_id' => $this->approver1Id],
            ['order_no' => 2, 'label' => 'Division Head', 'user_id' => $this->approver2Id],
        ]);
        $payrollId = $this->seedComputedPayroll();
        $this->withoutMiddleware()->postJson('/payroll/submit_for_approval', ['payroll_id' => $payrollId])->assertOk();

        $this->actAs($this->approver2Id, 'standard');

        $response = $this->withoutMiddleware()->postJson('/payroll/approval_action', [
            'payroll_id' => $payrollId,
            'status' => 'approved',
        ]);

        $response->assertStatus(403);
        $this->assertEquals(
            'pending',
            DB::table('payroll_approvals')->where('payroll_id', $payrollId)->where('order_no', 2)->value('status')
        );
    }

    /** @test */
    public function each_named_approver_can_act_only_on_their_own_step_in_sequence(): void
    {
        $this->seedAssignatories([
            ['order_no' => 1, 'label' => 'Section Head', 'user_id' => $this->approver1Id],
            ['order_no' => 2, 'label' => 'Division Head', 'user_id' => $this->approver2Id],
        ]);
        $payrollId = $this->seedComputedPayroll();
        $this->withoutMiddleware()->postJson('/payroll/submit_for_approval', ['payroll_id' => $payrollId])->assertOk();

        $this->actAs($this->approver1Id, 'standard');
        $this->withoutMiddleware()->postJson('/payroll/approval_action', [
            'payroll_id' => $payrollId,
            'status' => 'approved',
        ])->assertOk();

        $this->actAs($this->approver2Id, 'standard');
        $this->withoutMiddleware()->postJson('/payroll/approval_action', [
            'payroll_id' => $payrollId,
            'status' => 'approved',
        ])->assertOk();

        $payroll = DB::table('payrolls')->where('id', $payrollId)->first();
        $this->assertEquals('approved', strtolower($payroll->status));
    }

    /** @test */
    public function rejection_cascades_to_remaining_pending_steps(): void
    {
        $this->seedAssignatories([
            ['order_no' => 1, 'label' => 'Section Head', 'user_id' => $this->approver1Id],
            ['order_no' => 2, 'label' => 'Division Head', 'user_id' => $this->approver2Id],
        ]);
        $payrollId = $this->seedComputedPayroll();
        $this->withoutMiddleware()->postJson('/payroll/submit_for_approval', ['payroll_id' => $payrollId])->assertOk();

        $this->actAs($this->approver1Id, 'standard');
        $this->withoutMiddleware()->postJson('/payroll/approval_action', [
            'payroll_id' => $payrollId,
            'status' => 'rejected',
            'remarks' => 'Numbers look off.',
        ])->assertOk();

        $statuses = DB::table('payroll_approvals')->where('payroll_id', $payrollId)->orderBy('order_no')->pluck('status');
        $this->assertEquals(['rejected', 'rejected'], $statuses->toArray());

        $payroll = DB::table('payrolls')->where('id', $payrollId)->first();
        $this->assertEquals('rejected', strtolower($payroll->status));
    }

    /** @test */
    public function admin_session_can_approve_any_step_regardless_of_assigned_approver(): void
    {
        $this->seedAssignatories([
            ['order_no' => 1, 'label' => 'Section Head', 'user_id' => $this->approver1Id],
        ]);
        $payrollId = $this->seedComputedPayroll();
        $this->withoutMiddleware()->postJson('/payroll/submit_for_approval', ['payroll_id' => $payrollId])->assertOk();

        // Preparer is acting as admin (set up in seedComputedPayroll), not approver1Id.
        $this->withoutMiddleware()->postJson('/payroll/approval_action', [
            'payroll_id' => $payrollId,
            'status' => 'approved',
        ])->assertOk();

        $payroll = DB::table('payrolls')->where('id', $payrollId)->first();
        $this->assertEquals('approved', strtolower($payroll->status));
    }
```

- [ ] **Step 2: Run to confirm failures**

Run: `php artisan test --filter=PayrollApprovalChainTest`
Expected: the four new tests fail — today's gating logic keys off `session('access_rights')` being literally `SH`/`DH`/`HR`/`ADMIN` and matching `approver_id` against `user->section`/`user->division`/`15`, none of which lines up with a named `approver_id` and a `standard` access-rights session.

- [ ] **Step 3: Rewrite the two gating methods**

Replace `getPendingApprovalMapForCurrentUser()` (currently starting at line 1156) entirely with:

```php
    private function getPendingApprovalMapForCurrentUser()
    {
        $accessRight = strtoupper((string) session('access_rights'));
        $userId = Auth::id();

        $query = DB::table('payroll_approvals as pa')
            ->where('pa.status', 'pending')
            ->whereRaw('pa.order_no = (SELECT MIN(px.order_no) FROM payroll_approvals as px WHERE px.payroll_id = pa.payroll_id AND px.status = ?)', ['pending'])
            ->select('pa.id', 'pa.payroll_id', 'pa.label', 'pa.order_no');

        if ($accessRight !== 'ADMIN') {
            $query->where('pa.approver_id', $userId);
        }

        return $query->get()->keyBy('payroll_id')->toArray();
    }
```

And replace `getUserPendingApproval($payrollId)` (immediately following it) entirely with:

```php
    private function getUserPendingApproval($payrollId)
    {
        $accessRight = strtoupper((string) session('access_rights'));
        $userId = Auth::id();

        $query = DB::table('payroll_approvals as pa')
            ->where('pa.payroll_id', $payrollId)
            ->where('pa.status', 'pending')
            ->whereRaw('pa.order_no = (SELECT MIN(px.order_no) FROM payroll_approvals as px WHERE px.payroll_id = pa.payroll_id AND px.status = ?)', ['pending'])
            ->select('pa.*');

        if ($accessRight !== 'ADMIN') {
            $query->where('pa.approver_id', $userId);
        }

        return $query->first();
    }
```

This deletes all the `SH`/`DH`/`HR`/`whereExists` branching — the "lowest pending `order_no` for this payroll" subquery *is* the sequential gate, and it works for any number of configured phases. The `ADMIN` session bypass is preserved so an admin can still act on any step, matching today's behavior.

- [ ] **Step 4: Update `getPayroll()`'s label reference**

In `getPayroll()`, find the line `$item->pending_approval_role = $item->can_approve ? $pendingApprovalMap[$item->id]->role : null;` and replace it with:

```php
                $item->pending_approval_label = $item->can_approve ? $pendingApprovalMap[$item->id]->label : null;
```

- [ ] **Step 5: Run again**

Run: `php artisan test --filter=PayrollApprovalChainTest`
Expected: `6 passed`

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/PayrollController.php tests/Feature/PayrollApprovalChainTest.php
git commit -m "refactor: generic order-based approval gating, drop hardcoded SH/DH/HR roles"
```

---

### Task 7: Update `payroll.blade.php`, fix the existing lifecycle test, full suite verification

**Files:**
- Modify: `resources/views/payroll.blade.php:704-705`
- Modify: `tests/Feature/PayrollCutoffTest.php` (rewrite `full_payroll_lifecycle_moves_through_approval_chain_to_released` and `actAsAdminWithOrg()`)

- [ ] **Step 1: Update `payroll.blade.php`**

In `resources/views/payroll.blade.php`, replace lines 704-705:

```js
                            actions.push(`<li><a class="dropdown-item text-success" href="#" onclick="approvePayroll(${item.id})">Approve (${item.pending_approval_role})</a></li>`);
                            actions.push(`<li><a class="dropdown-item text-danger" href="#" onclick="rejectPayroll(${item.id})">Reject (${item.pending_approval_role})</a></li>`);
```

with:

```js
                            actions.push(`<li><a class="dropdown-item text-success" href="#" onclick="approvePayroll(${item.id})">Approve (${item.pending_approval_label})</a></li>`);
                            actions.push(`<li><a class="dropdown-item text-danger" href="#" onclick="rejectPayroll(${item.id})">Reject (${item.pending_approval_label})</a></li>`);
```

- [ ] **Step 2: Run the full DTR/Payroll suites to see the expected regression**

Run: `php artisan test --filter=Payroll`
Expected: `full_payroll_lifecycle_moves_through_approval_chain_to_released` fails — it still asserts against the hardcoded `['SH', 'DH', 'HR']` role chain and relies on `actAsAdminWithOrg()`'s `section`/`division` mock, neither of which the new code reads anymore.

- [ ] **Step 3: Rewrite `actAsAdminWithOrg()` and the lifecycle test**

In `tests/Feature/PayrollCutoffTest.php`, replace the `actAsAdminWithOrg()` method (currently lines 253-263) with:

```php
    private function actAsAdminWithOrg(): void
    {
        Auth::shouldReceive('id')->andReturn($this->userId);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'admin']);
    }
```

(`Auth::user()` mocking is no longer needed since submission no longer derives section/division.)

Then replace the entire `full_payroll_lifecycle_moves_through_approval_chain_to_released` test method with:

```php
    /** @test */
    public function full_payroll_lifecycle_moves_through_approval_chain_to_released()
    {
        DB::table('tdh_user.user_priv')->where('user_id', $this->userId)->where('syscode', 'hris')->delete();
        DB::table('tdh_user.user_priv')->insert([
            'user_id' => $this->userId,
            'syscode' => 'hris',
            'level'   => 'admin',
        ]);

        $assignatorySnapshot = DB::table('payroll_assignatories')->get()->map(fn($row) => (array) $row)->all();
        DB::table('payroll_assignatories')->delete();
        DB::table('payroll_assignatories')->insert([
            [
                'order_no' => 1, 'label' => 'Section Head', 'user_id' => $this->userId,
                'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'order_no' => 2, 'label' => 'Division Head', 'user_id' => $this->userId,
                'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'order_no' => 3, 'label' => 'HR', 'user_id' => $this->userId,
                'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $masterId = $this->seedCutoffMaster('2027-01-01', '2027-01-15', 'Smoke Test Cutoff');

        app(\App\Services\PayrollServices::class)->generateCutoffSummary(
            $this->userId,
            '2027-01-01',
            '2027-01-15',
            'Smoke Test Cutoff',
            $this->userId
        );

        $this->actAsAdminWithOrg();

        $saveResponse = $this->withoutMiddleware()->postJson('/save_payroll', [
            'title'        => 'Smoke Test Payroll',
            'cutoff_id'    => $masterId,
            'payroll_type' => 'Regular',
            'rate_type'    => 'Non Pro-rated',
        ]);
        $saveResponse->assertOk();

        $payroll = DB::table('hris.payrolls')->where('title', 'Smoke Test Payroll')->latest('id')->first();
        $this->assertNotNull($payroll);
        $payrollId = $payroll->id;

        try {
            $computeResponse = $this->withoutMiddleware()->postJson('/save_payroll_employee', [
                'payroll_id' => $payrollId,
                'employees'  => [['userid' => $this->userId]],
            ]);
            $computeResponse->assertOk();

            $submitResponse = $this->withoutMiddleware()->postJson('/payroll/submit_for_approval', [
                'payroll_id' => $payrollId,
            ]);
            $submitResponse->assertOk();

            $approvals = DB::table('hris.payroll_approvals')
                ->where('payroll_id', $payrollId)
                ->orderBy('order_no')
                ->get();
            $this->assertCount(3, $approvals);
            $this->assertEquals(['Section Head', 'Division Head', 'HR'], $approvals->pluck('label')->toArray());
            $this->assertEquals(['pending', 'pending', 'pending'], $approvals->pluck('status')->toArray());

            foreach (['Section Head', 'Division Head', 'HR'] as $expectedLabel) {
                $before = DB::table('hris.payroll_approvals')
                    ->where('payroll_id', $payrollId)
                    ->where('status', 'pending')
                    ->orderBy('order_no')
                    ->first();
                $this->assertNotNull($before, "Expected a pending approval step for {$expectedLabel}");
                $this->assertEquals($expectedLabel, $before->label);

                $actionResponse = $this->withoutMiddleware()->postJson('/payroll/approval_action', [
                    'payroll_id' => $payrollId,
                    'status'     => 'approved',
                ]);
                $actionResponse->assertOk();
            }

            $payroll = DB::table('hris.payrolls')->where('id', $payrollId)->first();
            $this->assertEquals('approved', strtolower($payroll->status));
            $this->assertEquals($this->userId, (int) $payroll->reviewed_by);
            $this->assertNotNull($payroll->approved_at);

            $releaseResponse = $this->withoutMiddleware()->postJson('/payroll/release', [
                'payroll_id' => $payrollId,
            ]);
            $releaseResponse->assertOk();

            $payroll = DB::table('hris.payrolls')->where('id', $payrollId)->first();
            $this->assertEquals('released', strtolower($payroll->status));
            $this->assertNotNull($payroll->released_at);
        } finally {
            DB::table('hris.payroll_approvals')->where('payroll_id', $payrollId)->delete();
            DB::table('hris.payroll_employee_components')->whereIn(
                'payroll_employee_id',
                DB::table('hris.payroll_employees')->where('payroll_id', $payrollId)->pluck('id')
            )->delete();
            DB::table('hris.payroll_employees')->where('payroll_id', $payrollId)->delete();
            DB::table('hris.payrolls')->where('id', $payrollId)->delete();
            DB::table('hris.cutoff_summaries')->where('cutoff_id', $masterId)->delete();
            DB::table('tdh_user.user_priv')->where('user_id', $this->userId)->where('syscode', 'hris')->delete();
            DB::table('payroll_assignatories')->delete();
            if (!empty($assignatorySnapshot)) {
                DB::table('payroll_assignatories')->insert($assignatorySnapshot);
            }
        }
    }
```

- [ ] **Step 4: Run the Payroll suite again**

Run: `php artisan test --filter=Payroll`
Expected: all pass.

- [ ] **Step 5: Run the complete test suite**

Run: `php artisan test`
Expected: all passing, no regressions versus the 93-passing baseline (81 original + 12 from the DTR/payroll unified computation plan), plus this plan's new tests (7 in `PayrollAssignatoryTest` + 8 in `PayrollApprovalChainTest` = 15 new): expect `108 passed`.

- [ ] **Step 6: Commit**

```bash
git add resources/views/payroll.blade.php tests/Feature/PayrollCutoffTest.php
git commit -m "refactor: payroll.blade.php and lifecycle test use assignatory labels instead of hardcoded roles"
```
