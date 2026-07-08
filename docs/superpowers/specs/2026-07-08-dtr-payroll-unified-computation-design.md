# DTR/Payroll Unified Late-Undertime Computation — Design

## Problem

Two independent implementations of "late/undertime/absence for a given day" exist in this codebase:

1. **`DTRController::printDTR()`** — the sophisticated one. Break-time deduction, half-day-missing-half additive charge with half-scoped boundary math, gliding-schedule offset logic, overnight-shift window matching, holiday-with-logs still computes penalties (only leave/hospital-order/pass-slip waive). This is what employees and HR see printed.
2. **`DTRServices::getTotalLateAbsent()` / `processLogs()`** — a simpler, independently-written algorithm. No break-time deduction at all (the schedule map it builds never even carries `break_out`/`break_in`). A blanket rule — any checkout before 1:00 PM adds a flat 240 minutes plus the diff from noon — with none of the half-day-specific nuance. This is what actually populates `cutoff_summaries.late_minutes` / `undertime_minutes`, which `PayrollController::savePayrollEmployee()` reads directly to compute the late/absent payroll deduction.

Because these are separate code paths maintained independently, they can disagree for the same employee on the same day. An employee can see one number printed on their DTR and have a different number silently deducted from their payslip. Additionally, once a cutoff's totals are computed, no day-level detail survives — a payroll dispute requires re-running the computation against `dtr_logs`, which may have since been corrected and would then tell a different story than what was actually paid.

## Goals

- One computation produces the late/undertime/absence facts for a given employee/day, consumed by both `printDTR` (display) and the cutoff-generation path (payroll deduction).
- `printDTR`'s current output is unchanged — it is the working, trusted reference. Nothing about its behavior may change as a result of this work.
- A persisted per-day snapshot survives cutoff generation, so payroll disputes can be answered by looking at a stored record instead of recomputing against logs that may have changed.

## Non-goals

- No backfill/migration of existing `cutoff_summaries` rows. Only future `generateCutoffSummary()` / `getOrCreateCutoffSummary()` calls use the new engine and populate the new snapshot table. Existing locked cutoffs are untouched; existing unlocked drafts can already be regenerated through the existing flow if someone wants corrected numbers.
- No behavior changes/bug fixes to the algorithm itself as part of this work, even if something looks fixable along the way. Extraction is strictly behavior-preserving; anything noticed gets filed separately.
- No changes to `printDTR`'s presentational output (day markers, planned-schedule strings, OFF labels) — those stay in the controller.

## Architecture

### New class: `App\Services\DtrDayComputationService`

```php
public function computeRange(
    int $employeeId,
    string $startDate,
    string $endDate,
    Collection $schedules,   // keyed by date string, must include one buffer day on each side of the range (for overnight/previous-day lookups)
    Collection $logs,        // dtr_logs rows spanning startDate-1 to endDate+1
    array $exemptions,       // ['holidays' => [date => ['type'=>..,'description'=>..]], 'leaves' => [date => true], 'hospitalOrders' => [date => true], 'passSlips' => [date => true], 'officialPassSlips' => [date => true]]
    bool $skipAllAutomation = false
): Collection            // keyed by date string => ['late_minutes' => int, 'undertime_minutes' => int, 'is_absent' => bool, 'is_holiday' => bool, 'exemption_reason' => ?string]
```

This method is extracted from the money-relevant core of `printDTR`'s per-day closure — schedule matching (overnight/gliding/half-day-missing-half), break-time deduction, late/undertime arithmetic, absence detection. It explicitly does **not** include `printDTR`'s presentational fields (`day_marker`, `planned_schedule`, `off_label`, `suppress_off_label`) — those are display concerns, stay in the controller, and were never part of the divergence bug. `exemption_reason` is one of `leave`, `hospital_order`, `pass_slip`, `holiday`, or `null`.

### Consumer 1: `DTRController::printDTR()`

Calls `computeRange()` once per employee for the full month range (matching its current per-employee, per-month loop), reads `late_minutes`/`undertime_minutes`/`is_absent` from the result for each day, and continues building its display strings exactly as it does today — those code paths are otherwise untouched.

### Consumer 2: `DTRServices`

`getTotalLateAbsent()` is rewritten to call `computeRange()` for the requested date range (fetching schedules/logs with the same buffering it already does) and sum `late_minutes`/`undertime_minutes` across days, and count `is_absent` days — replacing `processLogs()` and its private helpers entirely. `getCutoffBreakdown()`'s other pieces (approved overtime minutes, approved pass slip minutes, leave/HO day counts) are unaffected — only the late/undertime/absence numbers change source.

### New table: `cutoff_summary_days`

| column | type | notes |
|---|---|---|
| id | bigIncrements | |
| cutoff_id | unsignedBigInteger | FK to `cutoff_summaries_master.id` |
| employee_id | integer | |
| date | date | |
| late_minutes | integer | default 0 |
| undertime_minutes | integer | default 0 |
| is_absent | boolean | default false |
| is_holiday | boolean | default false |
| exemption_reason | string, nullable | `leave` / `hospital_order` / `pass_slip` / `holiday` / null |
| timestamps | | |

Unique index on `(cutoff_id, employee_id, date)`. Written by `PayrollServices::generateCutoffSummary()` and `getOrCreateCutoffSummary()` in the same call that writes the `cutoff_summaries` aggregate row, directly from `computeRange()`'s per-day output — no additional computation, just persistence of what's already been calculated for the aggregate.

If more than one exemption applies to the same day (e.g. a leave application and a hospital order both filed for that date), `exemption_reason` takes the first match in this order: `leave` → `hospital_order` → `pass_slip`. This mirrors the original code's own implicit precedence (`$hasLeave` is checked before `$hasHospitalOrder` before `$hasApprovedPassSlip` when building `$hasExcusedRecord`) — it doesn't change which days get exempted, only which single label gets recorded for a day exempted by more than one source.

## Testing strategy

This algorithm has a documented history of subtle bugs (half-day-charge corrections, a revert-and-redo — see [[dtr-computation-rules]]), so extraction needs a safety net before anything moves:

1. **Characterization tests against current `printDTR` output**, written first, covering: plain late/undertime with break deduction, holiday-with-logs (penalty still applies), holiday-with-no-logs, half-day-missing-AM, half-day-missing-PM, gliding schedule, overnight spillover, leave exemption, hospital-order exemption, pass-slip exemption (personal and official), `skipAllAutomation` on. These lock in current behavior as the baseline.
2. **Extract** the logic into `DtrDayComputationService::computeRange()`. Run the same tests against its output — must match exactly.
3. **Wire `DTRServices`** to the new engine. Add tests proving `getCutoffBreakdown()`'s late/undertime/absence numbers for a range now equal what `computeRange()` (and therefore `printDTR`) would produce for the same days — this is the test that closes the original gap and should fail against the old `processLogs()` implementation.
4. Existing `PayrollCutoffTest`/`OvertimeApprovalTest`/DTR-related tests must continue passing unmodified (or with only mechanical updates, e.g. new table cleanup in tearDown) — no intentional behavior change to anything other than `DTRServices`'s output.

## Rollout

No migration/backfill step. New `cutoff_summary_days` table + `is_cutoff_adjustable`-style additive migration. `DtrDayComputationService` is additive (new file). `DTRServices` changes are internal to its methods — its public method signatures (`getTotalLateAbsent`, `getCutoffBreakdown`, etc.) are unchanged, so nothing calling it needs to change.
