# Computed Payroll Components Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a `payroll_components` row be defined as a percentage of Basic Salary or of another component's resolved amount (e.g. Hazard Pay = 25% of Basic Salary), instead of only a flat `default_amount`, so a future rate/policy change is a Settings-page data edit, not a code change.

**Architecture:** Four new columns on `hris.payroll_components` (`calculation_type`, `base_type`, `base_component_code`, `rate_percent`). `PayrollServices` gains a dependency-ordered resolution algorithm (topological sort + per-row resolve, 3-decimal rounding) used by `getEmployeeDynamicComponents()`, and a `validateComponentFormulas()` guard (mirroring the existing `validateTaxRuleSet()` pattern) called from `PayrollController::savePayrollComponents()` before anything is persisted, rejecting self-references and circular chains. The Settings → Payroll Components UI gains a Computation Type toggle (Fixed / Percentage) with a Base picker and rate input.

**Tech Stack:** Laravel 8 / PHP, MySQL (`hris` schema, `payroll_components` table is MyISAM), Blade + jQuery + Select2, PHPUnit (`DatabaseTransactions`, with explicit manual cleanup since MyISAM tables don't honor transactional rollback in this environment).

**Reference spec:** `docs/superpowers/specs/2026-07-10-computed-payroll-components-design.md`

---

### Task 1: Migration — add calculation columns to `payroll_components`

**Files:**
- Create: `database/migrations/2026_07_10_160000_add_calculation_fields_to_payroll_components_table.php`

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets a payroll_components row be defined as a percentage of Basic
     * Salary or of another component's resolved amount, instead of only a
     * flat default_amount - see
     * docs/superpowers/specs/2026-07-10-computed-payroll-components-design.md.
     * Every existing row defaults to calculation_type = 'fixed', which
     * computes identically to today's behavior.
     */
    public function up()
    {
        Schema::table('payroll_components', function (Blueprint $table) {
            $table->enum('calculation_type', ['fixed', 'percentage'])->default('fixed')->after('default_amount');
            $table->enum('base_type', ['basic_salary', 'component'])->nullable()->after('calculation_type');
            $table->string('base_component_code', 100)->nullable()->after('base_type');
            $table->decimal('rate_percent', 8, 3)->nullable()->after('base_component_code');
        });
    }

    public function down()
    {
        Schema::table('payroll_components', function (Blueprint $table) {
            $table->dropColumn(['calculation_type', 'base_type', 'base_component_code', 'rate_percent']);
        });
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `php artisan migrate`
Expected: `Migrating: 2026_07_10_160000_add_calculation_fields_to_payroll_components_table` then `Migrated:` with no errors.

- [ ] **Step 3: Verify the columns exist**

Run:
```bash
php artisan tinker --execute="echo json_encode(DB::selectOne(\"SHOW COLUMNS FROM hris.payroll_components WHERE Field = 'calculation_type'\"));"
```
Expected: JSON showing `calculation_type` with `Type: "enum('fixed','percentage')"` and `Default: "fixed"`.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_07_10_160000_add_calculation_fields_to_payroll_components_table.php
git commit -m "feat: add calculation fields to payroll_components for computed amounts"
```

---

### Task 2: `PayrollServices::validateComponentFormulas()` — save-time validation

**Files:**
- Modify: `app/Services/PayrollServices.php` (add method after `taxRangesOverlap()`, currently ending at `app/Services/PayrollServices.php:452`)
- Create: `tests/Feature/PayrollComponentFormulaTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Services\PayrollServices;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class PayrollComponentFormulaTest extends TestCase
{
    use DatabaseTransactions;

    private array $testCodes = ['TEST_FORMULA_A', 'TEST_FORMULA_B', 'TEST_FORMULA_C'];

    protected function setUp(): void
    {
        parent::setUp();
        // APP_URL contains a subfolder (/hris) which breaks test HTTP client path matching.
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);
        $this->cleanupTestComponents();
    }

    protected function tearDown(): void
    {
        // payroll_components is MyISAM in this environment, so
        // DatabaseTransactions rollback is a silent no-op - clean up explicitly.
        $this->cleanupTestComponents();
        parent::tearDown();
    }

    private function cleanupTestComponents(): void
    {
        DB::table('hris.payroll_components')->whereIn('code', $this->testCodes)->delete();
    }

    private function actAsAdmin(): void
    {
        Auth::shouldReceive('id')->andReturn(9601);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'admin']);
    }

    /** @test */
    public function rejects_a_percentage_component_with_no_base_selected()
    {
        $service = app(PayrollServices::class);

        $error = $service->validateComponentFormulas([
            'TEST_FORMULA_A' => [
                'calculation_type' => 'percentage',
                'base_type' => null,
                'base_component_code' => null,
                'rate_percent' => 25,
            ],
        ]);

        $this->assertNotNull($error);
        $this->assertStringContainsString('no valid base type', $error);
    }

    /** @test */
    public function rejects_a_component_that_references_itself_as_its_base()
    {
        $service = app(PayrollServices::class);

        $error = $service->validateComponentFormulas([
            'TEST_FORMULA_A' => [
                'calculation_type' => 'percentage',
                'base_type' => 'component',
                'base_component_code' => 'TEST_FORMULA_A',
                'rate_percent' => 25,
            ],
        ]);

        $this->assertNotNull($error);
        $this->assertStringContainsString('cannot reference itself', $error);
    }

    /** @test */
    public function rejects_a_reference_to_an_unknown_component()
    {
        $service = app(PayrollServices::class);

        $error = $service->validateComponentFormulas([
            'TEST_FORMULA_A' => [
                'calculation_type' => 'percentage',
                'base_type' => 'component',
                'base_component_code' => 'DOES_NOT_EXIST',
                'rate_percent' => 25,
            ],
        ]);

        $this->assertNotNull($error);
        $this->assertStringContainsString('unknown base component', $error);
    }

    /** @test */
    public function rejects_a_two_hop_circular_reference()
    {
        $service = app(PayrollServices::class);

        $error = $service->validateComponentFormulas([
            'TEST_FORMULA_A' => [
                'calculation_type' => 'percentage',
                'base_type' => 'component',
                'base_component_code' => 'TEST_FORMULA_B',
                'rate_percent' => 25,
            ],
            'TEST_FORMULA_B' => [
                'calculation_type' => 'percentage',
                'base_type' => 'component',
                'base_component_code' => 'TEST_FORMULA_A',
                'rate_percent' => 10,
            ],
        ]);

        $this->assertNotNull($error);
        $this->assertStringContainsString('Circular reference', $error);
    }

    /** @test */
    public function rejects_a_negative_percentage_rate()
    {
        $service = app(PayrollServices::class);

        $error = $service->validateComponentFormulas([
            'TEST_FORMULA_A' => [
                'calculation_type' => 'percentage',
                'base_type' => 'basic_salary',
                'base_component_code' => null,
                'rate_percent' => -5,
            ],
        ]);

        $this->assertNotNull($error);
        $this->assertStringContainsString('invalid percentage rate', $error);
    }

    /** @test */
    public function accepts_a_valid_chained_percentage_set()
    {
        $service = app(PayrollServices::class);

        $error = $service->validateComponentFormulas([
            'TEST_FORMULA_A' => [
                'calculation_type' => 'fixed',
                'base_type' => null,
                'base_component_code' => null,
                'rate_percent' => null,
            ],
            'TEST_FORMULA_B' => [
                'calculation_type' => 'percentage',
                'base_type' => 'basic_salary',
                'base_component_code' => null,
                'rate_percent' => 25,
            ],
            'TEST_FORMULA_C' => [
                'calculation_type' => 'percentage',
                'base_type' => 'component',
                'base_component_code' => 'TEST_FORMULA_B',
                'rate_percent' => 10,
            ],
        ]);

        $this->assertNull($error);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/PayrollComponentFormulaTest.php`
Expected: FAIL — `Call to undefined method App\Services\PayrollServices::validateComponentFormulas()`.

- [ ] **Step 3: Implement `validateComponentFormulas()`**

In `app/Services/PayrollServices.php`, add this method immediately after `taxRangesOverlap()` (currently `app/Services/PayrollServices.php:444-452`):

```php
    /**
     * Checks a resulting set of components (existing + incoming, keyed by
     * code) for formula problems before anything is persisted: a percentage
     * component must name a valid, non-self base and a non-negative rate,
     * and its base chain must not loop back to itself. Returns a
     * human-readable error, or null if the set is consistent.
     *
     * @param array<string, array{calculation_type: string, base_type: ?string, base_component_code: ?string, rate_percent: mixed}> $componentsByCode
     */
    public function validateComponentFormulas(array $componentsByCode): ?string
    {
        foreach ($componentsByCode as $code => $component) {
            if (($component['calculation_type'] ?? 'fixed') !== 'percentage') {
                continue;
            }

            $baseType = $component['base_type'] ?? null;
            $baseCode = $component['base_component_code'] ?? null;
            $rate = $component['rate_percent'] ?? null;

            if ($baseType === 'component') {
                if (empty($baseCode)) {
                    return "Component \"{$code}\" is percentage-based but has no base component selected.";
                }
                if ($baseCode === $code) {
                    return "Component \"{$code}\" cannot reference itself as its base.";
                }
                if (!array_key_exists($baseCode, $componentsByCode)) {
                    return "Component \"{$code}\" references unknown base component \"{$baseCode}\".";
                }

                $visited = [$code => true];
                $current = $baseCode;
                while ($current !== null) {
                    if (isset($visited[$current])) {
                        return "Circular reference detected: component \"{$code}\" eventually references itself through \"{$current}\".";
                    }
                    $visited[$current] = true;
                    $currentComponent = $componentsByCode[$current] ?? null;
                    if (!$currentComponent
                        || ($currentComponent['calculation_type'] ?? 'fixed') !== 'percentage'
                        || ($currentComponent['base_type'] ?? null) !== 'component') {
                        break;
                    }
                    $current = $currentComponent['base_component_code'] ?? null;
                }
            } elseif ($baseType !== 'basic_salary') {
                return "Component \"{$code}\" is percentage-based but has no valid base type.";
            }

            if ($rate === null || (float) $rate < 0) {
                return "Component \"{$code}\" has an invalid percentage rate.";
            }
        }

        return null;
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/PayrollComponentFormulaTest.php`
Expected: `Tests: 6 passed`.

- [ ] **Step 5: Commit**

```bash
git add app/Services/PayrollServices.php tests/Feature/PayrollComponentFormulaTest.php
git commit -m "feat: validate payroll component formulas for self-reference and cycles"
```

---

### Task 3: Resolution algorithm — topological sort + per-row resolve

**Files:**
- Modify: `app/Services/PayrollServices.php` (add methods after `getEmployeeDynamicComponents()`, currently ending at `app/Services/PayrollServices.php:731`)
- Modify: `tests/Feature/PayrollComponentFormulaTest.php`

- [ ] **Step 1: Write the failing tests**

Add these methods to `tests/Feature/PayrollComponentFormulaTest.php`, just before the class's closing `}`:

```php
    /** @test */
    public function resolves_a_basic_salary_percentage_component()
    {
        $rows = collect([
            (object) [
                'code' => 'TEST_FORMULA_A',
                'default_amount' => 0,
                'amount_override' => null,
                'calculation_type' => 'percentage',
                'base_type' => 'basic_salary',
                'base_component_code' => null,
                'rate_percent' => 25,
            ],
        ]);

        $resolved = $this->callResolveComponentAmounts($rows, 20000.0);

        $this->assertEqualsWithDelta(5000.0, $resolved->firstWhere('code', 'TEST_FORMULA_A')->amount, 0.001);
    }

    /** @test */
    public function resolves_a_component_chained_off_another_computed_component_regardless_of_payload_order()
    {
        $rows = collect([
            (object) [
                'code' => 'TEST_FORMULA_B',
                'default_amount' => 0,
                'amount_override' => null,
                'calculation_type' => 'percentage',
                'base_type' => 'component',
                'base_component_code' => 'TEST_FORMULA_A',
                'rate_percent' => 10,
            ],
            (object) [
                'code' => 'TEST_FORMULA_A',
                'default_amount' => 0,
                'amount_override' => null,
                'calculation_type' => 'percentage',
                'base_type' => 'basic_salary',
                'base_component_code' => null,
                'rate_percent' => 25,
            ],
        ]);

        // B is listed before A in $rows, but B depends on A - proving
        // resolution order follows the dependency graph, not payload order.
        $resolved = $this->callResolveComponentAmounts($rows, 20000.0);

        $this->assertEqualsWithDelta(5000.0, $resolved->firstWhere('code', 'TEST_FORMULA_A')->amount, 0.001);
        $this->assertEqualsWithDelta(500.0, $resolved->firstWhere('code', 'TEST_FORMULA_B')->amount, 0.001);
    }

    /** @test */
    public function an_amount_override_wins_over_the_formula_and_feeds_dependents()
    {
        $rows = collect([
            (object) [
                'code' => 'TEST_FORMULA_A',
                'default_amount' => 0,
                'amount_override' => 9999.0,
                'calculation_type' => 'percentage',
                'base_type' => 'basic_salary',
                'base_component_code' => null,
                'rate_percent' => 25,
            ],
            (object) [
                'code' => 'TEST_FORMULA_B',
                'default_amount' => 0,
                'amount_override' => null,
                'calculation_type' => 'percentage',
                'base_type' => 'component',
                'base_component_code' => 'TEST_FORMULA_A',
                'rate_percent' => 10,
            ],
        ]);

        $resolved = $this->callResolveComponentAmounts($rows, 20000.0);

        $this->assertEqualsWithDelta(9999.0, $resolved->firstWhere('code', 'TEST_FORMULA_A')->amount, 0.001);
        // 10% of the override (9,999), not of the 25%-of-basic-salary formula.
        $this->assertEqualsWithDelta(999.9, $resolved->firstWhere('code', 'TEST_FORMULA_B')->amount, 0.001);
    }

    /** @test */
    public function rounds_computed_amounts_to_three_decimal_places()
    {
        $rows = collect([
            (object) [
                'code' => 'TEST_FORMULA_A',
                'default_amount' => 0,
                'amount_override' => null,
                'calculation_type' => 'percentage',
                'base_type' => 'basic_salary',
                'base_component_code' => null,
                'rate_percent' => 33.333,
            ],
        ]);

        $resolved = $this->callResolveComponentAmounts($rows, 100.0);

        $this->assertEqualsWithDelta(33.333, $resolved->firstWhere('code', 'TEST_FORMULA_A')->amount, 0.0005);
    }

    /** @test */
    public function a_cyclic_pair_resolves_to_zero_instead_of_looping_forever()
    {
        $rows = collect([
            (object) [
                'code' => 'TEST_FORMULA_A',
                'default_amount' => 0,
                'amount_override' => null,
                'calculation_type' => 'percentage',
                'base_type' => 'component',
                'base_component_code' => 'TEST_FORMULA_B',
                'rate_percent' => 25,
            ],
            (object) [
                'code' => 'TEST_FORMULA_B',
                'default_amount' => 0,
                'amount_override' => null,
                'calculation_type' => 'percentage',
                'base_type' => 'component',
                'base_component_code' => 'TEST_FORMULA_A',
                'rate_percent' => 10,
            ],
        ]);

        // Defensive backstop only - validateComponentFormulas() (Task 2) is
        // what actually keeps this shape out of real saved data.
        $resolved = $this->callResolveComponentAmounts($rows, 20000.0);

        $this->assertEqualsWithDelta(0.0, $resolved->firstWhere('code', 'TEST_FORMULA_A')->amount, 0.001);
        $this->assertEqualsWithDelta(0.0, $resolved->firstWhere('code', 'TEST_FORMULA_B')->amount, 0.001);
    }

    private function callResolveComponentAmounts(\Illuminate\Support\Collection $rows, float $basicSalary): \Illuminate\Support\Collection
    {
        $service = app(PayrollServices::class);
        $method = new ReflectionMethod(PayrollServices::class, 'resolveComponentAmounts');
        $method->setAccessible(true);
        return $method->invoke($service, $rows, $basicSalary);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/PayrollComponentFormulaTest.php`
Expected: FAIL — `Method App\Services\PayrollServices::resolveComponentAmounts() does not exist`.

- [ ] **Step 3: Implement the resolution methods**

In `app/Services/PayrollServices.php`, add these three methods immediately after `getEmployeeDynamicComponents()` (currently ending at `app/Services/PayrollServices.php:731`), before `getCutoffAdjustableComponents()`:

```php
    /**
     * Resolves each row's final 'amount' in dependency order (a component's
     * base is resolved before the component that references it), per
     * docs/superpowers/specs/2026-07-10-computed-payroll-components-design.md.
     * Returns the same rows with 'amount' set; every other field untouched.
     */
    private function resolveComponentAmounts(\Illuminate\Support\Collection $rows, float $basicSalary): \Illuminate\Support\Collection
    {
        $byCode = $rows->keyBy('code');
        $order = $this->topoSortComponentCodes($byCode);

        $resolved = [];
        foreach ($order as $code) {
            $resolved[$code] = $this->resolveComponentAmount($byCode[$code], $resolved, $basicSalary);
        }

        return $rows->map(function ($row) use ($resolved) {
            $row->amount = $resolved[$row->code] ?? 0.0;
            return $row;
        });
    }

    /**
     * A component in a reference cycle is deliberately left out of
     * $resolved by topoSortComponentCodes() (it logs a warning and bails
     * before recording that code) - the `?? 0.0` fallback below means every
     * component in the cycle silently resolves to 0 rather than looping
     * forever. Save-time validation (validateComponentFormulas()) is what
     * actually keeps cycles out of the database; this is just a defensive
     * backstop.
     */
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

    /**
     * Post-order DFS: a component's base (if any) is appended to $order
     * before the component itself, so resolveComponentAmount() can always
     * look up an already-resolved base. A code revisited while still
     * 'visiting' is a cycle - logged and left out of $order (and therefore
     * out of $resolved in resolveComponentAmounts()).
     */
    private function topoSortComponentCodes(\Illuminate\Support\Collection $byCode): array
    {
        $order = [];
        $state = [];

        $visit = function ($code) use (&$visit, &$order, &$state, $byCode) {
            if (($state[$code] ?? null) === 'done') {
                return;
            }
            if (($state[$code] ?? null) === 'visiting') {
                \Illuminate\Support\Facades\Log::warning("Payroll component formula cycle detected involving \"{$code}\"; treating as 0.");
                return;
            }

            $state[$code] = 'visiting';
            $row = $byCode->get($code);
            if ($row && ($row->calculation_type ?? 'fixed') === 'percentage' && ($row->base_type ?? null) === 'component') {
                $baseCode = $row->base_component_code;
                if ($baseCode !== null && $byCode->has($baseCode)) {
                    $visit($baseCode);
                }
            }
            $state[$code] = 'done';
            $order[] = $code;
        };

        foreach ($byCode->keys() as $code) {
            $visit($code);
        }

        return $order;
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/PayrollComponentFormulaTest.php`
Expected: `Tests: 11 passed`.

- [ ] **Step 5: Commit**

```bash
git add app/Services/PayrollServices.php tests/Feature/PayrollComponentFormulaTest.php
git commit -m "feat: resolve percentage-based payroll components in dependency order"
```

---

### Task 4: Wire resolution into `getEmployeeDynamicComponents()` and `savePayrollEmployee()`

**Files:**
- Modify: `app/Services/PayrollServices.php:573` (call site) and `:652-731` (`getEmployeeDynamicComponents()`)
- Modify: `tests/Feature/PayrollComponentFormulaTest.php`

- [ ] **Step 1: Write the failing tests**

First, add a second test-user property and extend `tearDown()`. Replace the existing `tearDown()` method in `tests/Feature/PayrollComponentFormulaTest.php` with:

```php
    private int $wireTestUserId = 9602;

    protected function tearDown(): void
    {
        // payroll_components/payroll_settings/tdh_user.users/
        // employee_payroll_components are MyISAM in this environment, so
        // DatabaseTransactions rollback is a silent no-op - clean up explicitly.
        DB::table('hris.employee_payroll_components')->where('userid', $this->wireTestUserId)->delete();
        DB::table('hris.payroll_settings')->where('userid', $this->wireTestUserId)->delete();
        DB::table('tdh_user.users')->where('id', $this->wireTestUserId)->delete();
        $this->cleanupTestComponents();
        parent::tearDown();
    }
```

(This replaces the `private array $testCodes = [...]` line's neighboring `tearDown()` — keep the existing `$testCodes` property and `cleanupTestComponents()`/`actAsAdmin()` methods as they are; only `tearDown()` changes, and the new `$wireTestUserId` property is added alongside `$testCodes`.)

Then add this test just before the class's closing `}`:

```php
    /** @test */
    public function get_employee_dynamic_components_resolves_a_percentage_component_against_the_employees_basic_salary()
    {
        DB::table('tdh_user.users')->where('id', $this->wireTestUserId)->delete();
        DB::table('tdh_user.users')->insert([
            'id' => $this->wireTestUserId,
            'fname' => 'Formula',
            'lname' => 'Tester',
            'username' => 'formulatester' . $this->wireTestUserId,
            'password' => 'x',
            'division' => 2,
            'section' => 21,
            'designation' => 52,
        ]);

        DB::table('hris.payroll_settings')->where('userid', $this->wireTestUserId)->delete();
        DB::table('hris.payroll_settings')->insert([
            'userid' => $this->wireTestUserId,
            'basic_salary' => 20000,
            'salary_grade' => 11,
            'step' => 1,
        ]);

        DB::table('hris.payroll_components')->insert([
            'code' => 'TEST_FORMULA_A',
            'name' => 'Test Hazard Pay',
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

        $service = app(PayrollServices::class);
        $method = new ReflectionMethod(PayrollServices::class, 'getEmployeeDynamicComponents');
        $method->setAccessible(true);
        $components = $method->invoke($service, $this->wireTestUserId, '2027-07-01', '2027-07-15', 20000.0);

        $component = $components->firstWhere('code', 'TEST_FORMULA_A');
        $this->assertNotNull($component);
        $this->assertEqualsWithDelta(5000.0, $component->amount, 0.001);
    }

    /** @test */
    public function a_base_component_excluded_for_the_employee_resolves_its_dependent_to_zero()
    {
        DB::table('tdh_user.users')->where('id', $this->wireTestUserId)->delete();
        DB::table('tdh_user.users')->insert([
            'id' => $this->wireTestUserId,
            'fname' => 'Formula',
            'lname' => 'Tester',
            'username' => 'formulatester' . $this->wireTestUserId,
            'password' => 'x',
            'division' => 2,
            'section' => 21,
            'designation' => 52,
        ]);

        DB::table('hris.payroll_settings')->where('userid', $this->wireTestUserId)->delete();
        DB::table('hris.payroll_settings')->insert([
            'userid' => $this->wireTestUserId,
            'basic_salary' => 20000,
            'salary_grade' => 11,
            'step' => 1,
        ]);

        $baseId = DB::table('hris.payroll_components')->insertGetId([
            'code' => 'TEST_FORMULA_A',
            'name' => 'Test Base Earning',
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
            'sort_order' => 998,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('hris.payroll_components')->insert([
            'code' => 'TEST_FORMULA_B',
            'name' => 'Test Dependent Earning',
            'type' => 'earning',
            'is_global' => 1,
            'taxable' => 0,
            'default_amount' => 0,
            'calculation_type' => 'percentage',
            'base_type' => 'component',
            'base_component_code' => 'TEST_FORMULA_A',
            'rate_percent' => 10,
            'is_active' => 1,
            'is_cutoff_adjustable' => 0,
            'sort_order' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // This employee is explicitly excluded from the base component.
        DB::table('hris.employee_payroll_components')->insert([
            'payroll_component_id' => $baseId,
            'userid' => $this->wireTestUserId,
            'amount' => 0,
            'amount_override' => null,
            'is_active' => 0,
            'is_excluded' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(PayrollServices::class);
        $method = new ReflectionMethod(PayrollServices::class, 'getEmployeeDynamicComponents');
        $method->setAccessible(true);
        $components = $method->invoke($service, $this->wireTestUserId, '2027-07-01', '2027-07-15', 20000.0);

        // TEST_FORMULA_A itself is filtered out entirely (excluded), and
        // TEST_FORMULA_B - 10% of a base that isn't there - resolves to 0
        // rather than erroring or being dropped.
        $this->assertNull($components->firstWhere('code', 'TEST_FORMULA_A'));
        $dependent = $components->firstWhere('code', 'TEST_FORMULA_B');
        $this->assertNotNull($dependent);
        $this->assertEqualsWithDelta(0.0, $dependent->amount, 0.001);

        DB::table('hris.employee_payroll_components')->where('payroll_component_id', $baseId)->where('userid', $this->wireTestUserId)->delete();
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/PayrollComponentFormulaTest.php --filter "get_employee_dynamic_components_resolves_a_percentage_component_against_the_employees_basic_salary|a_base_component_excluded_for_the_employee_resolves_its_dependent_to_zero"`
Expected: FAIL — `Too few arguments to function ...getEmployeeDynamicComponents(), 3 passed ... and exactly 4 expected` (the method doesn't yet accept `$basicSalary`).

- [ ] **Step 3: Wire the resolution algorithm into `getEmployeeDynamicComponents()`**

Replace the full `getEmployeeDynamicComponents()` method (`app/Services/PayrollServices.php:652-731`) with:

```php
    private function getEmployeeDynamicComponents($userId, $periodStart, $periodEnd, float $basicSalary)
    {
        $globalRows = DB::table('hris.payroll_components as pc')
            ->leftJoin('hris.employee_payroll_components as epc', function ($join) use ($userId) {
                $join->on('epc.payroll_component_id', '=', 'pc.id')
                    ->where('epc.userid', '=', $userId);
            })
            ->where('pc.is_active', 1)
            ->where('pc.is_global', 1)
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
            ->get()
            ->filter(function ($row) use ($periodStart, $periodEnd) {
                if ($row->is_excluded || $row->employee_active === 0) {
                    return false;
                }

                if ($periodStart && $row->effective_to) {
                    if ($row->effective_to < $periodStart) return false;
                }
                if ($periodEnd && $row->effective_from) {
                    if ($row->effective_from > $periodEnd) return false;
                }
                return true;
            });

        $globals = $this->resolveComponentAmounts($globalRows, $basicSalary)
            ->map(function ($row) {
                return (object) [
                    'payroll_component_id' => $row->payroll_component_id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'type' => $row->type,
                    'taxable' => $row->taxable,
                    'amount' => $row->amount,
                ];
            });

        $employeeSpecific = DB::table('hris.employee_payroll_components as epc')
            ->join('hris.payroll_components as pc', 'pc.id', '=', 'epc.payroll_component_id')
            ->where('epc.userid', $userId)
            ->where('pc.is_active', 1)
            ->where(function ($q) {
                $q->where('pc.is_global', 0)->orWhereNull('pc.is_global');
            })
            ->where('epc.is_active', 1)
            ->select(
                'pc.id as payroll_component_id',
                'pc.code',
                'pc.name',
                'pc.type',
                'pc.taxable',
                'epc.amount_override',
                'epc.amount'
            )
            ->get()
            ->map(function ($row) {
                $amount = $row->amount_override !== null ? $row->amount_override : $row->amount;
                return (object) [
                    'payroll_component_id' => $row->payroll_component_id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'type' => $row->type,
                    'taxable' => $row->taxable,
                    'amount' => $amount ?? 0,
                ];
            });

        return $globals->merge($employeeSpecific);
    }
```

Then update the call site at `app/Services/PayrollServices.php:573`. Find:

```php
        $dynamicComponents = $this->getEmployeeDynamicComponents($employee["userid"], $periodStart, $periodEnd);
```

Replace with:

```php
        $dynamicComponents = $this->getEmployeeDynamicComponents($employee["userid"], $periodStart, $periodEnd, (float) $basic_salary);
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/PayrollComponentFormulaTest.php`
Expected: `Tests: 13 passed`.

- [ ] **Step 5: Run the existing payroll test suites to check for regressions**

Run: `php artisan test tests/Feature/PayrollCutoffTest.php tests/Feature/PayrollComponentAdjustmentTest.php`
Expected: both suites still fully pass (`PayrollCutoffTest: 8 passed`, `PayrollComponentAdjustmentTest: 3 passed`) — `getEmployeeDynamicComponents()`'s new 4th parameter and extra selected columns must not change behavior for existing flat (`calculation_type = 'fixed'`) components.

- [ ] **Step 6: Commit**

```bash
git add app/Services/PayrollServices.php tests/Feature/PayrollComponentFormulaTest.php
git commit -m "feat: resolve computed component amounts against the employee's basic salary"
```

---

### Task 5: Validate and persist formula fields in `PayrollController::savePayrollComponents()`

**Files:**
- Modify: `app/Http/Controllers/PayrollController.php:194-236` (`savePayrollComponents()`)
- Modify: `tests/Feature/PayrollComponentFormulaTest.php`

- [ ] **Step 1: Write the failing tests**

Add these methods to `tests/Feature/PayrollComponentFormulaTest.php`, just before the class's closing `}`:

```php
    /** @test */
    public function saving_a_component_via_the_endpoint_persists_the_formula_fields()
    {
        $this->actAsAdmin();

        $response = $this->withoutMiddleware()->postJson('/payroll-components', [
            'components' => [[
                'code' => 'TEST_FORMULA_A',
                'name' => 'Test Hazard Pay',
                'type' => 'earning',
                'taxable' => false,
                'default_amount' => 0,
                'is_global' => true,
                'is_active' => true,
                'is_cutoff_adjustable' => false,
                'calculation_type' => 'percentage',
                'base_type' => 'basic_salary',
                'base_component_code' => null,
                'rate_percent' => 25,
            ]],
        ]);

        $response->assertOk();

        $stored = DB::table('hris.payroll_components')->where('code', 'TEST_FORMULA_A')->first();
        $this->assertNotNull($stored);
        $this->assertEquals('percentage', $stored->calculation_type);
        $this->assertEquals('basic_salary', $stored->base_type);
        $this->assertEquals(25.0, (float) $stored->rate_percent);
    }

    /** @test */
    public function the_endpoint_rejects_a_component_that_would_create_a_circular_reference()
    {
        $this->actAsAdmin();

        $this->withoutMiddleware()->postJson('/payroll-components', [
            'components' => [[
                'code' => 'TEST_FORMULA_A',
                'name' => 'Test A',
                'type' => 'earning',
                'taxable' => false,
                'default_amount' => 0,
                'is_global' => true,
                'is_active' => true,
                'is_cutoff_adjustable' => false,
                'calculation_type' => 'percentage',
                'base_type' => 'basic_salary',
                'base_component_code' => null,
                'rate_percent' => 25,
            ]],
        ])->assertOk();

        $this->withoutMiddleware()->postJson('/payroll-components', [
            'components' => [[
                'code' => 'TEST_FORMULA_B',
                'name' => 'Test B',
                'type' => 'earning',
                'taxable' => false,
                'default_amount' => 0,
                'is_global' => true,
                'is_active' => true,
                'is_cutoff_adjustable' => false,
                'calculation_type' => 'percentage',
                'base_type' => 'component',
                'base_component_code' => 'TEST_FORMULA_A',
                'rate_percent' => 10,
            ]],
        ])->assertOk();

        // Now try to make A a percentage of B, closing the loop A -> B -> A.
        $response = $this->withoutMiddleware()->postJson('/payroll-components', [
            'components' => [[
                'code' => 'TEST_FORMULA_A',
                'name' => 'Test A',
                'type' => 'earning',
                'taxable' => false,
                'default_amount' => 0,
                'is_global' => true,
                'is_active' => true,
                'is_cutoff_adjustable' => false,
                'calculation_type' => 'percentage',
                'base_type' => 'component',
                'base_component_code' => 'TEST_FORMULA_B',
                'rate_percent' => 5,
            ]],
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Circular reference', $response->json('message'));

        // The bad update must not have been persisted - A is still basic_salary-based.
        $stored = DB::table('hris.payroll_components')->where('code', 'TEST_FORMULA_A')->first();
        $this->assertEquals('basic_salary', $stored->base_type);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/PayrollComponentFormulaTest.php --filter "persists_the_formula_fields|create_a_circular_reference"`
Expected: FAIL — the "persists" test fails because `stored->calculation_type` is `"fixed"` (the field was never read from the request), and the "rejects" test fails because the response is `200` instead of `422` (no validation is wired in yet).

- [ ] **Step 3: Wire validation and persistence into `savePayrollComponents()`**

Replace the full `savePayrollComponents()` method (`app/Http/Controllers/PayrollController.php:194-236`) with:

```php
    public function savePayrollComponents(Request $request)
    {
        try {
            $components = $request->input('components', []);
            if (!is_array($components)) {
                return response()->json(['success' => false, 'message' => 'Invalid payload.'], 422);
            }

            $normalized = [];
            foreach ($components as $component) {
                if (empty($component['code']) || empty($component['name']) || empty($component['type'])) {
                    continue;
                }

                $code = strtoupper(trim((string) $component['code']));
                $calculationType = strtolower((string) ($component['calculation_type'] ?? 'fixed')) === 'percentage' ? 'percentage' : 'fixed';
                $baseType = $component['base_type'] ?? null;
                $baseType = in_array($baseType, ['basic_salary', 'component'], true) ? $baseType : null;
                $baseComponentCode = !empty($component['base_component_code']) ? strtoupper(trim((string) $component['base_component_code'])) : null;
                $ratePercent = isset($component['rate_percent']) && $component['rate_percent'] !== '' ? (float) $component['rate_percent'] : null;

                $normalized[$code] = [
                    'name' => trim((string) $component['name']),
                    'type' => strtolower(trim((string) $component['type'])) === 'deduction' ? 'deduction' : 'earning',
                    'taxable' => !empty($component['taxable']),
                    'default_amount' => isset($component['default_amount']) ? (float) $component['default_amount'] : 0,
                    'is_global' => !empty($component['is_global']),
                    'is_active' => array_key_exists('is_active', $component) ? (bool) $component['is_active'] : true,
                    'is_cutoff_adjustable' => !empty($component['is_cutoff_adjustable']),
                    'calculation_type' => $calculationType,
                    'base_type' => $calculationType === 'percentage' ? $baseType : null,
                    'base_component_code' => $calculationType === 'percentage' && $baseType === 'component' ? $baseComponentCode : null,
                    'rate_percent' => $calculationType === 'percentage' ? $ratePercent : null,
                ];
            }

            // Validate the full resulting set (every existing component, with
            // this save's changes overlaid) before writing anything, so a bad
            // formula never lands half-saved. Inactive existing components are
            // included too - deactivated-but-still-referenced bases are valid
            // (they resolve to 0), only truly unknown codes should be rejected.
            $existing = DB::table('hris.payroll_components')
                ->get(['code', 'calculation_type', 'base_type', 'base_component_code', 'rate_percent'])
                ->keyBy('code')
                ->map(fn($row) => (array) $row)
                ->all();

            $componentsByCode = array_merge($existing, $normalized);

            $error = $this->payrollServices->validateComponentFormulas($componentsByCode);
            if ($error) {
                return response()->json(['success' => false, 'message' => $error], 422);
            }

            foreach ($normalized as $code => $component) {
                DB::table('hris.payroll_components')->updateOrInsert(
                    ['code' => $code],
                    [
                        'name' => $component['name'],
                        'type' => $component['type'],
                        'is_global' => $component['is_global'] ? 1 : 0,
                        'taxable' => $component['taxable'],
                        'default_amount' => $component['default_amount'],
                        'calculation_type' => $component['calculation_type'],
                        'base_type' => $component['base_type'],
                        'base_component_code' => $component['base_component_code'],
                        'rate_percent' => $component['rate_percent'],
                        'is_active' => $component['is_active'] ? 1 : 0,
                        'is_cutoff_adjustable' => $component['is_cutoff_adjustable'] ? 1 : 0,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            return response()->json(['success' => true, 'message' => 'Components saved successfully.']);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/PayrollComponentFormulaTest.php`
Expected: `Tests: 15 passed`.

- [ ] **Step 5: Run the existing payroll test suites to check for regressions**

Run: `php artisan test tests/Feature/PayrollComponentAdjustmentTest.php tests/Feature/PayrollTaxRuleTest.php`
Expected: both suites still fully pass — the `admin_toggling_is_cutoff_adjustable_round_trips_through_settings_endpoints` test in particular must still pass unchanged, since it saves a component without any `calculation_type` field at all (must default to `fixed` and skip validation).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/PayrollController.php tests/Feature/PayrollComponentFormulaTest.php
git commit -m "feat: validate and persist payroll component formula fields on save"
```

---

### Task 6: Settings UI — Computation Type, Base, and Rate fields

**Files:**
- Modify: `resources/views/settings/payroll_components.blade.php`

- [ ] **Step 1: Replace the "Default Amount" field with the Computation Type toggle**

In the modal body (`resources/views/settings/payroll_components.blade.php:120-123`), replace:

```html
                        <div class="col-md-4">
                            <label class="form-label">Default Amount</label>
                            <input type="number" class="form-control" id="component_default" value="0.00">
                        </div>
```

with:

```html
                        <div class="col-md-4">
                            <label class="form-label">Computation Type</label>
                            <select class="form-control" id="component_calc_type">
                                <option value="fixed">Fixed Amount</option>
                                <option value="percentage">Percentage</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="component_default_wrap">
                            <label class="form-label">Default Amount</label>
                            <input type="number" class="form-control" id="component_default" value="0.00">
                        </div>
                        <div class="col-md-4 d-none" id="component_base_wrap">
                            <label class="form-label">Base</label>
                            <select class="form-control" id="component_base">
                                <option value="__BASIC_SALARY__">Basic Salary</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-none" id="component_rate_wrap">
                            <label class="form-label">Percentage Rate (%)</label>
                            <input type="number" class="form-control" id="component_rate" value="0.00" step="0.001">
                        </div>
```

- [ ] **Step 2: Initialize Select2 and the Computation Type toggle**

In the `$(document).ready(function () { ... })` block (`resources/views/settings/payroll_components.blade.php:161-193`), find:

```javascript
        $(document).ready(function () {
            loadComponents();

            $("#btn_add_component").click(function () {
```

Replace with:

```javascript
        $(document).ready(function () {
            loadComponents();

            $("#component_base").select2({
                dropdownParent: $("#component_modal"),
                width: "100%"
            });

            $("#component_calc_type").on("change", function () {
                toggleComputationFields();
            });

            $("#btn_add_component").click(function () {
```

- [ ] **Step 3: Add the `toggleComputationFields()` function**

Add this function anywhere among the other top-level functions in the `@section('js')` block (e.g. directly after `renderComponents()`, which currently ends at `resources/views/settings/payroll_components.blade.php:231`):

```javascript
        function toggleComputationFields() {
            const isPercentage = $("#component_calc_type").val() === "percentage";
            $("#component_default_wrap").toggleClass("d-none", isPercentage);
            $("#component_base_wrap").toggleClass("d-none", !isPercentage);
            $("#component_rate_wrap").toggleClass("d-none", !isPercentage);
        }
```

- [ ] **Step 4: Show the formula in the table's "Default" column**

Replace the `renderRow()` function (`resources/views/settings/payroll_components.blade.php:233-258`) with:

```javascript
        function renderRow(component) {
            const taxable = component.taxable ? "Yes" : "No";
            const active = component.is_active ? "Yes" : "No";
            const adjustable = component.is_cutoff_adjustable ? "Yes" : "No";
            const defaultDisplay = component.calculation_type === "percentage"
                ? `${parseFloat(component.rate_percent || 0)}% of ${component.base_type === "basic_salary" ? "Basic Salary" : (component.base_component_code || "?")}`
                : (component.default_amount !== undefined && component.default_amount !== null ? parseFloat(component.default_amount).toFixed(2) : "0.00");

            return `
                <tr>
                    <td>${component.code}</td>
                    <td>${component.name}</td>
                    <td>${taxable}</td>
                    <td>${defaultDisplay}</td>
                    <td>${active}</td>
                    <td>${adjustable}</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-action btn-edit btn-edit-component" data-code="${component.code}" data-type="${component.type}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-action btn-delete btn-remove-component" data-code="${component.code}" data-type="${component.type}" title="Deactivate">
                            <i class="bi bi-x-circle"></i>
                        </button>
                    </td>
                </tr>`;
        }
```

- [ ] **Step 5: Populate the Base dropdown and formula fields when the modal opens**

Replace the `openComponentModal()` function (`resources/views/settings/payroll_components.blade.php:260-272`) with:

```javascript
        function openComponentModal(component = null) {
            editMode = !!component;
            editCode = component ? component.code : "";
            $("#componentModalLabel").text(editMode ? "Edit Component" : "Add Component");
            $("#component_code").val(component ? component.code : "").prop("disabled", editMode);
            $("#component_name").val(component ? component.name : "");
            $("#component_type").val(component ? component.type : "earning");
            $("#component_default").val(component ? (component.default_amount ?? 0) : "0.00");
            $("#component_taxable").prop("checked", !!(component && component.taxable));
            $("#component_active").prop("checked", component ? !!component.is_active : true);
            $("#component_cutoff_adjustable").prop("checked", !!(component && component.is_cutoff_adjustable));

            const $base = $("#component_base");
            $base.empty().append('<option value="__BASIC_SALARY__">Basic Salary</option>');
            earnings.concat(deductions)
                .filter(item => item.code !== editCode)
                .forEach(item => {
                    $base.append(`<option value="${item.code}">${item.code} - ${item.name}</option>`);
                });

            const calcType = component && component.calculation_type === "percentage" ? "percentage" : "fixed";
            $("#component_calc_type").val(calcType);

            const baseValue = component && component.base_type === "component"
                ? component.base_component_code
                : "__BASIC_SALARY__";
            $base.val(baseValue).trigger("change");

            $("#component_rate").val(component && component.rate_percent !== null && component.rate_percent !== undefined ? component.rate_percent : "0.00");

            toggleComputationFields();
            $("#component_modal").modal("show");
        }
```

- [ ] **Step 6: Send the formula fields when saving**

Replace the `saveComponent()` function (`resources/views/settings/payroll_components.blade.php:274-301`) with:

```javascript
        function saveComponent() {
            const code = ($("#component_code").val() || "").trim().toUpperCase();
            const name = ($("#component_name").val() || "").trim();
            const type = $("#component_type").val();
            const taxable = $("#component_taxable").is(":checked");
            const active = $("#component_active").is(":checked");
            const cutoffAdjustable = $("#component_cutoff_adjustable").is(":checked");
            const defaultRaw = $("#component_default").val();
            const defaultAmount = defaultRaw === "" ? 0 : parseFloat(defaultRaw);
            const calculationType = $("#component_calc_type").val() === "percentage" ? "percentage" : "fixed";
            const baseValue = $("#component_base").val();
            const ratePercent = parseFloat($("#component_rate").val());

            if (!code || !name) {
                toastr.error("Code and Name are required.", "Validation");
                return;
            }

            if (calculationType === "percentage" && (isNaN(ratePercent) || ratePercent < 0)) {
                toastr.error("Enter a valid, non-negative percentage rate.", "Validation");
                return;
            }

            const payload = {
                code: code,
                name: name,
                type: type,
                taxable: taxable,
                default_amount: isNaN(defaultAmount) ? 0 : defaultAmount,
                is_global: true,
                is_active: active,
                is_cutoff_adjustable: cutoffAdjustable,
                calculation_type: calculationType,
                base_type: calculationType === "percentage"
                    ? (baseValue === "__BASIC_SALARY__" ? "basic_salary" : "component")
                    : null,
                base_component_code: calculationType === "percentage" && baseValue !== "__BASIC_SALARY__" ? baseValue : null,
                rate_percent: calculationType === "percentage" ? ratePercent : null
            };

            submitComponents([payload]);
        }
```

- [ ] **Step 7: Verify in the browser**

Ensure MySQL is running and the app is reachable, then drive it headlessly. If a scratchpad Playwright project from an earlier session isn't already set up, create one:

```bash
mkdir -p /tmp/pw-verify && cd /tmp/pw-verify && npm init -y && npm install playwright && npx playwright install chromium
```

Set up a temporary local-only login route the same way used earlier for the Tax Purpose dropdown verification: add, in `routes/web.php` right after the `/logout` route —

```php
if (app()->environment('local')) {
    Route::get('/__dev_login/{id}', function ($id) {
        Auth::loginUsingId($id);
        return redirect('/payroll-components/manage');
    });
}
```

— log in as a user with the `payroll.settings` permission (check first with `php artisan tinker --execute="echo app(App\Services\PermissionService::class)->can(1, 'payroll.settings') ? 'yes' : 'no';"`, trying a couple of candidate user IDs if needed), then run a script equivalent to:

```javascript
const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto('http://localhost/hris/__dev_login/1');
  await page.goto('http://localhost/hris/payroll-components/manage');
  await page.click('#btn_add_component');
  await page.waitForSelector('#component_modal.show');
  await page.fill('#component_code', 'TEST_HAZARD_PAY');
  await page.fill('#component_name', 'Test Hazard Pay');
  await page.selectOption('#component_calc_type', 'percentage');
  await page.waitForTimeout(200);
  await page.screenshot({ path: 'percentage-fields-shown.png' });
  const baseHidden = await page.$eval('#component_base_wrap', el => el.classList.contains('d-none'));
  const defaultHidden = await page.$eval('#component_default_wrap', el => el.classList.contains('d-none'));
  console.log('base visible:', !baseHidden, 'default hidden:', defaultHidden);
  await page.fill('#component_rate', '25');
  await page.click('#btn_save_component');
  await page.waitForTimeout(500);
  const rowText = await page.textContent('#earnings_table_body');
  console.log('row shows formula:', rowText.includes('25% of Basic Salary'));
  await browser.close();
})();
```

Expected: `base visible: true default hidden: true`, and `row shows formula: true`. Then clean up the test component (`DB::table('hris.payroll_components')->where('code', 'TEST_HAZARD_PAY')->delete();` via tinker) and remove the temporary `/__dev_login/{id}` route from `routes/web.php` (confirm with `git diff routes/web.php` that it shows no changes).

- [ ] **Step 8: Commit**

```bash
git add resources/views/settings/payroll_components.blade.php
git commit -m "feat: add computation type, base, and rate fields to the payroll components UI"
```

---

### Task 7: End-to-end integration test — the actual "no code change" proof

**Files:**
- Modify: `tests/Feature/PayrollComponentFormulaTest.php`

- [ ] **Step 1: Write the failing tests**

Add these two methods to `tests/Feature/PayrollComponentFormulaTest.php`, just before the class's closing `}`:

```php
    /** @test */
    public function a_percentage_component_flows_through_the_full_payroll_computation()
    {
        DB::table('tdh_user.users')->where('id', $this->wireTestUserId)->delete();
        DB::table('tdh_user.users')->insert([
            'id' => $this->wireTestUserId,
            'fname' => 'Formula',
            'lname' => 'Tester',
            'username' => 'formulatester' . $this->wireTestUserId,
            'password' => 'x',
            'division' => 2,
            'section' => 21,
            'designation' => 52,
        ]);

        DB::table('hris.payroll_settings')->where('userid', $this->wireTestUserId)->delete();
        DB::table('hris.payroll_settings')->insert([
            'userid' => $this->wireTestUserId,
            'basic_salary' => 20000,
            'salary_grade' => 11,
            'step' => 1,
        ]);

        DB::table('hris.payroll_components')->insert([
            'code' => 'TEST_FORMULA_A',
            'name' => 'Test Hazard Pay',
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

        $masterId = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title' => 'Formula Test Cutoff',
            'period_start' => '2027-08-01',
            'period_end' => '2027-08-15',
            'generated_by' => $this->wireTestUserId,
            'generated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payrollId = DB::table('hris.payrolls')->insertGetId([
            'cutoff_id' => $masterId,
            'title' => 'Formula Test Payroll',
            'start_date' => '2027-08-01',
            'end_date' => '2027-08-15',
            'cutoff_start_date' => '2027-08-01',
            'cutoff_end_date' => '2027-08-15',
            'cutoff_title' => 'Formula Test Cutoff',
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
            'period_start' => '2027-08-01',
            'period_end' => '2027-08-15',
            'total_working_days' => 10,
            'days_present' => 10,
            'days_absent' => 0,
            'late_minutes' => 0,
            'undertime_minutes' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actAsAdmin();
        $response = $this->withoutMiddleware()->postJson('/save_payroll_employee', [
            'payroll_id' => $payrollId,
            'employees' => [[
                'userid' => $this->wireTestUserId,
                'salary_adjustment' => 0,
                'adjustments' => [],
            ]],
        ]);
        $response->assertOk();

        $payrollEmployee = DB::table('hris.payroll_employees')
            ->where('payroll_id', $payrollId)
            ->where('employee_id', $this->wireTestUserId)
            ->first();
        $this->assertNotNull($payrollEmployee);
        // Hazard Pay = 25% of the 20,000 basic salary = 5,000 other_earnings.
        $this->assertEqualsWithDelta(5000.0, (float) $payrollEmployee->other_earnings, 0.01);

        DB::table('hris.payroll_employee_components')
            ->whereIn('payroll_employee_id', DB::table('hris.payroll_employees')->where('payroll_id', $payrollId)->pluck('id'))
            ->delete();
        DB::table('hris.payroll_employees')->where('payroll_id', $payrollId)->delete();
        DB::table('hris.payrolls')->where('id', $payrollId)->delete();
        DB::table('hris.cutoff_summaries')->where('cutoff_id', $masterId)->delete();
        DB::table('hris.cutoff_summaries_master')->where('id', $masterId)->delete();
    }

    /** @test */
    public function changing_basic_salary_changes_the_computed_component_on_the_next_payroll_run_with_no_code_change()
    {
        DB::table('tdh_user.users')->where('id', $this->wireTestUserId)->delete();
        DB::table('tdh_user.users')->insert([
            'id' => $this->wireTestUserId,
            'fname' => 'Formula',
            'lname' => 'Tester',
            'username' => 'formulatester' . $this->wireTestUserId,
            'password' => 'x',
            'division' => 2,
            'section' => 21,
            'designation' => 52,
        ]);

        DB::table('hris.payroll_components')->insert([
            'code' => 'TEST_FORMULA_A',
            'name' => 'Test Hazard Pay',
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

        $masterId = DB::table('hris.cutoff_summaries_master')->insertGetId([
            'title' => 'Formula Salary Change Cutoff',
            'period_start' => '2027-09-01',
            'period_end' => '2027-09-15',
            'generated_by' => $this->wireTestUserId,
            'generated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payrollId = DB::table('hris.payrolls')->insertGetId([
            'cutoff_id' => $masterId,
            'title' => 'Formula Salary Change Payroll',
            'start_date' => '2027-09-01',
            'end_date' => '2027-09-15',
            'cutoff_start_date' => '2027-09-01',
            'cutoff_end_date' => '2027-09-15',
            'cutoff_title' => 'Formula Salary Change Cutoff',
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
            'period_start' => '2027-09-01',
            'period_end' => '2027-09-15',
            'total_working_days' => 10,
            'days_present' => 10,
            'days_absent' => 0,
            'late_minutes' => 0,
            'undertime_minutes' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actAsAdmin();

        DB::table('hris.payroll_settings')->where('userid', $this->wireTestUserId)->delete();
        DB::table('hris.payroll_settings')->insert([
            'userid' => $this->wireTestUserId,
            'basic_salary' => 20000,
            'salary_grade' => 11,
            'step' => 1,
        ]);

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
        $this->assertEqualsWithDelta(5000.0, (float) $firstRun->other_earnings, 0.01);

        // A salary grade/step change: basic_salary goes from 20,000 to
        // 30,000. Nothing about the payroll code changes between these two
        // calls - just this one row of data.
        DB::table('hris.payroll_settings')->where('userid', $this->wireTestUserId)
            ->update(['basic_salary' => 30000]);

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
        // 25% of the new 30,000 basic salary = 7,500.
        $this->assertEqualsWithDelta(7500.0, (float) $secondRun->other_earnings, 0.01);

        DB::table('hris.payroll_employee_components')
            ->whereIn('payroll_employee_id', DB::table('hris.payroll_employees')->where('payroll_id', $payrollId)->pluck('id'))
            ->delete();
        DB::table('hris.payroll_employees')->where('payroll_id', $payrollId)->delete();
        DB::table('hris.payrolls')->where('id', $payrollId)->delete();
        DB::table('hris.cutoff_summaries')->where('cutoff_id', $masterId)->delete();
        DB::table('hris.cutoff_summaries_master')->where('id', $masterId)->delete();
    }
```

- [ ] **Step 2: Run tests to verify they pass**

Run: `php artisan test tests/Feature/PayrollComponentFormulaTest.php`
Expected: `Tests: 17 passed`. (If either new test fails, it's a real regression in Tasks 3-5's wiring, not a missing-method failure — every method these tests call already exists by this point.)

- [ ] **Step 3: Run the full existing DTR/payroll regression suite**

Run: `php artisan test tests/Feature/PayrollCutoffTest.php tests/Feature/PayrollComponentAdjustmentTest.php tests/Feature/PayrollTaxRuleTest.php tests/Feature/CutoffSummaryDaysTest.php tests/Unit/DtrServicesUnifiedComputationTest.php tests/Feature/DtrPrintCharacterizationTest.php`
Expected: every suite passes with no failures.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/PayrollComponentFormulaTest.php
git commit -m "test: add end-to-end coverage proving computed components need no code change on salary/policy updates"
```
