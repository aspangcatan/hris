# Payroll Cutoff Prerequisite Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enforce a cutoff period as a prerequisite for creating a payroll — the creation form selects from existing `cutoff_summaries_master` records, stores a proper `cutoff_id` FK, and the payroll computation uses this FK directly instead of fragile date matching.

**Architecture:** A new `cutoff_id` column (nullable unsignedBigInteger FK) is added to `payrolls`. `savePayroll()` requires `cutoff_id`, derives `start_date`/`end_date` and legacy cutoff columns from the master record. `savePayrollEmployee()` uses `cutoff_id` directly with a backward-compat fallback for old records. The creation modal's cutoff dropdown option values change from date strings to master record IDs.

**Tech Stack:** Laravel 8, MySQL (`hris` + `tdh_user`), Blade + jQuery/fetch JS, PHPUnit with DatabaseTransactions.

---

## File Map

| Action | File |
|---|---|
| Create | `database/migrations/2026_06_08_100000_add_cutoff_id_to_payrolls_table.php` |
| Modify | `app/Http/Controllers/PayrollController.php` |
| Modify | `resources/views/modals/payroll.blade.php` |
| Modify | `resources/views/payroll.blade.php` |
| Create | `tests/Feature/PayrollCutoffTest.php` |

---

## Task 1: Migration — add cutoff_id to payrolls

**Files:**
- Create: `database/migrations/2026_06_08_100000_add_cutoff_id_to_payrolls_table.php`

- [ ] **Step 1: Create the migration file**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->unsignedBigInteger('cutoff_id')->nullable()->after('id');
            $table->index('cutoff_id');
        });
    }

    public function down()
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropIndex(['cutoff_id']);
            $table->dropColumn('cutoff_id');
        });
    }
};
```

- [ ] **Step 2: Run the migration**

```bash
php artisan migrate --path=database/migrations/2026_06_08_100000_add_cutoff_id_to_payrolls_table.php --force
```

Expected: `Migrated: 2026_06_08_100000_add_cutoff_id_to_payrolls_table`

- [ ] **Step 3: Verify column exists**

```bash
php artisan tinker --execute="var_dump(Schema::hasColumn('payrolls', 'cutoff_id'));"
```

Expected: `bool(true)`

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_06_08_100000_add_cutoff_id_to_payrolls_table.php
git commit -m "feat: add cutoff_id FK column to payrolls table"
```

---

## Task 2: getCutoffPeriods() — expose id in response

**Files:**
- Modify: `app/Http/Controllers/PayrollController.php` (lines ~434–447)
- Create: `tests/Feature/PayrollCutoffTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PayrollCutoffTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PayrollCutoffTest extends TestCase
{
    use DatabaseTransactions;

    private int $userId = 9201;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tdh_user.users')->insertOrIgnore([
            'id'       => $this->userId,
            'fname'    => 'Test',
            'lname'    => 'User',
            'username' => 'testuser9201',
            'password' => 'x',
        ]);
    }

    private function actAsUser(): void
    {
        Auth::shouldReceive('id')->andReturn($this->userId);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'admin']);
    }

    private function seedCutoffMaster(
        string $start = '2026-06-01',
        string $end   = '2026-06-15',
        string $title = 'Test Cutoff'
    ): int {
        return DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title'        => $title,
            'period_start' => $start,
            'period_end'   => $end,
            'generated_by' => $this->userId,
            'generated_at' => now(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    /** @test */
    public function get_cutoff_periods_includes_id()
    {
        $masterId = $this->seedCutoffMaster();
        $this->actAsUser();

        $response = $this->get(route('getCutoffPeriods'));

        $response->assertOk();
        $data = $response->json('data');
        $found = collect($data)->firstWhere('id', $masterId);
        $this->assertNotNull($found, 'Master record not found in response');
        $this->assertArrayHasKey('id', (array) $found);
        $this->assertEquals($masterId, $found['id']);
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
php artisan test tests/Feature/PayrollCutoffTest.php --filter get_cutoff_periods_includes_id
```

Expected: FAIL — `id` key missing from response items.

- [ ] **Step 3: Update getCutoffPeriods() in PayrollController**

Find the `getCutoffPeriods()` method (around line 434). Replace the `->select(...)` line:

