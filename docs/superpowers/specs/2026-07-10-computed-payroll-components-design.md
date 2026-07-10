# Computed Payroll Components — Design

## Problem

Every `payroll_components` row has a single flat `default_amount`. Components that are conceptually a formula against something else — e.g. Hazard Pay = 25% of Basic Salary — have to be hand-maintained as a static number, and re-entered by hand (per employee, via `amount_override`) whenever the underlying value changes (a salary grade/step adjustment, a new hazard-pay policy percentage, etc.). This is the same class of problem the tax-rules table already solved for withholding tax: move the rule into data so a policy change doesn't require a code deploy — this design does the equivalent for percentage-based allowances/deductions.

## Goals

- A `payroll_components` row can be defined as a percentage of **Basic Salary** or of **another component's resolved amount**, instead of (or as well as) a flat `default_amount`.
- Changing `basic_salary` (via a salary grade/step change) automatically changes every dependent computed component's payroll amount on the next payroll run — no code change, no manual re-entry.
- Chained components work: e.g. a deduction defined as a percentage of an already-computed earning.
- A per-employee `amount_override` still wins outright over any formula, exactly as it does today for flat components — no behavior change there.
- Existing components (all currently flat) keep computing identically after this ships — this is additive, not a rewrite of existing math.

## Non-goals

- No arbitrary formula/expression engine (no `eval`, no free-text expressions). Only a constrained "percentage of one named base" relationship. If a future need arises for something more expressive, that's a separate design.
- No **Gross Pay** as a formula base. Gross Pay is defined as basic salary + all earning components, so a component computed as "% of Gross Pay" would be circular by construction (Gross Pay can't be finalized until every earning component, including this one, is computed). Bases are limited to Basic Salary and other named components, which are always well-defined single values.
- No formula support on the per-employee **Payroll Settings** screen (`employee_payroll_components`, non-global/employee-specific components). That screen keeps its existing flat `amount` field as-is — employee-specific components are exceptions by nature, not typically something you'd want computed.
- No backfill of existing rows beyond a default: all pre-existing `payroll_components` rows get `calculation_type = 'fixed'`, which is a no-op — they compute exactly as they do today.

## Architecture

### Data model

New columns on `hris.payroll_components`:

| column | type | notes |
|---|---|---|
| `calculation_type` | enum(`fixed`, `percentage`) | default `fixed` |
| `base_type` | enum(`basic_salary`, `component`), nullable | only meaningful when `calculation_type = percentage` |
| `base_component_code` | string, nullable | set only when `base_type = component`; references another `payroll_components.code` |
| `rate_percent` | decimal, nullable | e.g. `25.00` for 25%; required when `calculation_type = percentage` |

`default_amount` keeps its current meaning and is simply unused/ignored when `calculation_type = percentage`.

### Resolution algorithm

`PayrollServices::getEmployeeDynamicComponents()` currently reads each global component's `default_amount`/`amount_override` independently, in no particular dependency order (see [PayrollServices.php:652-731](../../../app/Services/PayrollServices.php#L652-L731)). This changes to:

1. Load all active global components, including the four new fields.
2. Build a dependency graph from `base_component_code` (an edge from base → dependent).
3. Topologically sort the components. If the graph contains a cycle, this indicates bad data that should have been rejected at save time (see Validation below) — log a warning and treat every component in the cycle as `0` rather than looping forever or throwing mid-payroll-run.
4. Resolve amounts in dependency order, per employee:
   - If the employee has a non-null `amount_override` for this component → use it. The formula never runs. (Unchanged from today.)
   - Else if `calculation_type = fixed` → use `default_amount`. (Unchanged from today.)
   - Else if `calculation_type = percentage` and `base_type = basic_salary` → `basic_salary × rate_percent / 100`.
   - Else if `calculation_type = percentage` and `base_type = component` → `(resolved amount of base_component_code for this employee) × rate_percent / 100`. If the base component is missing, inactive, or excluded for this employee (`is_excluded`), its resolved amount is `0`, so the dependent component resolves to `0` too — no special-casing needed, this falls out of "already-resolved amount."
5. Round every computed amount to **3 decimal places** before it's summed into gross pay / deductions.

Because resolution reads `basic_salary` fresh from `payroll_settings` on every call, a salary grade/step change is picked up automatically the next time payroll is generated — this is the core of the "no code change when policy changes" goal.

### Validation (save time)

`PayrollController::savePayrollComponents()` gains a check — mirroring the existing `PayrollServices::validateTaxRuleSet()` pattern used for tax rules — before persisting a component with `calculation_type = percentage`:

- `base_component_code` is required, and must reference an existing component (any type — earning or deduction — is a valid base).
- `rate_percent` is required and must be a non-negative number.
- A component can't reference itself directly.
- Walk the candidate's base chain (`base_component_code` → that component's own `base_component_code` → ...); if it ever revisits the component being saved, reject with an error naming the cycle (e.g. `Circular reference: HAZARD_PAY → LONGEVITY → HAZARD_PAY`).

This keeps bad (cyclic) data out of the database entirely, so the defensive cycle handling in the resolution algorithm (step 3 above) is a safety net, not the primary guard.

### UI

**Settings → Payroll Components** modal ([resources/views/settings/payroll_components.blade.php](../../../resources/views/settings/payroll_components.blade.php)):

- New **Computation Type** dropdown: `Fixed Amount` (default) | `Percentage`.
- `Fixed Amount` selected → today's "Default Amount" number input, unchanged.
- `Percentage` selected → swaps in a **Base** dropdown (`Basic Salary`, or any other active component by code/name via Select2, matching the app-wide Select2 pattern) and a **Percentage Rate** number input.

Earnings/Deductions table: the "Default" column shows the flat amount for fixed components, or a formula string (`25% of Basic Salary`, `10% of HAZARD_PAY`) for percentage components, so the list is self-documenting at a glance.

## Testing strategy

1. **Unit tests** on the resolution algorithm: a fixed component, a basic-salary-percentage component, and a chained component-percentage-of-component case (A → B → C), asserting correct topological order and 3-decimal rounding.
2. **Validation tests**: saving a component with a direct self-reference is rejected; saving a component that creates a 2-hop cycle (A→B, then B→A) is rejected; saving `percentage` with a missing `base_component_code` or a negative `rate_percent` is rejected.
3. **Integration test**, extending existing payroll-computation coverage: a Hazard Pay component defined as 25% of Basic Salary produces the correct amount inside `savePayrollEmployee()`'s resulting gross pay; changing `basic_salary` on `payroll_settings` between two payroll runs changes the computed Hazard Pay on the second run with no code change.
4. Existing `PayrollComponentAdjustmentTest`/`PayrollCutoffTest`/`PayrollTaxRuleTest` continue passing unmodified — this is additive, existing flat components must compute identically.

## Rollout

Additive migration only (new nullable/defaulted columns on `hris.payroll_components`), no backfill needed — every existing row implicitly becomes `calculation_type = 'fixed'`, which is a no-op against current behavior. No changes to `employee_payroll_components` or any other table.
