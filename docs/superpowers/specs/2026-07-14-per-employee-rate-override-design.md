# Per-Employee Rate Override — Design

## Problem

`payroll_components` supports percentage-based formulas (e.g. Hazard Pay = 25% of Basic Salary), and an employee can be given a flat `amount_override` to replace that formula outright — but a flat override freezes the number. If an employee's basic salary later changes, a flat override no longer reflects "25% of Basic Salary" (or whatever policy it was meant to encode); it just sits there stale until someone manually recalculates it. This defeats the entire point of the computed-components feature for the common case where an individual employee's *rate* differs from the catalog default (e.g. a higher salary grade gets 30% Hazard Pay instead of 25%), rather than their amount.

## Goals

- An employee can be given a different **percentage rate** for a percentage-based global component (e.g. 30% instead of the catalog's 25%), while the amount keeps recomputing live off their basic salary (or chained base component) exactly like the catalog default does.
- The existing flat `amount_override` continues to win outright over any formula, unchanged — a rate override only applies when no amount override is set.
- Existing components and employees with no rate override compute identically to today — additive only.

## Non-goals

- No per-employee override of the **base** itself (switching a component from "% of Basic Salary" to "% of some other component" for one employee). Only the rate changes; the base stays whatever the catalog component defines.
- No salary-grade-tiered rate tables on the component itself (e.g. "SG 1-19 = 25%, SG 20+ = 30%" applied automatically to everyone). This is a manual, per-employee, ad hoc override entered by HR — the same shape as the existing per-employee Amount override, not a new rules engine.
- No changes to fixed-type components. A fixed component has no rate to override; `amount_override` already fully covers per-employee exceptions for those.
- No change to the non-global "Payroll Settings" per-employee component flow beyond adding this one field — everything else about how non-global components are assigned is unchanged.

## Architecture

### Data model

New column on `hris.employee_payroll_components`:

| column | type | notes |
|---|---|---|
| `rate_percent_override` | decimal(8,3), nullable | meaningful only when the component's `calculation_type = percentage`; ignored for fixed components |

### Resolution algorithm

`PayrollServices::resolveComponentAmount()` (in `resolveComponentAmounts()`, used by `getEmployeeDynamicComponents()`) changes its priority order to:

1. If the employee has a non-null `amount_override` → use it. The formula never runs. **Unchanged** — wins outright regardless of any rate override also being present.
2. Else if `calculation_type = fixed` → use `default_amount`. **Unchanged.** `rate_percent_override` is not applicable and is ignored if somehow present.
3. Else (`calculation_type = percentage`):
   - Rate = the employee's `rate_percent_override` if set, otherwise the catalog's `rate_percent`.
   - Amount = that rate applied against the same base as today (`basic_salary`, or the resolved amount of `base_component_code`) — the base itself is never overridden, only the rate.
4. Round to 3 decimal places, as today.

`getEmployeeDynamicComponents()`'s global-components query adds `epc.rate_percent_override` to its `SELECT` so it's available to the resolver.

### Validation (save time)

- `rate_percent_override`, when provided, must be a non-negative number. Reuses the same non-negative check pattern as the catalog's own `rate_percent` validation.
- No error is raised if a rate override is submitted for a component that turns out to be `fixed` — the resolver simply ignores it. The UI won't expose the field for fixed-type rows in the first place, so this is a defensive backend rule, not a user-facing validation error.

### UI

Both places that render the Earnings/Deductions override tables — the Employee modal's Payroll tab (`resources/views/forms/payroll.blade.php` + `resources/views/js/employee.blade.php`) and Settings → Payroll Settings (`resources/views/modals/payroll.blade.php` + `resources/views/settings/payroll.blade.php`) — gain a **Rate Override %** column next to **Amount**:

- For rows where `calculation_type === 'percentage'`, the input is enabled; leaving it blank means "use the catalog's default rate."
- For rows where `calculation_type === 'fixed'`, the input is disabled and shows "—", since Amount already covers the full override story for those.
- Saving: a non-blank rate override is enough to include that row in the save payload on its own (same as a non-blank Amount override today) — an employee can have a rate override with no amount override.
- Reloading the tab pre-fills the Rate Override input from the employee's existing `rate_percent_override`, the same way Amount is pre-filled from `amount_override` today.

## Testing strategy

1. **Unit tests** on `resolveComponentAmount()`/`resolveComponentAmounts()`: an employee with a `rate_percent_override` gets `basic_salary × override_rate`, not the catalog's rate; an employee with both `amount_override` and `rate_percent_override` set gets the flat amount (amount wins); a `fixed` component with a stray `rate_percent_override` still resolves to `default_amount` (override ignored).
2. **Validation test**: a negative `rate_percent_override` is rejected at save time.
3. **Round-trip test** through `savePayrollSettings` → `syncDynamicComponents` → `getPayrollSettings`: a saved rate override persists and is returned correctly.
4. **End-to-end test**, extending the existing computed-components pattern: an employee with a Hazard-Pay rate override of 30% produces the correct amount inside `savePayrollEmployee()`'s resulting gross pay, and changing their `basic_salary` between two payroll runs changes the computed amount on the second run at the overridden rate — no code change, matching the "no code change on salary/policy updates" guarantee the base feature already has.
5. Existing `PayrollComponentFormulaTest`/`PayrollComponentAdjustmentTest`/`EmployeePayrollComponentAssignmentTest` suites continue passing unmodified.

## Rollout

Additive migration only (one new nullable column on `hris.employee_payroll_components`), no backfill needed. No changes to `payroll_components` or any other table. Existing employees with no rate override continue computing exactly as they do today.