```php
public function getCutoffPeriods()
{
    try {
        $table = $this->payrollServices->getCutoffSummaryMasterTableName();
        $periods = DB::table($table)
            ->select('id', 'period_start', 'period_end', 'title')
            ->orderByDesc('period_start')
            ->get();

        return response()->json(['success' => true, 'data' => $periods]);
    } catch (\Exception $exception) {
        return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
    }
}
```

- [ ] **Step 4: Run test to confirm it passes**

```bash
php artisan test tests/Feature/PayrollCutoffTest.php --filter get_cutoff_periods_includes_id
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/PayrollController.php tests/Feature/PayrollCutoffTest.php
git commit -m "feat: expose cutoff master id in getCutoffPeriods response"
```

---

## Task 3: savePayroll() — require cutoff_id, derive dates from master

**Files:**
- Modify: `app/Http/Controllers/PayrollController.php` (lines ~233–270)
- Modify: `tests/Feature/PayrollCutoffTest.php`

- [ ] **Step 1: Write the failing tests**

Add these three tests to `tests/Feature/PayrollCutoffTest.php`:

```php
/** @test */
public function save_payroll_requires_cutoff_id()
{
    $this->actAsUser();

    $response = $this->postJson(route('savePayroll'), [
        'title'        => 'Test Payroll',
        'payroll_type' => 'Regular',
        'rate_type'    => 'Non Pro-rated',
    ]);

    $response->assertStatus(422);
}

/** @test */
public function save_payroll_rejects_nonexistent_cutoff_id()
{
    $this->actAsUser();

    $response = $this->postJson(route('savePayroll'), [
        'title'     => 'Test Payroll',
        'cutoff_id' => 99999,
    ]);

    $response->assertStatus(422);
}

/** @test */
public function save_payroll_with_valid_cutoff_id_stores_cutoff_id_and_derives_dates()
{
    $masterId = $this->seedCutoffMaster('2026-06-01', '2026-06-15', 'June 1st Half');
    $this->actAsUser();

    $response = $this->postJson(route('savePayroll'), [
        'title'        => 'June Payroll',
        'cutoff_id'    => $masterId,
        'payroll_type' => 'Regular',
        'rate_type'    => 'Non Pro-rated',
    ]);

    $response->assertOk();

    $payroll = DB::table('hris.payrolls')
        ->where('title', 'June Payroll')
        ->where('prepared_by', $this->userId)
        ->first();

    $this->assertNotNull($payroll);
    $this->assertEquals($masterId, (int) $payroll->cutoff_id);
    $this->assertEquals('2026-06-01', $payroll->start_date);
    $this->assertEquals('2026-06-15', $payroll->end_date);
    $this->assertEquals('2026-06-01', $payroll->cutoff_start_date);
    $this->assertEquals('2026-06-15', $payroll->cutoff_end_date);
    $this->assertEquals('June 1st Half', $payroll->cutoff_title);
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
php artisan test tests/Feature/PayrollCutoffTest.php --filter "save_payroll"
```

Expected: All FAIL — `cutoff_id` accepted as nullable currently.

- [ ] **Step 3: Rewrite savePayroll() in PayrollController**

Replace the existing `savePayroll()` method entirely:

```php
public function savePayroll(Request $request)
{
    try {
        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'cutoff_id'    => 'required|integer|exists:hris.cutoff_summaries_master,id',
            'payroll_type' => 'nullable|string|max:50',
            'rate_type'    => 'nullable|string|max:50',
            'remarks'      => 'nullable|string',
            'tax_purpose'  => 'nullable|string|max:150',
        ]);

        $masterTable = $this->payrollServices->getCutoffSummaryMasterTableName();
        $master = DB::table($masterTable)->where('id', $validated['cutoff_id'])->first();

        DB::table('hris.payrolls')->insert([
            'cutoff_id'         => $master->id,
            'title'             => $validated['title'],
            'start_date'        => $master->period_start,
            'end_date'          => $master->period_end,
            'cutoff_start_date' => $master->period_start,
            'cutoff_end_date'   => $master->period_end,
            'cutoff_title'      => $master->title,
            'payroll_type'      => $validated['payroll_type'] ?? 'Regular',
            'rate_type'         => $validated['rate_type'] ?? 'Non Pro-rated',
            'remarks'           => $validated['remarks'] ?? null,
            'tax_purpose'       => $validated['tax_purpose'] ?? null,
            'status'            => 'draft',
            'prepared_by'       => Auth::id(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        return response()->json(['message' => 'Payroll created successfully.']);
    } catch (ValidationException $e) {
        return response()->json(['errors' => $e->errors()], 422);
    } catch (\Exception $exception) {
        return response()->json(['error' => $exception->getMessage()], 500);
    }
}
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
php artisan test tests/Feature/PayrollCutoffTest.php --filter "save_payroll"
```

