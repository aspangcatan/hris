# Per-Employee Rate Override Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an employee be given a different percentage rate for a percentage-based global component (e.g. 30% Hazard Pay instead of the catalog's 25%), while the amount keeps recomputing live off their basic salary — instead of the only current option, a flat `amount_override` that freezes the number and no longer tracks salary changes.

**Architecture:** One new nullable `rate_percent_override` column on `hris.employee_payroll_components`. `PayrollServices::resolveComponentAmount()` gains a priority step between "amount override wins outright" and "use the catalog's rate": if the employee has a `rate_percent_override`, use it instead of the catalog's `rate_percent`, against the same base. A new `PayrollServices::validateRatePercentOverrides()` guard (mirroring `validateComponentFormulas()`) rejects negative values before persisting. Both UI surfaces that already render the Earnings/Deductions override tables (Employee modal's Payroll tab, and Settings → Payroll Settings) gain a "Rate Override %" column next to "Amount".

**Tech Stack:** Laravel 8 / PHP, MySQL (`hris` schema, `employee_payroll_components` is MyISAM in this environment — no transactional rollback), Blade + jQuery, PHPUnit (`DatabaseTransactions` with explicit manual cleanup).

**Reference spec:** `docs/superpowers/specs/2026-07-14-per-employee-rate-override-design.md`

---

### Task 1: Migration — add `rate_percent_override` to `employee_payroll_components`

