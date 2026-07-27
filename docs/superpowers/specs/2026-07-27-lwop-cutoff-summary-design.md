# LWOP in Cutoff Summary — Design

## Purpose

LWOP (Leave Without Pay) leave applications (`leave_applications.is_lwop = 1`, shipped in
[2026-07-24-lwop-leave-application-design.md](2026-07-24-lwop-leave-application-design.md)) are
currently invisible in the Cutoff Summary module: they're silently lumped into "Approved Leave"
and, worse, counted as "present" days, which inflates the earned-leave-credit computation on the
Cutoff Summary screen. This change breaks LWOP out into its own tracked bucket so it stops
distorting both the summary display and the earned-credits computation.

## Scope

Cutoff Summary screen (`resources/views/settings/cutoff_summaries.blade.php`) and its "Compute
Leave Credits Earned" flow. The payroll-run salary/deduction logic (`payroll_employees`,
`savePayrollEmployee`) is untouched — out of scope for this change.

## Behavior Changes

1. **New "LWOP Days" column** in the Cutoff Summary detail table: a count of that employee's
   approved (`is_lwop = 1`) leave dates within the cutoff period.
2. **"Approved Leave" becomes paid-leave-only.** Today it counts every approved leave date
   regardless of `is_lwop`. It will now exclude LWOP dates so the two columns don't double-count
   the same days.
3. **LWOP days become a neutral bucket for Days Present / Days Absent.** They stay excluded from
   "Days Absent" (still an authorized filing, not an unauthorized absence) but are now *also*
   excluded from "Days Present" (today they're incorrectly counted present). Since "Days Present"
   directly feeds `LeaveCreditService::computeEarnedCredits()`, this is what makes LWOP days
   reduce earned leave-credit points for the month — no change needed inside
   `LeaveCreditService` or `CreditController` themselves, since they already just consume
   `days_present` off the cutoff summary row.

## Data Model

Add `cutoff_summaries.lwop_days` (integer, default `0`), alongside the existing
`approved_leave_days`, `days_present`, `days_absent` columns.

## Backend Flow

**`app/Services/DTRServices.php`:**
- `getApprovedLeaveDatesByRange($userid, $start, $end)`: add a `where('la.is_lwop', 0)` filter —
  becomes paid-leave-only.
- New `getApprovedLwopDatesByRange($userid, $start, $end)`: same query shape, filtered
  `where('la.is_lwop', 1)`.
- `buildPresentAbsentByRange()`: unchanged call site (`getApprovedLeaveDatesByRange` already
  excludes LWOP now), so LWOP dates fall out of `$presentDays` automatically. They fall into the
  loop's `$absentDays` bucket internally, but that return value is discarded by
  `getTotalPresentByRange()` (`[$presentDays] = ...`), so this has no observable effect — LWOP
  days are correctly just absent from the "present" count.
- `buildComputationExemptions()`: `absenceExemptDates` (which drives `is_absent` inside
  `DtrDayComputationService::computeDay()`) must merge in LWOP dates alongside paid-leave,
  special-duty, and official-pass-slip dates, so LWOP days keep being excluded from
  "Days Absent". The `leaves` exemption set (drives late/undertime exemption + `exemption_reason`)
  also merges in LWOP dates, since an employee on an approved LWOP day has no attendance
  expectation either — no behavior change from today for late/undertime, this just keeps it
  working now that `getApprovedLeaveDatesByRange` no longer implicitly includes LWOP dates.
- `getCutoffBreakdown()`: add `'lwop_days' => count(array_unique($lwopDates))` to the returned
  array, computed from the new `getApprovedLwopDatesByRange()` call.

**`app/Services/PayrollServices.php`:**
- `getOrCreateCutoffSummary()` and `generateCutoffSummary()`: read `lwop_days` off the
  `getCutoffBreakdown()` result and write it into the `cutoff_summaries` insert/upsert payload.

**Migration:** add `lwop_days` integer column (default `0`) to `cutoff_summaries`, positioned
after `approved_leave_days`.

**`app/Http/Controllers/PayrollController.php`:** no change needed —
`getCutoffSummaryPreview()` already does `select('cs.*', ...)`, so `lwop_days` flows through
automatically once the column exists.

## UI

`resources/views/settings/cutoff_summaries.blade.php`:
- Detail modal table (`#cutoff_detail_modal`): add an "LWOP Days" `<th>` after "Approved Leave",
  and render `item.lwop_days ?? 0` in `loadCutoffDetails()`'s row-building JS.

The "Leave Credits Earned" preview modal (`#leave_credits_modal`) is left as-is — it already
reflects the LWOP-adjusted `days_present` indirectly via `total_present`, no new column needed
there.

## Testing / Verification

No automated DB tests exist on this project (RefreshDatabase is unsafe — it would wipe the live
shared database). Verification is manual, via tinker + the actual UI:

1. File and approve an LWOP leave application for a date inside an existing cutoff period.
2. Generate/regenerate the cutoff summary for that employee and period.
3. Confirm the new LWOP Days column shows the correct count, "Approved Leave" does not include
   that date, and "Days Absent" does not include it either.
4. Confirm `days_present` for that cutoff no longer includes the LWOP date (compare against a
   before/after tinker call to `DTRServices::getTotalPresentByRange`).
5. Run "Compute Leave Credits Earned" for that employee/period and confirm the earned points
   reflect the reduced present-day count.
6. File and approve a *regular* (non-LWOP) leave application in the same period and confirm it
   still shows under "Approved Leave" and still counts as present — no regression.