Expected: 3 tests PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/PayrollController.php tests/Feature/PayrollCutoffTest.php
git commit -m "feat: require cutoff_id on payroll creation and derive dates from master"
```

---

## Task 4: savePayrollEmployee() — use cutoff_id FK directly

**Files:**
- Modify: `app/Http/Controllers/PayrollController.php` (lines ~552–657)
- Modify: `tests/Feature/PayrollCutoffTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/PayrollCutoffTest.php`:

```php
private function seedPayrollWithCutoff(int $cutoffId): int
{
    $master = DB::table('hris.cutoff_summaries_master')->where('id', $cutoffId)->first();
    return DB::table('hris.payrolls')->insertGetId([
        'cutoff_id'         => $cutoffId,
        'title'             => 'Test Payroll',
        'start_date'        => $master->period_start,
        'end_date'          => $master->period_end,
        'cutoff_start_date' => $master->period_start,
        'cutoff_end_date'   => $master->period_end,
        'cutoff_title'      => $master->title,
        'payroll_type'      => 'Regular',
        'rate_type'         => 'Non Pro-rated',
        'status'            => 'draft',
        'prepared_by'       => $this->userId,
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);
}

/** @test */
public function save_payroll_employee_fails_gracefully_when_no_cutoff_summaries_exist()
{
    $masterId  = $this->seedCutoffMaster();
    $payrollId = $this->seedPayrollWithCutoff($masterId);
    $this->actAsUser();

    $response = $this->postJson(route('savePayrollEmployee'), [
        'payroll_id' => $payrollId,
        'employees'  => [['userid' => $this->userId]],
    ]);

    // Should fail with missing cutoff summaries error (not a date-lookup error)
    $response->assertStatus(422);
    $this->assertStringContainsString('cutoff', strtolower($response->json('error') ?? ''));
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
php artisan test tests/Feature/PayrollCutoffTest.php --filter save_payroll_employee_fails_gracefully
```

Expected: FAIL or error unrelated to cutoff lookup (proves the old date-based path is broken / unreliable).

- [ ] **Step 3: Update savePayrollEmployee() in PayrollController**

Find `savePayrollEmployee()` (around line 552). Replace the cutoff master lookup block (lines ~566–575) with the FK-based lookup. Locate this block:

```php
$cutoffStart = $period->cutoff_start_date ?? $period->start_date;
$cutoffEnd = $period->cutoff_end_date ?? $period->end_date;
$cutoffTitle = $period->cutoff_title ?? null;

$cutoffMaster = $this->payrollServices->getCutoffMasterByPeriod($cutoffStart, $cutoffEnd, $cutoffTitle);
if (!$cutoffMaster) {
    return response()->json([
        'error' => 'Cutoff master record not found for the selected period.',
    ], 422);
}
```

Replace it with:

```php
$masterTable = $this->payrollServices->getCutoffSummaryMasterTableName();

if ($period->cutoff_id) {
    $cutoffMaster = DB::table($masterTable)->where('id', $period->cutoff_id)->first();
} else {
    // Backward compat: old payrolls without cutoff_id use date matching
    $cutoffStart = $period->cutoff_start_date ?? $period->start_date;
    $cutoffEnd   = $period->cutoff_end_date ?? $period->end_date;
    $cutoffTitle = $period->cutoff_title ?? null;
    $cutoffMaster = $this->payrollServices->getCutoffMasterByPeriod($cutoffStart, $cutoffEnd, $cutoffTitle);
}

if (!$cutoffMaster) {
    return response()->json([
        'error' => 'Cutoff master record not found for this payroll.',
    ], 422);
}

$cutoffStart = $cutoffMaster->period_start;
$cutoffEnd   = $cutoffMaster->period_end;
```

Also find the `getOrCreateCutoffSummary` call inside the transaction (around line 606). It receives `$cutoffStart` and `$cutoffEnd` — those are now derived from `$cutoffMaster` above. Remove the existing declarations for `$cutoffStart`/`$cutoffEnd`/`$cutoffTitle` from the `use(...)` clause if they were passed in and add `$cutoffMaster` instead, or simply ensure `$cutoffStart` and `$cutoffEnd` are in scope for the closure. The closure `use` clause should include `$cutoffStart` and `$cutoffEnd`:

```php
DB::transaction(function () use ($validated, $period, $cutoffStart, $cutoffEnd, $employeeIds, $cutoffMaster) {
```

- [ ] **Step 4: Run test to confirm it passes**

```bash
php artisan test tests/Feature/PayrollCutoffTest.php --filter save_payroll_employee_fails_gracefully
```

Expected: PASS — error message now contains "cutoff" from the FK-based lookup path.

- [ ] **Step 5: Run full test suite to confirm no regressions**

```bash
php artisan test tests/Feature/PayrollCutoffTest.php
```

Expected: All tests PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/PayrollController.php tests/Feature/PayrollCutoffTest.php
git commit -m "feat: use cutoff_id FK for payroll computation with backward-compat fallback"
```

---

## Task 5: getPayroll() — join cutoff master, expose cutoff_label

**Files:**
- Modify: `app/Http/Controllers/PayrollController.php` (lines ~494–550)
- Modify: `tests/Feature/PayrollCutoffTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/PayrollCutoffTest.php`:

```php
/** @test */
public function get_payroll_list_includes_cutoff_label()
{
    $masterId  = $this->seedCutoffMaster('2026-06-01', '2026-06-15', 'June 1st Half');
    $payrollId = $this->seedPayrollWithCutoff($masterId);
    $this->actAsUser();

    $response = $this->get(route('getPayroll'));

    $response->assertOk();
    $found = collect($response->json())->firstWhere('id', $payrollId);
    $this->assertNotNull($found);
    $this->assertEquals('June 1st Half', $found['cutoff_label'] ?? null);
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
php artisan test tests/Feature/PayrollCutoffTest.php --filter get_payroll_list_includes_cutoff_label
```

Expected: FAIL — `cutoff_label` key missing.

- [ ] **Step 3: Update getPayroll() in PayrollController**

Find `getPayroll()` (around line 494). Add the left join with cutoff master and include new select columns.

Replace the query builder opening:

```php
$payrolls = DB::table('payrolls as p')
    ->leftJoin('tdh_user.users as u', 'u.id', '=', 'p.prepared_by')
    ->select(
        'p.id',
        'p.title',
        'p.start_date',
        'p.end_date',
        'p.cutoff_start_date',
        'p.cutoff_end_date',
        'p.cutoff_title',
        'p.payroll_type',
        'p.rate_type',
        'p.remarks',
        'p.status',
        'p.prepared_by',
        'p.created_at',
        'u.fname',
        'u.mname',
        'u.lname',
        'u.suffix'
    )
    ->orderByDesc('p.created_at')
    ->get();
```

With:

```php
$masterTable = $this->payrollServices->getCutoffSummaryMasterTableName();

$payrolls = DB::table('payrolls as p')
    ->leftJoin('tdh_user.users as u', 'u.id', '=', 'p.prepared_by')
    ->leftJoin($masterTable . ' as cm', 'cm.id', '=', 'p.cutoff_id')
    ->select(
        'p.id',
        'p.title',
        'p.start_date',
        'p.end_date',
        'p.cutoff_id',
        'p.cutoff_start_date',
        'p.cutoff_end_date',
        'p.cutoff_title',
        'p.payroll_type',
        'p.rate_type',
        'p.remarks',
        'p.status',
        'p.prepared_by',
        'p.created_at',
        'u.fname',
        'u.mname',
        'u.lname',
        'u.suffix',
        DB::raw('COALESCE(cm.title, p.cutoff_title) as cutoff_label')
    )
    ->orderByDesc('p.created_at')
    ->get();
```

- [ ] **Step 4: Run test to confirm it passes**

```bash
php artisan test tests/Feature/PayrollCutoffTest.php --filter get_payroll_list_includes_cutoff_label
```

Expected: PASS

- [ ] **Step 5: Run full suite**

```bash
php artisan test tests/Feature/PayrollCutoffTest.php
```

Expected: All tests PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/PayrollController.php tests/Feature/PayrollCutoffTest.php
git commit -m "feat: join cutoff master in getPayroll and expose cutoff_label"
```

---

## Task 6: Modal HTML — simplify creation form

**Files:**
- Modify: `resources/views/modals/payroll.blade.php` (lines ~128–200)

- [ ] **Step 1: Read the current modal form**

Open `resources/views/modals/payroll.blade.php` and locate the `#create_payroll_modal` div (line ~128). The current form has these fields in order:
1. `#title` — text input
2. `#start_date` — date input (editable)
3. `#end_date` — date input (editable)
4. `#cutoff_period` — select (optional)
5. `#cutoff_start_date` — readonly date (redundant display)
6. `#cutoff_end_date` — readonly date (redundant display)
7. `#payroll_type` — select
8. `#rate_type` — select
9. ... (tax_purpose, remarks)

- [ ] **Step 2: Replace the cutoff + dates section**

Find this block (lines ~150–174):

```html
                    <!-- Payroll Period Start -->
                    <div class="col-md-6">
                        <label for="payroll_start" class="form-label">Payroll Period Start</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" required>
                    </div>

                    <!-- Payroll Period End -->
                    <div class="col-md-6">
                        <label for="payroll_end" class="form-label">Payroll Period End</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" required>
                    </div>
                    <div class="col-md-12">
                        <label for="cutoff_period" class="form-label">Cutoff Period</label>
                        <select class="form-control" id="cutoff_period">
                            <option value="">Use payroll period dates</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Cutoff Start</label>
                        <input type="date" class="form-control" id="cutoff_start_date" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Cutoff End</label>
                        <input type="date" class="form-control" id="cutoff_end_date" readonly>
                    </div>
```

Replace with:

```html
                    <!-- Cutoff Period (required) -->
                    <div class="col-md-12">
                        <label for="cutoff_period" class="form-label">Cutoff Period <span class="text-danger">*</span></label>
                        <select class="form-control" id="cutoff_period" required>
                            <option value="">— Select a cutoff period —</option>
                        </select>
                    </div>

                    <!-- Payroll Period (auto-filled from cutoff, read-only) -->
                    <div class="col-md-6">
                        <label class="form-label">Period Start</label>
                        <input type="date" class="form-control" id="start_date" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Period End</label>
                        <input type="date" class="form-control" id="end_date" readonly>
                    </div>
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/modals/payroll.blade.php
git commit -m "feat: simplify payroll creation modal — cutoff required, dates auto-filled"
```

---

## Task 7: JS — update loadCutoffPeriods, change handler, save handler

**Files:**
- Modify: `resources/views/payroll.blade.php`

This task has three JS changes in the same file. Make them one at a time.

### Change A — loadCutoffPeriods(): option value = master id

- [ ] **Step 1: Update loadCutoffPeriods() function**

Find `function loadCutoffPeriods()` (around line 909). Replace the `forEach` block inside it:

Current code:
```js
(data.data || []).forEach((item, index) => {
    const title = item.remarks || `Cutoff ${item.period_start} - ${item.period_end}`;
    const value = `${item.period_start}|${item.period_end}|${title}`;
    cutoffMeta[value] = item;
    $select.append(`<option value="${value}" data-title="${title}">${title} (${item.period_start} to ${item.period_end})</option>`);
});
```

Replace with:

```js
(data.data || []).forEach((item) => {
    const title = item.title || `Cutoff ${item.period_start} - ${item.period_end}`;
    $select.append(
        `<option value="${item.id}" data-start="${item.period_start}" data-end="${item.period_end}" data-title="${title}">` +
        `${title} (${item.period_start} to ${item.period_end})</option>`
    );
});
```

Also update the line above it that initialises `cutoffMeta` — remove the `cutoffMeta = {};` line and the entire `cutoffMeta` variable (it is no longer needed; we read dates from `data-start`/`data-end`):

Find at the top of the `loadCutoffPeriods` response handler:
```js
const $select = $("#cutoff_period");
$select.empty().append("<option value=''>Use payroll period dates</option>");
cutoffMeta = {};
```

Replace with:
```js
const $select = $("#cutoff_period");
$select.empty().append("<option value=''>— Select a cutoff period —</option>");
```

Also find `let cutoffMeta = {};` near the top of the script block (around line 152) and remove that variable declaration entirely.

### Change B — #cutoff_period change handler: use data attributes

- [ ] **Step 2: Replace the #cutoff_period change handler**

Find the change handler (around line 929):

```js
$("#cutoff_period").change(function () {
    const value = $(this).val();
    if (!value) {
        $("#cutoff_start_date").val("");
        $("#cutoff_end_date").val("");
        $("#start_date").prop("readonly", false);
        $("#end_date").prop("readonly", false);
        return;
    }
    const parts = value.split("|");
    $("#cutoff_start_date").val(parts[0] || "");
    $("#cutoff_end_date").val(parts[1] || "");
    $("#start_date").val(parts[0] || "").prop("readonly", true);
    $("#end_date").val(parts[1] || "").prop("readonly", true);
});
```

Replace with:

```js
$("#cutoff_period").change(function () {
    const $option = $(this).find(":selected");
    const periodStart = $option.data("start") || "";
    const periodEnd   = $option.data("end")   || "";
    $("#start_date").val(periodStart);
    $("#end_date").val(periodEnd);
});
```

### Change C — #btn_save_payroll click handler: send cutoff_id, validate required

- [ ] **Step 3: Replace the #btn_save_payroll click handler**

Find `$("#btn_save_payroll").click(function ()` (around line 319). Replace the entire handler:

```js
$("#btn_save_payroll").click(function () {
    const title       = $("#title").val().trim();
    const cutoff_id   = parseInt($("#cutoff_period").val()) || null;
    const payroll_type = $("#payroll_type").val();
    const rate_type   = $("#rate_type").val();
    const remarks     = $("#remarks").val().trim();
    const tax_purpose = $("#tax_purpose").val().trim();

    if (!title) {
        toastr.error("Payroll title is required.", "Validation");
        return;
    }
    if (!cutoff_id) {
        toastr.error("Please select a cutoff period.", "Validation");
        return;
    }

    fetch('{{ route('savePayroll') }}', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        body: JSON.stringify({
            title:        title,
            cutoff_id:    cutoff_id,
            payroll_type: payroll_type,
            rate_type:    rate_type,
            remarks:      remarks || null,
            tax_purpose:  tax_purpose || null,
        })
    })
        .then(res => res.json())
        .then(data => {
            if (data.errors || data.error) {
                const msg = data.error || Object.values(data.errors)[0]?.[0] || "Failed to create payroll.";
                toastr.error(msg, "Error");
                return;
            }
            getPayroll();
            $("#create_payroll_modal").modal("hide");
            toastr.success(data.message, 'Success');
        })
        .catch(err => {
            console.error(err);
            toastr.error("Failed to create payroll.", "Error");
        });
});
```

- [ ] **Step 4: Commit all JS changes**

```bash
git add resources/views/payroll.blade.php
git commit -m "feat: wire cutoff_id into payroll creation JS — dropdown, auto-fill, submit"
```

---

## Final Verification

- [ ] **Run full test suite**

```bash
php artisan test tests/Feature/PayrollCutoffTest.php
```

Expected: All tests PASS

- [ ] **Manual smoke test — create payroll with cutoff**

1. Navigate to `/payroll`.
2. Click "Create Payroll".
3. Confirm the Cutoff Period dropdown loads existing cutoff master records.
4. Select a cutoff — Period Start and Period End auto-fill (readonly).
5. Enter a Payroll Title.
6. Click Save.
7. Confirm the new payroll appears in the list with the correct cutoff label and dates.

- [ ] **Manual smoke test — cutoff required validation**

1. Open "Create Payroll" modal.
2. Enter a title, do NOT select a cutoff.
3. Click Save.
4. Confirm toastr error: "Please select a cutoff period."

- [ ] **Manual smoke test — old payrolls still load**

1. Confirm existing payrolls (where `cutoff_id` is null) still appear in the list with no errors.
2. `cutoff_label` falls back to `cutoff_title` for these records.

- [ ] **Manual smoke test — compute payroll employees**

1. Open an existing payroll that was created with a cutoff.
2. Add employees and click Compute.
3. Confirm computation uses cutoff data (no "cutoff master not found" error).