**Files:**
- Create: `database/migrations/2026_07_14_100000_add_rate_percent_override_to_employee_payroll_components_table.php`

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets an employee be given a different percentage rate for a
     * percentage-based global component (e.g. 30% Hazard Pay instead of the
     * catalog's 25%), while the amount keeps recomputing live off their
     * basic salary - see
     * docs/superpowers/specs/2026-07-14-per-employee-rate-override-design.md.
     * Only meaningful when the component's calculation_type = 'percentage';
     * ignored for fixed components. Existing rows default to null, which
     * computes identically to today (catalog rate applies).
     */
    public function up()
    {
        Schema::table('employee_payroll_components', function (Blueprint $table) {
            $table->decimal('rate_percent_override', 8, 3)->nullable()->after('amount_override');
        });
    }

    public function down()
    {
        Schema::table('employee_payroll_components', function (Blueprint $table) {
            $table->dropColumn('rate_percent_override');
        });
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `php artisan migrate`
Expected: `Migrating: 2026_07_14_100000_add_rate_percent_override_to_employee_payroll_components_table` then `Migrated:` with no errors.

- [ ] **Step 3: Verify the column exists**

Run:
```bash
php artisan tinker --execute="echo json_encode(DB::selectOne(\"SHOW COLUMNS FROM hris.employee_payroll_components WHERE Field = 'rate_percent_override'\"));"
```
Expected: JSON showing `rate_percent_override` with `Type: "decimal(8,3)"` and `Null: "YES"`.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_07_14_100000_add_rate_percent_override_to_employee_payroll_components_table.php
git commit -m "feat: add rate_percent_override column to employee_payroll_components"
```

---

### Task 2: Resolution algorithm — prefer the employee's rate override

**Files:**
- Modify: `app/Services/PayrollServices.php:710-735` (`getEmployeeDynamicComponents()` select) and `:827-850` (`resolveComponentAmount()`)
- Create: `tests/Feature/PayrollComponentRateOverrideTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Services\PayrollServices;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class PayrollComponentRateOverrideTest extends TestCase
{
    private function callResolveComponentAmounts(\Illuminate\Support\Collection $rows, float $basicSalary): \Illuminate\Support\Collection
    {
        $service = app(PayrollServices::class);
        $method = new ReflectionMethod(PayrollServices::class, 'resolveComponentAmounts');
        $method->setAccessible(true);
        return $method->invoke($service, $rows, $basicSalary);
    }

    /** @test */
    public function a_rate_override_replaces_the_catalog_rate_against_the_same_base()
    {
        $rows = collect([
            (object) [
                'code' => 'TEST_RATE_A',
                'default_amount' => 0,
                'amount_override' => null,
                'rate_percent_override' => 30,
                'calculation_type' => 'percentage',
                'base_type' => 'basic_salary',
                'base_component_code' => null,
                'rate_percent' => 25,
            ],
        ]);

        $resolved = $this->callResolveComponentAmounts($rows, 20000.0);

        // 30% of 20,000 - the employee's override rate, not the catalog's 25%.
        $this->assertEqualsWithDelta(6000.0, $resolved->firstWhere('code', 'TEST_RATE_A')->amount, 0.001);
    }

    /** @test */
    public function with_no_rate_override_the_catalog_rate_still_applies()
    {
        $rows = collect([
            (object) [
                'code' => 'TEST_RATE_A',
                'default_amount' => 0,
                'amount_override' => null,
                'rate_percent_override' => null,
                'calculation_type' => 'percentage',
                'base_type' => 'basic_salary',
                'base_component_code' => null,
                'rate_percent' => 25,
            ],
        ]);

        $resolved = $this->callResolveComponentAmounts($rows, 20000.0);

        $this->assertEqualsWithDelta(5000.0, $resolved->firstWhere('code', 'TEST_RATE_A')->amount, 0.001);
    }

    /** @test */
    public function an_amount_override_wins_outright_over_a_rate_override()
    {
        $rows = collect([
            (object) [
                'code' => 'TEST_RATE_A',
                'default_amount' => 0,
                'amount_override' => 9999.0,
                'rate_percent_override' => 30,
                'calculation_type' => 'percentage',
                'base_type' => 'basic_salary',
                'base_component_code' => null,
                'rate_percent' => 25,
            ],
        ]);

        $resolved = $this->callResolveComponentAmounts($rows, 20000.0);

        $this->assertEqualsWithDelta(9999.0, $resolved->firstWhere('code', 'TEST_RATE_A')->amount, 0.001);
    }

    /** @test */
    public function a_fixed_component_ignores_a_stray_rate_override()
    {
        $rows = collect([
            (object) [
                'code' => 'TEST_RATE_A',
                'default_amount' => 500,
                'amount_override' => null,
                'rate_percent_override' => 30,
                'calculation_type' => 'fixed',
                'base_type' => null,
                'base_component_code' => null,
                'rate_percent' => null,
            ],
        ]);

        $resolved = $this->callResolveComponentAmounts($rows, 20000.0);

        $this->assertEqualsWithDelta(500.0, $resolved->firstWhere('code', 'TEST_RATE_A')->amount, 0.001);
    }

    /** @test */
    public function a_rate_override_also_applies_when_the_base_is_another_component()
    {
        $rows = collect([
            (object) [
                'code' => 'TEST_RATE_BASE',
                'default_amount' => 0,
                'amount_override' => null,
                'rate_percent_override' => null,
                'calculation_type' => 'percentage',
                'base_type' => 'basic_salary',
                'base_component_code' => null,
                'rate_percent' => 25,
            ],
            (object) [
                'code' => 'TEST_RATE_DEPENDENT',
                'default_amount' => 0,
                'amount_override' => null,
                'rate_percent_override' => 20,
                'calculation_type' => 'percentage',
                'base_type' => 'component',
                'base_component_code' => 'TEST_RATE_BASE',
                'rate_percent' => 10,
            ],
        ]);

        $resolved = $this->callResolveComponentAmounts($rows, 20000.0);

        // Base resolves at its own (non-overridden) rate: 25% of 20,000 = 5,000.
        $this->assertEqualsWithDelta(5000.0, $resolved->firstWhere('code', 'TEST_RATE_BASE')->amount, 0.001);
        // Dependent uses its override rate (20%) against the resolved base, not the catalog's 10%.
        $this->assertEqualsWithDelta(1000.0, $resolved->firstWhere('code', 'TEST_RATE_DEPENDENT')->amount, 0.001);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/PayrollComponentRateOverrideTest.php`
Expected: FAIL — every assertion off (e.g. `a_rate_override_replaces_the_catalog_rate_against_the_same_base` gets `5000.0` instead of `6000.0`), since `resolveComponentAmount()` doesn't read `rate_percent_override` yet.

- [ ] **Step 3: Update `getEmployeeDynamicComponents()`'s select**

In `app/Services/PayrollServices.php`, find (around line 719-735):

```php
            ->select(
                'pc.id as payroll_component_id',
                'pc.code',
                'pc.name',
                'pc.type',
                'pc.taxable',
                'pc.default_amount',
                'pc.calculation_type',
                'pc.base_type',
                'pc.base_component_code',
                'pc.rate_percent',
                'epc.amount_override',
                'epc.is_active as employee_active',
                'epc.is_excluded',
                'epc.effective_from',
                'epc.effective_to'
            )
```

Replace with:

```php
            ->select(
                'pc.id as payroll_component_id',
                'pc.code',
                'pc.name',
                'pc.type',
                'pc.taxable',
                'pc.default_amount',
                'pc.calculation_type',
                'pc.base_type',
                'pc.base_component_code',
                'pc.rate_percent',
                'epc.amount_override',
                'epc.rate_percent_override',
                'epc.is_active as employee_active',
                'epc.is_excluded',
                'epc.effective_from',
                'epc.effective_to'
            )
```

- [ ] **Step 4: Update `resolveComponentAmount()` to prefer the employee's rate override**

In `app/Services/PayrollServices.php`, find (around line 827-850):

```php
    private function resolveComponentAmount($row, array $resolved, float $basicSalary): float
    {
        if ($row->amount_override !== null) {
            return round((float) $row->amount_override, 3);
        }

        $calculationType = $row->calculation_type ?? 'fixed';
        if ($calculationType !== 'percentage') {
            return round((float) ($row->default_amount ?? 0), 3);
        }

        $rate = (float) ($row->rate_percent ?? 0);

        if (($row->base_type ?? null) === 'basic_salary') {
            return round($basicSalary * $rate / 100, 3);
        }

        if (($row->base_type ?? null) === 'component') {
            $baseAmount = $resolved[$row->base_component_code] ?? 0.0;
            return round($baseAmount * $rate / 100, 3);
        }

        return 0.0;
    }
```

Replace with:

```php
    private function resolveComponentAmount($row, array $resolved, float $basicSalary): float
    {
        if ($row->amount_override !== null) {
            return round((float) $row->amount_override, 3);
        }

        $calculationType = $row->calculation_type ?? 'fixed';
        if ($calculationType !== 'percentage') {
            return round((float) ($row->default_amount ?? 0), 3);
        }

        $rateOverride = $row->rate_percent_override ?? null;
        $rate = $rateOverride !== null ? (float) $rateOverride : (float) ($row->rate_percent ?? 0);

        if (($row->base_type ?? null) === 'basic_salary') {
            return round($basicSalary * $rate / 100, 3);
        }

        if (($row->base_type ?? null) === 'component') {
            $baseAmount = $resolved[$row->base_component_code] ?? 0.0;
            return round($baseAmount * $rate / 100, 3);
        }

        return 0.0;
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/PayrollComponentRateOverrideTest.php`
Expected: `Tests: 5 passed`.

- [ ] **Step 6: Run existing payroll suites to check for regressions**

Run: `php artisan test tests/Feature/PayrollComponentFormulaTest.php tests/Feature/PayrollComponentAdjustmentTest.php`
Expected: both suites still fully pass — a `rate_percent_override` of `null` on every existing row must not change behavior.

- [ ] **Step 7: Commit**

```bash
git add app/Services/PayrollServices.php tests/Feature/PayrollComponentRateOverrideTest.php
git commit -m "feat: resolve percentage components against an employee's rate override"
```

---

### Task 3: Validate and persist `rate_percent_override` on save

**Files:**
- Modify: `app/Services/PayrollServices.php` (add method after `validateComponentFormulas()`, currently ending at `app/Services/PayrollServices.php:461`)
- Modify: `app/Http/Controllers/PayrollController.php:26-177` (`getPayrollSettings()`, `savePayrollSettings()`, `syncDynamicComponents()`)
- Modify: `tests/Feature/PayrollComponentRateOverrideTest.php`

- [ ] **Step 1: Write the failing tests**

First, add the test-scaffolding (DB trait, test user/component fixtures, cleanup). In `tests/Feature/PayrollComponentRateOverrideTest.php`, find:

```php
class PayrollComponentRateOverrideTest extends TestCase
{
    private function callResolveComponentAmounts(\Illuminate\Support\Collection $rows, float $basicSalary): \Illuminate\Support\Collection
```

Replace with:

```php
class PayrollComponentRateOverrideTest extends TestCase
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    private int $wireTestUserId = 9801;
    private string $componentCode = 'TEST_RATE_WIRE';

    protected function setUp(): void
    {
        parent::setUp();
        // APP_URL contains a subfolder (/hris) which breaks test HTTP client path matching.
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        // payroll_components/payroll_settings/tdh_user.users/
        // employee_payroll_components are MyISAM in this environment, so
        // DatabaseTransactions rollback is a silent no-op - clean up explicitly.
        $this->cleanup();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        DB::table('hris.employee_payroll_components')->where('userid', $this->wireTestUserId)->delete();
        DB::table('hris.payroll_settings')->where('userid', $this->wireTestUserId)->delete();
        DB::table('tdh_user.users')->where('id', $this->wireTestUserId)->delete();
        DB::table('hris.payroll_components')->where('code', $this->componentCode)->delete();
    }

    private function actAsAdmin(): void
    {
        Auth::shouldReceive('id')->andReturn($this->wireTestUserId);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'admin']);
    }

    private function seedTestUser(): void
    {
        DB::table('tdh_user.users')->insert([
            'id' => $this->wireTestUserId,
            'fname' => 'Rate',
            'lname' => 'Tester',
            'username' => 'ratetester' . $this->wireTestUserId,
            'password' => 'x',
            'division' => 2,
            'section' => 21,
            'designation' => 52,
        ]);
    }

    private function callResolveComponentAmounts(\Illuminate\Support\Collection $rows, float $basicSalary): \Illuminate\Support\Collection
```

(This restores the `callResolveComponentAmounts()` signature line consumed by the `find` match above — its body, unchanged from Task 2, stays exactly where it was.)

Then add these test methods just before the class's closing `}`:

```php
    /** @test */
    public function rejects_a_negative_rate_override_via_the_save_endpoint()
    {
        DB::table('hris.payroll_components')->insert([
            'code' => $this->componentCode,
            'name' => 'Test Rate Component',
            'type' => 'earning',
            'is_global' => 1,
            'taxable' => 0,
            'default_amount' => 0,
            'calculation_type' => 'percentage',
            'base_type' => 'basic_salary',
            'base_component_code' => null,
            'rate_percent' => 25,
            'is_active' => 1,
            'is_cutoff_adjustable' => 0,
            'sort_order' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->seedTestUser();
        $this->actAsAdmin();

        $response = $this->withoutMiddleware()->postJson('/save_payroll_settings', [
            'userid' => $this->wireTestUserId,
            'salary_grade' => 11,
            'step' => 1,
            'basic_salary' => 20000,
            'components' => [[
                'code' => $this->componentCode,
                'name' => 'Test Rate Component',
                'type' => 'earning',
                'taxable' => false,
                'is_global' => true,
                'default_amount' => 0,
                'amount_override' => null,
                'rate_percent_override' => -5,
                'is_excluded' => false,
                'effective_from' => null,
                'effective_to' => null,
            ]],
        ]);

        $response->assertStatus(422);

        $assignment = DB::table('hris.employee_payroll_components')
            ->where('userid', $this->wireTestUserId)
            ->first();
        $this->assertNull($assignment);
    }

    /** @test */
    public function saving_a_rate_override_persists_it_and_round_trips_through_get_payroll_settings()
    {
        DB::table('hris.payroll_components')->insert([
            'code' => $this->componentCode,
            'name' => 'Test Rate Component',
            'type' => 'earning',
            'is_global' => 1,
            'taxable' => 0,
            'default_amount' => 0,
            'calculation_type' => 'percentage',
            'base_type' => 'basic_salary',
            'base_component_code' => null,
            'rate_percent' => 25,
            'is_active' => 1,
            'is_cutoff_adjustable' => 0,
            'sort_order' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->seedTestUser();
        $this->actAsAdmin();

        $response = $this->withoutMiddleware()->postJson('/save_payroll_settings', [
            'userid' => $this->wireTestUserId,
            'salary_grade' => 11,
            'step' => 1,
            'basic_salary' => 20000,
            'components' => [[
                'code' => $this->componentCode,
                'name' => 'Test Rate Component',
                'type' => 'earning',
                'taxable' => false,
                'is_global' => true,
                'default_amount' => 0,
                'amount_override' => null,
                'rate_percent_override' => 30,
                'is_excluded' => false,
                'effective_from' => null,
                'effective_to' => null,
            ]],
        ]);
        $response->assertOk();

        $stored = DB::table('hris.employee_payroll_components')
            ->where('userid', $this->wireTestUserId)
            ->first();
        $this->assertNotNull($stored);
        $this->assertEqualsWithDelta(30.0, (float) $stored->rate_percent_override, 0.001);
        $this->assertEquals(1, (int) $stored->is_active);

        $reload = $this->withoutMiddleware()->get('/payroll-settings?userid=' . $this->wireTestUserId);
        $reload->assertOk();
        $reloadedComponent = collect($reload->json('components'))->firstWhere('code', $this->componentCode);
        $this->assertNotNull($reloadedComponent);
        $this->assertEqualsWithDelta(30.0, (float) $reloadedComponent['rate_percent_override'], 0.001);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/PayrollComponentRateOverrideTest.php --filter "rejects_a_negative_rate_override|saving_a_rate_override_persists_it"`
Expected: FAIL — the "rejects" test fails because the response is `200` instead of `422` (no validation wired in yet); the "persists" test fails because `stored->rate_percent_override` doesn't exist as a column being written to, or comes back null (the field is silently dropped since `syncDynamicComponents()` doesn't read it yet).

- [ ] **Step 3: Add `validateRatePercentOverrides()`**

In `app/Services/PayrollServices.php`, add this method immediately after `validateComponentFormulas()` (currently ending at `app/Services/PayrollServices.php:461`, right before `taxRangesOverlap()`):

```php
    /**
     * Checks a set of per-employee component overrides for a negative
     * rate_percent_override before anything is persisted. A missing or null
     * rate_percent_override is fine (it just means "use the catalog rate").
     * Returns a human-readable error, or null if every override is valid.
     *
     * @param array<int, array{code?: string, rate_percent_override?: mixed}> $components
     */
    public function validateRatePercentOverrides(array $components): ?string
    {
        foreach ($components as $component) {
            if (!array_key_exists('rate_percent_override', $component)) {
                continue;
            }
            $rateOverride = $component['rate_percent_override'];
            if ($rateOverride === null || $rateOverride === '') {
                continue;
            }
            if ((float) $rateOverride < 0) {
                $code = $component['code'] ?? '?';
                return "Component \"{$code}\" has an invalid rate override.";
            }
        }

        return null;
    }
```

- [ ] **Step 4: Wire validation and persistence into `PayrollController`**

In `app/Http/Controllers/PayrollController.php`, find `getPayrollSettings()` (lines 26-58):

```php
    public function getPayrollSettings(Request $request)
    {
        try {
            $payroll = DB::table('payroll_settings')->where('userid', $request->userid)->first();
            $components = DB::table('hris.employee_payroll_components as epc')
                ->join('hris.payroll_components as pc', 'pc.id', '=', 'epc.payroll_component_id')
                ->where('epc.userid', $request->userid)
                ->orderBy('pc.sort_order')
                ->select(
                    'pc.code',
                    'pc.name',
                    'pc.type',
                    'pc.taxable',
                    'pc.is_global',
                    'pc.default_amount',
                    'epc.amount',
                    'epc.amount_override',
                    'epc.is_active',
                    'epc.is_excluded',
                    'epc.effective_from',
                    'epc.effective_to'
                )
                ->get();
```

Replace the `->select(...)` call with:

```php
                ->select(
                    'pc.code',
                    'pc.name',
                    'pc.type',
                    'pc.taxable',
                    'pc.is_global',
                    'pc.default_amount',
                    'pc.calculation_type',
                    'epc.amount',
                    'epc.amount_override',
                    'epc.rate_percent_override',
                    'epc.is_active',
                    'epc.is_excluded',
                    'epc.effective_from',
                    'epc.effective_to'
                )
                ->get();
```

(`pc.calculation_type` is added too — the UI in Task 5/6 needs it to know whether to show the Rate Override input for a given row on reload, the same way it already needs `pc.default_amount` etc.)

Next, find `savePayrollSettings()` (lines 60-100):

```php
    public function savePayrollSettings(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'salary_grade' => 'required|integer|min:1',
                'step' => 'required|integer|min:1',
                'basic_salary' => 'required|numeric|min:0',
            ]);

            $dynamicComponents = $request->input('components', null);
            if ($dynamicComponents !== null && !is_array($dynamicComponents)) {
                $dynamicComponents = [];
            }

            DB::table('payroll_settings')->updateOrInsert(
```

Replace with:

```php
    public function savePayrollSettings(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'salary_grade' => 'required|integer|min:1',
                'step' => 'required|integer|min:1',
                'basic_salary' => 'required|numeric|min:0',
            ]);

            $dynamicComponents = $request->input('components', null);
            if ($dynamicComponents !== null && !is_array($dynamicComponents)) {
                $dynamicComponents = [];
            }

            if ($dynamicComponents !== null) {
                $rateError = $this->payrollServices->validateRatePercentOverrides($dynamicComponents);
                if ($rateError) {
                    return response()->json(['message' => $rateError], 422);
                }
            }

            DB::table('payroll_settings')->updateOrInsert(
```

Next, find `syncDynamicComponents()` (lines 102-177), specifically the variable extraction block:

```php
                $code = strtoupper(trim((string) $component['code']));
                $name = trim((string) $component['name']);
                $type = strtolower(trim((string) $component['type'])) === 'deduction' ? 'deduction' : 'earning';
                $taxable = !empty($component['taxable']);
                $amount = isset($component['amount']) ? (float) $component['amount'] : 0;
                $amountOverride = array_key_exists('amount_override', $component) ? $component['amount_override'] : null;
                $isExcluded = !empty($component['is_excluded']);
```

Replace with:

```php
                $code = strtoupper(trim((string) $component['code']));
                $name = trim((string) $component['name']);
                $type = strtolower(trim((string) $component['type'])) === 'deduction' ? 'deduction' : 'earning';
                $taxable = !empty($component['taxable']);
                $amount = isset($component['amount']) ? (float) $component['amount'] : 0;
                $amountOverride = array_key_exists('amount_override', $component) ? $component['amount_override'] : null;
                $rateOverrideRaw = array_key_exists('rate_percent_override', $component) ? $component['rate_percent_override'] : null;
                $rateOverride = ($rateOverrideRaw === null || $rateOverrideRaw === '') ? null : (float) $rateOverrideRaw;
                $isExcluded = !empty($component['is_excluded']);
```

Then find the `employee_payroll_components` upsert:

```php
                DB::table('hris.employee_payroll_components')->updateOrInsert(
                    [
                        'payroll_component_id' => $componentId,
                        'userid' => $userid,
                    ],
                    [
                        'amount' => $amount,
                        'amount_override' => $amountOverride,
                        'is_active' => $isExcluded ? 0 : 1,
                        'is_excluded' => $isExcluded ? 1 : 0,
                        'effective_from' => $effectiveFrom,
                        'effective_to' => $effectiveTo,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
```

Replace with:

```php
                DB::table('hris.employee_payroll_components')->updateOrInsert(
                    [
                        'payroll_component_id' => $componentId,
                        'userid' => $userid,
                    ],
                    [
                        'amount' => $amount,
                        'amount_override' => $amountOverride,
                        'rate_percent_override' => $rateOverride,
                        'is_active' => $isExcluded ? 0 : 1,
                        'is_excluded' => $isExcluded ? 1 : 0,
                        'effective_from' => $effectiveFrom,
                        'effective_to' => $effectiveTo,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/PayrollComponentRateOverrideTest.php`
Expected: `Tests: 7 passed`.

- [ ] **Step 6: Run existing payroll suites to check for regressions**

Run: `php artisan test tests/Feature/PayrollComponentAdjustmentTest.php tests/Feature/EmployeePayrollComponentAssignmentTest.php tests/Feature/PayrollComponentFormulaTest.php`
Expected: every suite still fully passes — saving a component with no `rate_percent_override` key at all (as every one of these existing tests' payloads does) must still work and leave the column `null`.

- [ ] **Step 7: Commit**

```bash
git add app/Services/PayrollServices.php app/Http/Controllers/PayrollController.php tests/Feature/PayrollComponentRateOverrideTest.php
git commit -m "feat: validate and persist per-employee rate overrides on save"
```

---

### Task 4: End-to-end test — rate override through a full payroll run

**Files:**
- Modify: `tests/Feature/PayrollComponentRateOverrideTest.php`

- [ ] **Step 1: Write the failing test**

Add this method to `tests/Feature/PayrollComponentRateOverrideTest.php`, just before the class's closing `}`:

```php
    /** @test */
    public function a_rate_override_flows_through_the_full_payroll_computation_and_tracks_a_later_salary_change()
    {
        DB::table('hris.payroll_components')->insert([
            'code' => $this->componentCode,
            'name' => 'Test Rate Component',
            'type' => 'earning',
            'is_global' => 1,
            'taxable' => 1,
            'default_amount' => 0,
            'calculation_type' => 'percentage',
            'base_type' => 'basic_salary',
            'base_component_code' => null,
            'rate_percent' => 25,
            'is_active' => 1,
            'is_cutoff_adjustable' => 0,
            'sort_order' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->seedTestUser();

        $masterId = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title' => 'Rate Override Test Cutoff',
            'period_start' => '2027-10-01',
            'period_end' => '2027-10-15',
            'generated_by' => $this->wireTestUserId,
            'generated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payrollId = DB::table('hris.payrolls')->insertGetId([
            'cutoff_id' => $masterId,
            'title' => 'Rate Override Test Payroll',
            'start_date' => '2027-10-01',
            'end_date' => '2027-10-15',
            'cutoff_start_date' => '2027-10-01',
            'cutoff_end_date' => '2027-10-15',
            'cutoff_title' => 'Rate Override Test Cutoff',
            'payroll_type' => 'Regular',
            'rate_type' => 'Non Pro-rated',
            'status' => 'draft',
            'prepared_by' => $this->wireTestUserId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('hris.cutoff_summaries')->insert([
            'employee_id' => $this->wireTestUserId,
            'cutoff_id' => $masterId,
            'period_start' => '2027-10-01',
            'period_end' => '2027-10-15',
            'total_working_days' => 10,
            'days_present' => 10,
            'days_absent' => 0,
            'late_minutes' => 0,
            'undertime_minutes' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actAsAdmin();

        DB::table('hris.payroll_settings')->insert([
            'userid' => $this->wireTestUserId,
            'basic_salary' => 20000,
            'salary_grade' => 24,
            'step' => 1,
        ]);

        // Give this employee a 30% rate override (catalog default is 25%).
        $this->withoutMiddleware()->postJson('/save_payroll_settings', [
            'userid' => $this->wireTestUserId,
            'salary_grade' => 24,
            'step' => 1,
            'basic_salary' => 20000,
            'components' => [[
                'code' => $this->componentCode,
                'name' => 'Test Rate Component',
                'type' => 'earning',
                'taxable' => true,
                'is_global' => true,
                'default_amount' => 0,
                'amount_override' => null,
                'rate_percent_override' => 30,
                'is_excluded' => false,
                'effective_from' => null,
                'effective_to' => null,
            ]],
        ])->assertOk();

        $this->withoutMiddleware()->postJson('/save_payroll_employee', [
            'payroll_id' => $payrollId,
            'employees' => [[
                'userid' => $this->wireTestUserId,
                'salary_adjustment' => 0,
                'adjustments' => [],
            ]],
        ])->assertOk();

        $firstRun = DB::table('hris.payroll_employees')
            ->where('payroll_id', $payrollId)
            ->where('employee_id', $this->wireTestUserId)
            ->first();
        // 30% of 20,000 = 6,000 - the override rate, not the catalog's 25%.
        $this->assertEqualsWithDelta(6000.0, (float) $firstRun->other_earnings, 0.01);

        // A salary change between two runs: basic_salary goes from 20,000 to
        // 24,000. The rate override (30%) must still apply against the new
        // salary with no code change.
        DB::table('hris.payroll_settings')->where('userid', $this->wireTestUserId)
            ->update(['basic_salary' => 24000]);

        $this->withoutMiddleware()->postJson('/save_payroll_employee', [
            'payroll_id' => $payrollId,
            'employees' => [[
                'userid' => $this->wireTestUserId,
                'salary_adjustment' => 0,
                'adjustments' => [],
            ]],
        ])->assertOk();

        $secondRun = DB::table('hris.payroll_employees')
            ->where('payroll_id', $payrollId)
            ->where('employee_id', $this->wireTestUserId)
            ->first();
        // 30% of the new 24,000 basic salary = 7,200.
        $this->assertEqualsWithDelta(7200.0, (float) $secondRun->other_earnings, 0.01);

        DB::table('hris.payroll_employee_components')
            ->whereIn('payroll_employee_id', DB::table('hris.payroll_employees')->where('payroll_id', $payrollId)->pluck('id'))
            ->delete();
        DB::table('hris.payroll_employees')->where('payroll_id', $payrollId)->delete();
        DB::table('hris.payrolls')->where('id', $payrollId)->delete();
        DB::table('hris.cutoff_summaries')->where('cutoff_id', $masterId)->delete();
        DB::table('hris.cutoff_summaries_master')->where('id', $masterId)->delete();
    }
```

- [ ] **Step 2: Run the test**

Run: `php artisan test tests/Feature/PayrollComponentRateOverrideTest.php --filter a_rate_override_flows_through_the_full_payroll_computation_and_tracks_a_later_salary_change`
Expected: `Tests: 1 passed`. (If it fails, Tasks 2-3's wiring has a gap - every method this test calls already exists by this point.)

- [ ] **Step 3: Run the full existing DTR/payroll regression suite**

Run: `php artisan test tests/Feature/PayrollCutoffTest.php tests/Feature/PayrollComponentAdjustmentTest.php tests/Feature/PayrollTaxRuleTest.php tests/Feature/PayrollComponentFormulaTest.php tests/Feature/EmployeePayrollComponentAssignmentTest.php`
Expected: every suite passes with no failures.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/PayrollComponentRateOverrideTest.php
git commit -m "test: prove a per-employee rate override flows through payroll and tracks salary changes"
```

---

### Task 5: UI — Employee modal's Payroll tab

**Files:**
- Modify: `resources/views/forms/payroll.blade.php:105-129` (Earnings table) and `:131-155` (Deductions table)
- Modify: `resources/views/js/employee.blade.php:197-243` (save handler), `:723-754` (`addDynamicComponentRow()`), `:763-804` (`collectDynamicOverrides()`)

- [ ] **Step 1: Add the Rate Override column to both table headers**

In `resources/views/forms/payroll.blade.php`, find the Earnings table header:

```html
                    <tr>
                        <th style="width: 18%;">Earning</th>
                        <th style="width: 8%;">Global</th>
                        <th style="width: 12%;">Default</th>
                        <th style="width: 16%;">Amount</th>
                        <th style="width: 10%;">Exclude</th>
                        <th style="width: 14%;">Effective From</th>
                        <th style="width: 14%;">Effective To</th>
                    </tr>
```

Replace with:

```html
                    <tr>
                        <th style="width: 16%;">Earning</th>
                        <th style="width: 7%;">Global</th>
                        <th style="width: 11%;">Default</th>
                        <th style="width: 12%;">Amount</th>
                        <th style="width: 12%;">Rate Override %</th>
                        <th style="width: 8%;">Exclude</th>
                        <th style="width: 13%;">Effective From</th>
                        <th style="width: 13%;">Effective To</th>
                    </tr>
```

Find the Deductions table header (identical shape, `Earning` → `Deduction`):

```html
                    <tr>
                        <th style="width: 18%;">Deduction</th>
                        <th style="width: 8%;">Global</th>
                        <th style="width: 12%;">Default</th>
                        <th style="width: 16%;">Amount</th>
                        <th style="width: 10%;">Exclude</th>
                        <th style="width: 14%;">Effective From</th>
                        <th style="width: 14%;">Effective To</th>
                    </tr>
```

Replace with:

```html
                    <tr>
                        <th style="width: 16%;">Deduction</th>
                        <th style="width: 7%;">Global</th>
                        <th style="width: 11%;">Default</th>
                        <th style="width: 12%;">Amount</th>
                        <th style="width: 12%;">Rate Override %</th>
                        <th style="width: 8%;">Exclude</th>
                        <th style="width: 13%;">Effective From</th>
                        <th style="width: 13%;">Effective To</th>
                    </tr>
```

Also update both helper captions immediately below each table (currently `Global: leave Amount blank to use the default. Non-global: leave Amount blank to leave it unassigned for this employee.`) to:

```html
            <div class="small text-muted mt-2">
                Global: leave Amount blank to use the default. Non-global: leave Amount blank to leave it unassigned for this employee. Rate Override % only applies to percentage-based components and replaces the catalog's rate while still computing off basic salary.
            </div>
```

(Replace both occurrences - one under the Earnings table, one under the Deductions table.)

- [ ] **Step 2: Show the catalog's formula (not raw `default_amount`) in the Default column, and add the Rate Override cell**

In `resources/views/js/employee.blade.php`, find `addDynamicComponentRow()` (lines 723-754):

```javascript
    function addDynamicComponentRow(component = {}, override = {}, $body) {
        const code = component.code || "";
        const name = component.name || "";
        const isGlobal = !!component.is_global;
        const defaultAmount = component.default_amount !== undefined && component.default_amount !== null
            ? parseFloat(component.default_amount).toFixed(2)
            : "0.00";
        const overrideAmount = override.amount_override !== undefined && override.amount_override !== null
            ? parseFloat(override.amount_override).toFixed(2)
            : "";
        const excluded = override.is_excluded ? "checked" : "";
        const effectiveFrom = override.effective_from || "";
        const effectiveTo = override.effective_to || "";

        const row = `
            <tr data-code="${code}" data-default="${defaultAmount}">
                <td>
                    <div class="fw-semibold">${name}</div>
                    <div class="small text-muted">${code}</div>
                </td>
                <td>${isGlobal ? "Yes" : "No"}</td>
                <td>${isGlobal ? defaultAmount : "&mdash;"}</td>
                <td><input type="number" class="form-control form-control-sm component-amount" value="${overrideAmount}"></td>
                <td class="text-center">
                    <input type="checkbox" class="form-check-input component-exclude" ${excluded}>
                </td>
                <td><input type="date" class="form-control form-control-sm component-effective-from" value="${effectiveFrom}"></td>
                <td><input type="date" class="form-control form-control-sm component-effective-to" value="${effectiveTo}"></td>
            </tr>`;

        $body.append(row);
    }
```

Replace with:

```javascript
    function addDynamicComponentRow(component = {}, override = {}, $body) {
        const code = component.code || "";
        const name = component.name || "";
        const isGlobal = !!component.is_global;
        const isPercentage = component.calculation_type === "percentage";
        const defaultAmount = component.default_amount !== undefined && component.default_amount !== null
            ? parseFloat(component.default_amount).toFixed(2)
            : "0.00";
        const defaultDisplay = !isGlobal
            ? "&mdash;"
            : (isPercentage
                ? `${parseFloat(component.rate_percent || 0)}% of ${component.base_type === "basic_salary" ? "Basic Salary" : (component.base_component_code || "?")}`
                : defaultAmount);
        const overrideAmount = override.amount_override !== undefined && override.amount_override !== null
            ? parseFloat(override.amount_override).toFixed(2)
            : "";
        const rateOverride = override.rate_percent_override !== undefined && override.rate_percent_override !== null
            ? parseFloat(override.rate_percent_override)
            : "";
        const excluded = override.is_excluded ? "checked" : "";
        const effectiveFrom = override.effective_from || "";
        const effectiveTo = override.effective_to || "";
        const rateOverrideCell = isPercentage
            ? `<input type="number" class="form-control form-control-sm component-rate-override" step="0.001" value="${rateOverride}">`
            : "&mdash;";

        const row = `
            <tr data-code="${code}" data-default="${defaultAmount}">
                <td>
                    <div class="fw-semibold">${name}</div>
                    <div class="small text-muted">${code}</div>
                </td>
                <td>${isGlobal ? "Yes" : "No"}</td>
                <td>${defaultDisplay}</td>
                <td><input type="number" class="form-control form-control-sm component-amount" value="${overrideAmount}"></td>
                <td>${rateOverrideCell}</td>
                <td class="text-center">
                    <input type="checkbox" class="form-check-input component-exclude" ${excluded}>
                </td>
                <td><input type="date" class="form-control form-control-sm component-effective-from" value="${effectiveFrom}"></td>
                <td><input type="date" class="form-control form-control-sm component-effective-to" value="${effectiveTo}"></td>
            </tr>`;

        $body.append(row);
    }
```

- [ ] **Step 3: Read the Rate Override input when collecting the payload**

In `resources/views/js/employee.blade.php`, find `collectDynamicOverrides()` (lines 763-804):

```javascript
    function collectDynamicOverrides($rows, sourceComponents) {
        const components = [];

        $rows.each(function () {
            const $row = $(this);
            const code = $row.data("code");
            if (!code) {
                return;
            }
            const defaultAmount = parseFloat($row.data("default") || "0");
            const overrideRaw = $row.find(".component-amount").val();
            const overrideAmount = overrideRaw === "" ? null : parseFloat(overrideRaw);
            const excluded = $row.find(".component-exclude").is(":checked");
            const effectiveFrom = $row.find(".component-effective-from").val();
            const effectiveTo = $row.find(".component-effective-to").val();

            const hasOverride = overrideAmount !== null && !isNaN(overrideAmount) && overrideAmount !== defaultAmount;
            if (!excluded && !hasOverride && !effectiveFrom && !effectiveTo) {
                return;
            }

            const component = sourceComponents.find(item => item.code === code);
            if (!component) {
                return;
            }

            components.push({
                code: component.code,
                name: component.name,
                type: component.type,
                taxable: component.taxable,
                is_global: !!component.is_global,
                default_amount: parseFloat(component.default_amount || 0),
                amount_override: hasOverride ? overrideAmount : null,
                is_excluded: excluded,
                effective_from: effectiveFrom || null,
                effective_to: effectiveTo || null
            });
        });

        return components;
    }
```

Replace with:

```javascript
    function collectDynamicOverrides($rows, sourceComponents) {
        const components = [];

        $rows.each(function () {
            const $row = $(this);
            const code = $row.data("code");
            if (!code) {
                return;
            }
            const defaultAmount = parseFloat($row.data("default") || "0");
            const overrideRaw = $row.find(".component-amount").val();
            const overrideAmount = overrideRaw === "" ? null : parseFloat(overrideRaw);
            const rateOverrideRaw = $row.find(".component-rate-override").val();
            const rateOverride = (rateOverrideRaw === undefined || rateOverrideRaw === "") ? null : parseFloat(rateOverrideRaw);
            const excluded = $row.find(".component-exclude").is(":checked");
            const effectiveFrom = $row.find(".component-effective-from").val();
            const effectiveTo = $row.find(".component-effective-to").val();

            const hasOverride = overrideAmount !== null && !isNaN(overrideAmount) && overrideAmount !== defaultAmount;
            const hasRateOverride = rateOverride !== null && !isNaN(rateOverride);
            if (!excluded && !hasOverride && !hasRateOverride && !effectiveFrom && !effectiveTo) {
                return;
            }

            const component = sourceComponents.find(item => item.code === code);
            if (!component) {
                return;
            }

            components.push({
                code: component.code,
                name: component.name,
                type: component.type,
                taxable: component.taxable,
                is_global: !!component.is_global,
                default_amount: parseFloat(component.default_amount || 0),
                amount_override: hasOverride ? overrideAmount : null,
                rate_percent_override: hasRateOverride ? rateOverride : null,
                is_excluded: excluded,
                effective_from: effectiveFrom || null,
                effective_to: effectiveTo || null
            });
        });

        return components;
    }
```

- [ ] **Step 4: Surface save errors instead of always showing a success toast**

In `resources/views/js/employee.blade.php`, find the `#btn_save_payroll` click handler's fetch call (around line 228-243):

```javascript
            fetch('{{ route('savePayrollSettings') }}', {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    body: JSON.stringify(params)
                }
            )
                .then(res => res.json())
                .then(data => {
                    toastr.success(data.message, 'Information');
                }).catch(err => {
                console.log(err);
            });
        });
```

Replace with:

```javascript
            fetch('{{ route('savePayrollSettings') }}', {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    body: JSON.stringify(params)
                }
            )
                .then(res => res.json().then(data => ({ok: res.ok, data})))
                .then(({ok, data}) => {
                    if (!ok) {
                        toastr.error(data.message || "Failed to save payroll settings.", "Error");
                        return;
                    }
                    toastr.success(data.message, 'Information');
                }).catch(err => {
                console.log(err);
            });
        });
```

- [ ] **Step 5: Verify by rendering the page**

Add a temporary test, run it, then delete it (this feature has no dedicated permanent view-render test elsewhere in this codebase - manual round-trip and backend behavior are already covered by Tasks 2-4):

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class TempEmployeeRateOverrideUiTest extends TestCase
{
    /** @test */
    public function the_employee_payroll_tab_renders_the_rate_override_column()
    {
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);
        Auth::loginUsingId(1);

        $response = $this->get('/employee');
        $response->assertOk();
        $response->assertSee('Rate Override %', false);
        $response->assertSee('component-rate-override', false);
    }
}
```

Save it to `tests/Feature/TempEmployeeRateOverrideUiTest.php`, run:

Run: `php artisan view:clear && php artisan test tests/Feature/TempEmployeeRateOverrideUiTest.php`
Expected: `Tests: 1 passed`.

Then delete the file: `rm tests/Feature/TempEmployeeRateOverrideUiTest.php`

- [ ] **Step 6: Commit**

```bash
git add resources/views/forms/payroll.blade.php resources/views/js/employee.blade.php
git commit -m "feat: add rate override column to the employee payroll tab"
```

---

### Task 6: UI — Settings → Payroll Settings

**Files:**
- Modify: `resources/views/modals/payroll.blade.php:63-115` (Earnings/Deductions tables)
- Modify: `resources/views/settings/payroll.blade.php:159-207` (save handler), `:271-302` (`addDynamicComponentRow()`), `:311-352` (`collectDynamicOverrides()`)

This mirrors Task 5 exactly, applied to the second UI surface (same duplicated JS pattern already established for the Global column in a prior change).

- [ ] **Step 1: Add the Rate Override column to both table headers**

In `resources/views/modals/payroll.blade.php`, find the Earnings table header:

```html
                                <tr>
                                    <th style="width: 18%;">Earning</th>
                                    <th style="width: 8%;">Global</th>
                                    <th style="width: 12%;">Default</th>
                                    <th style="width: 16%;">Amount</th>
                                    <th style="width: 10%;">Exclude</th>
                                    <th style="width: 14%;">Effective From</th>
                                    <th style="width: 14%;">Effective To</th>
                                </tr>
```

Replace with:

```html
                                <tr>
                                    <th style="width: 16%;">Earning</th>
                                    <th style="width: 7%;">Global</th>
                                    <th style="width: 11%;">Default</th>
                                    <th style="width: 12%;">Amount</th>
                                    <th style="width: 12%;">Rate Override %</th>
                                    <th style="width: 8%;">Exclude</th>
                                    <th style="width: 13%;">Effective From</th>
                                    <th style="width: 13%;">Effective To</th>
                                </tr>
```

Find the Deductions table header (identical shape, `Earning` → `Deduction`):

```html
                                <tr>
                                    <th style="width: 18%;">Deduction</th>
                                    <th style="width: 8%;">Global</th>
                                    <th style="width: 12%;">Default</th>
                                    <th style="width: 16%;">Amount</th>
                                    <th style="width: 10%;">Exclude</th>
                                    <th style="width: 14%;">Effective From</th>
                                    <th style="width: 14%;">Effective To</th>
                                </tr>
```

Replace with:

```html
                                <tr>
                                    <th style="width: 16%;">Deduction</th>
                                    <th style="width: 7%;">Global</th>
                                    <th style="width: 11%;">Default</th>
                                    <th style="width: 12%;">Amount</th>
                                    <th style="width: 12%;">Rate Override %</th>
                                    <th style="width: 8%;">Exclude</th>
                                    <th style="width: 13%;">Effective From</th>
                                    <th style="width: 13%;">Effective To</th>
                                </tr>
```

Also find both occurrences of this helper caption (one under the Earnings table, one under the Deductions table):

```html
                        <div class="small text-muted">
                            Global: leave Amount blank to use the default. Non-global: leave Amount blank to leave it unassigned for this employee.
                        </div>
```

Replace each with:

```html
                        <div class="small text-muted">
                            Global: leave Amount blank to use the default. Non-global: leave Amount blank to leave it unassigned for this employee. Rate Override % only applies to percentage-based components and replaces the catalog's rate while still computing off basic salary.
                        </div>
```

- [ ] **Step 2: Show the catalog's formula in Default, and add the Rate Override cell**

In `resources/views/settings/payroll.blade.php`, find `addDynamicComponentRow()` (lines 271-302):

```javascript
        function addDynamicComponentRow(component = {}, override = {}, $body) {
            const code = component.code || "";
            const name = component.name || "";
            const isGlobal = !!component.is_global;
            const defaultAmount = component.default_amount !== undefined && component.default_amount !== null
                ? parseFloat(component.default_amount).toFixed(2)
                : "0.00";
            const overrideAmount = override.amount_override !== undefined && override.amount_override !== null
                ? parseFloat(override.amount_override).toFixed(2)
                : "";
            const excluded = override.is_excluded ? "checked" : "";
            const effectiveFrom = override.effective_from || "";
            const effectiveTo = override.effective_to || "";

            const row = `
                <tr data-code="${code}" data-default="${defaultAmount}">
                    <td>
                        <div class="fw-semibold">${name}</div>
                        <div class="small text-muted">${code}</div>
                    </td>
                    <td>${isGlobal ? "Yes" : "No"}</td>
                    <td>${isGlobal ? defaultAmount : "&mdash;"}</td>
                    <td><input type="number" class="form-control form-control-sm component-amount" value="${overrideAmount}"></td>
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input component-exclude" ${excluded}>
                    </td>
                    <td><input type="date" class="form-control form-control-sm component-effective-from" value="${effectiveFrom}"></td>
                    <td><input type="date" class="form-control form-control-sm component-effective-to" value="${effectiveTo}"></td>
                </tr>`;

            $body.append(row);
        }
```

Replace with:

```javascript
        function addDynamicComponentRow(component = {}, override = {}, $body) {
            const code = component.code || "";
            const name = component.name || "";
            const isGlobal = !!component.is_global;
            const isPercentage = component.calculation_type === "percentage";
            const defaultAmount = component.default_amount !== undefined && component.default_amount !== null
                ? parseFloat(component.default_amount).toFixed(2)
                : "0.00";
            const defaultDisplay = !isGlobal
                ? "&mdash;"
                : (isPercentage
                    ? `${parseFloat(component.rate_percent || 0)}% of ${component.base_type === "basic_salary" ? "Basic Salary" : (component.base_component_code || "?")}`
                    : defaultAmount);
            const overrideAmount = override.amount_override !== undefined && override.amount_override !== null
                ? parseFloat(override.amount_override).toFixed(2)
                : "";
            const rateOverride = override.rate_percent_override !== undefined && override.rate_percent_override !== null
                ? parseFloat(override.rate_percent_override)
                : "";
            const excluded = override.is_excluded ? "checked" : "";
            const effectiveFrom = override.effective_from || "";
            const effectiveTo = override.effective_to || "";
            const rateOverrideCell = isPercentage
                ? `<input type="number" class="form-control form-control-sm component-rate-override" step="0.001" value="${rateOverride}">`
                : "&mdash;";

            const row = `
                <tr data-code="${code}" data-default="${defaultAmount}">
                    <td>
                        <div class="fw-semibold">${name}</div>
                        <div class="small text-muted">${code}</div>
                    </td>
                    <td>${isGlobal ? "Yes" : "No"}</td>
                    <td>${defaultDisplay}</td>
                    <td><input type="number" class="form-control form-control-sm component-amount" value="${overrideAmount}"></td>
                    <td>${rateOverrideCell}</td>
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input component-exclude" ${excluded}>
                    </td>
                    <td><input type="date" class="form-control form-control-sm component-effective-from" value="${effectiveFrom}"></td>
                    <td><input type="date" class="form-control form-control-sm component-effective-to" value="${effectiveTo}"></td>
                </tr>`;

            $body.append(row);
        }
```

- [ ] **Step 3: Read the Rate Override input when collecting the payload**

In `resources/views/settings/payroll.blade.php`, find `collectDynamicOverrides()` (lines 311-352):

```javascript
        function collectDynamicOverrides($rows, sourceComponents) {
            const components = [];

            $rows.each(function () {
                const $row = $(this);
                const code = $row.data("code");
                if (!code) {
                    return;
                }
                const defaultAmount = parseFloat($row.data("default") || "0");
                const overrideRaw = $row.find(".component-amount").val();
                const overrideAmount = overrideRaw === "" ? null : parseFloat(overrideRaw);
                const excluded = $row.find(".component-exclude").is(":checked");
                const effectiveFrom = $row.find(".component-effective-from").val();
                const effectiveTo = $row.find(".component-effective-to").val();

                const hasOverride = overrideAmount !== null && !isNaN(overrideAmount) && overrideAmount !== defaultAmount;
                if (!excluded && !hasOverride && !effectiveFrom && !effectiveTo) {
                    return;
                }

                const component = sourceComponents.find(item => item.code === code);
                if (!component) {
                    return;
                }

                components.push({
                    code: component.code,
                    name: component.name,
                    type: component.type,
                    taxable: component.taxable,
                    is_global: !!component.is_global,
                    default_amount: parseFloat(component.default_amount || 0),
                    amount_override: hasOverride ? overrideAmount : null,
                    is_excluded: excluded,
                    effective_from: effectiveFrom || null,
                    effective_to: effectiveTo || null
                });
            });

            return components;
        }
```

Replace with:

```javascript
        function collectDynamicOverrides($rows, sourceComponents) {
            const components = [];

            $rows.each(function () {
                const $row = $(this);
                const code = $row.data("code");
                if (!code) {
                    return;
                }
                const defaultAmount = parseFloat($row.data("default") || "0");
                const overrideRaw = $row.find(".component-amount").val();
                const overrideAmount = overrideRaw === "" ? null : parseFloat(overrideRaw);
                const rateOverrideRaw = $row.find(".component-rate-override").val();
                const rateOverride = (rateOverrideRaw === undefined || rateOverrideRaw === "") ? null : parseFloat(rateOverrideRaw);
                const excluded = $row.find(".component-exclude").is(":checked");
                const effectiveFrom = $row.find(".component-effective-from").val();
                const effectiveTo = $row.find(".component-effective-to").val();

                const hasOverride = overrideAmount !== null && !isNaN(overrideAmount) && overrideAmount !== defaultAmount;
                const hasRateOverride = rateOverride !== null && !isNaN(rateOverride);
                if (!excluded && !hasOverride && !hasRateOverride && !effectiveFrom && !effectiveTo) {
                    return;
                }

                const component = sourceComponents.find(item => item.code === code);
                if (!component) {
                    return;
                }

                components.push({
                    code: component.code,
                    name: component.name,
                    type: component.type,
                    taxable: component.taxable,
                    is_global: !!component.is_global,
                    default_amount: parseFloat(component.default_amount || 0),
                    amount_override: hasOverride ? overrideAmount : null,
                    rate_percent_override: hasRateOverride ? rateOverride : null,
                    is_excluded: excluded,
                    effective_from: effectiveFrom || null,
                    effective_to: effectiveTo || null
                });
            });

            return components;
        }
```

- [ ] **Step 4: Surface save errors instead of always showing a success toast**

In `resources/views/settings/payroll.blade.php`, find the `#btn_save` click handler's fetch call (around line 192-207):

```javascript
                fetch('{{ route('savePayrollSettings') }}', {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        body: JSON.stringify(params)
                    }
                )
                    .then(res => res.json())
                    .then(data => {
                        toastr.success(data.message, 'Information');
                    }).catch(err => {
                    console.log(err);
                });
            });
```

Replace with:

```javascript
                fetch('{{ route('savePayrollSettings') }}', {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        body: JSON.stringify(params)
                    }
                )
                    .then(res => res.json().then(data => ({ok: res.ok, data})))
                    .then(({ok, data}) => {
                        if (!ok) {
                            toastr.error(data.message || "Failed to save payroll settings.", "Error");
                            return;
                        }
                        toastr.success(data.message, 'Information');
                    }).catch(err => {
                    console.log(err);
                });
            });
```

- [ ] **Step 5: Verify by rendering the page**

Add a temporary test, run it, then delete it:

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class TempSettingsRateOverrideUiTest extends TestCase
{
    /** @test */
    public function the_settings_payroll_page_renders_the_rate_override_column()
    {
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);
        Auth::loginUsingId(1);

        $response = $this->get('/payroll_settings');
        $response->assertOk();
        $response->assertSee('Rate Override %', false);
        $response->assertSee('component-rate-override', false);
    }
}
```

Save it to `tests/Feature/TempSettingsRateOverrideUiTest.php`, run:

Run: `php artisan view:clear && php artisan test tests/Feature/TempSettingsRateOverrideUiTest.php`
Expected: `Tests: 1 passed`.

Then delete the file: `rm tests/Feature/TempSettingsRateOverrideUiTest.php`

- [ ] **Step 6: Commit**

```bash
git add resources/views/modals/payroll.blade.php resources/views/settings/payroll.blade.php
git commit -m "feat: add rate override column to the Settings payroll page"
```

---

### Task 7: Final regression pass

**Files:** none (verification only)

- [ ] **Step 1: Run the full payroll/DTR regression suite**

Run: `php artisan test tests/Feature/PayrollComponentRateOverrideTest.php tests/Feature/PayrollComponentFormulaTest.php tests/Feature/PayrollComponentAdjustmentTest.php tests/Feature/EmployeePayrollComponentAssignmentTest.php tests/Feature/PayrollCutoffTest.php tests/Feature/PayrollTaxRuleTest.php tests/Feature/CutoffSummaryDaysTest.php tests/Unit/DtrServicesUnifiedComputationTest.php tests/Feature/DtrPrintCharacterizationTest.php`

(Run each file separately if `php artisan test` only executes the first path passed - confirmed behavior in this environment - rather than assuming multi-file invocation works.)

Expected: every suite passes with no failures.

- [ ] **Step 2: Confirm no stray temp test files remain**

Run: `git status --short`
Expected: no untracked `Temp*Test.php` files under `tests/Feature/`.
