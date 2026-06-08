# Payroll Cutoff Prerequisite Design

**Date:** 2026-06-08
**Module:** Payroll / Cutoff

---

## Overview

Enforce a cutoff period as a prerequisite for creating a payroll. When creating a payroll, the user must select an existing `cutoff_summaries_master` record from a dropdown. The payroll's period dates auto-fill from the selected cutoff, and a proper FK (`cutoff_id`) is stored on the payroll record. Payroll computation is updated to use this FK directly instead of fragile date-based matching.

Benefits and deductions in payroll computation already use the modern `payroll_components` / `employee_payroll_components` system — no changes to that logic are needed.

---

## Current State (Problems)

1. `payrolls` stores `cutoff_start_date`, `cutoff_end_date`, `cutoff_title` as nullable free-text fields — no FK to `cutoff_summaries_master`.
2. `savePayroll()` accepts cutoff fields as nullable — payroll can be created without any cutoff.
3. `getCutoffPeriods()` returns `period_start`, `period_end`, `title` but NOT `id` — the dropdown cannot carry the FK value.
4. `savePayrollEmployee()` looks up the cutoff master by matching `cutoff_start_date`/`cutoff_end_date`/`cutoff_title` — fragile, breaks if titles differ.

---

## Approach

**Option A: Add `cutoff_id` FK to `payrolls`, make it required at application level, keep existing columns.**

The existing `cutoff_start_date`, `cutoff_end_date`, `cutoff_title` columns are preserved for backward compatibility with existing records. On new payroll creation, they are auto-populated from the selected cutoff master record alongside `cutoff_id`.

---

## 1. Database

### Migration: add `cutoff_id` to `payrolls`

| Column | Type | Constraint | Notes |
|---|---|---|---|
| `cutoff_id` | unsignedBigInteger | nullable | FK to `cutoff_summaries_master.id`. Nullable to preserve existing records without a matched master. |

Add index on `cutoff_id`.

No columns removed. `cutoff_start_date`, `cutoff_end_date`, `cutoff_title` remain for backward compatibility with existing records and export logic.

---

## 2. Backend Changes

### `getCutoffPeriods()` — `PayrollController`

Add `id` to the select:

```php
->select('id', 'period_start', 'period_end', 'title')
```

This allows the frontend dropdown to carry the master record's `id` as the option value.

### `savePayroll()` — `PayrollController`

**Validation changes:**
- Add `cutoff_id` as **required** integer, must exist in `cutoff_summaries_master`.
- Remove `cutoff_start_date`, `cutoff_end_date`, `cutoff_title` from validation input — these are now derived server-side.

**Logic:**
1. Look up `cutoff_summaries_master` by `cutoff_id`.
2. Derive `start_date`, `end_date` from `master->period_start`, `master->period_end`.
3. Populate legacy columns `cutoff_start_date`, `cutoff_end_date`, `cutoff_title` from the master for backward compat.
4. Store `cutoff_id` on the new payroll record.

```php
$request->validate([
    'cutoff_id'    => 'required|integer|exists:hris.cutoff_summaries_master,id',
    'title'        => 'required|string|max:255',
    'payroll_type' => 'nullable|string|max:50',
    'rate_type'    => 'nullable|string|max:50',
    'remarks'      => 'nullable|string',
    'tax_purpose'  => 'nullable|string|max:150',
]);
```

`start_date` and `end_date` are no longer accepted from the client — they come from the cutoff master.

### `savePayrollEmployee()` — `PayrollController`

Replace the date-based cutoff master lookup with a direct FK lookup:

```php
// Before (fragile):
$cutoffMaster = $this->payrollServices->getCutoffMasterByPeriod($cutoffStart, $cutoffEnd, $cutoffTitle);

// After:
$cutoffMaster = DB::table($masterTable)->where('id', $period->cutoff_id)->first();
if (!$cutoffMaster) {
    return response()->json(['error' => 'Cutoff master not found for this payroll.'], 422);
}
```

`$cutoffStart` and `$cutoffEnd` are then derived from `$cutoffMaster->period_start` and `$cutoffMaster->period_end` for any downstream logic that still needs them.

### `getPayroll()` — `PayrollController`

Add a join to `cutoff_summaries_master` via `cutoff_id` to expose the cutoff title and period in the list response:

```php
->leftJoin('hris.cutoff_summaries_master as cm', 'cm.id', '=', 'p.cutoff_id')
->addSelect('cm.title as cutoff_label', 'cm.period_start as cutoff_period_start', 'cm.period_end as cutoff_period_end')
```

Falls back gracefully for existing payrolls where `cutoff_id` is null (via `leftJoin`).

---

## 3. Frontend Changes

### Payroll creation form — `payroll.blade.php`

**Dropdown (`#cutoff_period`):**
- Option `value` is now the master record's `id` (was previously the date range string).
- `data-start` and `data-end` attributes carry `period_start` and `period_end` for auto-fill.
- `data-title` carries the cutoff title for display.

**Auto-fill on selection:**
- `#start_date` and `#end_date` fill from `data-start`/`data-end` and become readonly (this logic already exists — just needs the correct value source).

**Submit (`#btn_save_payroll`):**
- Sends `cutoff_id` (the selected option value).
- Removes `cutoff_start_date`, `cutoff_end_date`, `cutoff_title` from the POST body (now derived server-side).
- Blocks submit with a validation error if no cutoff is selected ("Please select a cutoff period.").

**Payroll list table:**
- Show cutoff label column (title from `cutoff_label` in API response) alongside the existing start/end date columns.

---

## 4. Data Flow Summary

```
User selects cutoff from dropdown
  → cutoff_id sent to savePayroll()
  → Server looks up cutoff_summaries_master
  → start_date / end_date derived from master
  → payroll inserted with cutoff_id + legacy date columns
  → payroll status = draft

User adds employees to payroll (savePayrollEmployee)
  → payroll.cutoff_id used to fetch cutoff_summaries_master directly
  → per-employee cutoff_summaries looked up by cutoff_id
  → payroll_components / employee_payroll_components provide
    earnings (RATA, PERA, etc.) and deductions (GSIS, PHIC, etc.)
  → computation runs → payroll_employees + payroll_employee_components stored
```

---

## 5. Backward Compatibility

- Existing `payrolls` records with no `cutoff_id` (null): `getPayroll()` uses `leftJoin` so they still load without errors. `savePayrollEmployee()` falls back to date-based lookup when `cutoff_id` is null.
- No data migration required.

---

## 6. Out of Scope

- Changes to the payroll computation formula itself.
- Changes to `payroll_components` / `employee_payroll_components` data or management.
- Payroll approval workflow changes.
- Cutoff generation or locking logic.
