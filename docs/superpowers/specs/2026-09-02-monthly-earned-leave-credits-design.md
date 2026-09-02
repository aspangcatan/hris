# Monthly Earned Leave Credits — Design

## Purpose

The "Compute Leave Credits Earned" flow (`CreditController::previewCutoffLeaveCredits` /
`applyCutoffLeaveCredits`) is meant to automate CSC-compliant monthly VL/SL credit earning, but
currently has four bugs (tracked in project memory `csc-earned-credit-automation-gaps`, re-verified
this session):

1. The computed late/undertime deduction is never actually subtracted from the stored balance
   (gross points applied instead of net).
2. The points lookup table assumes every month has exactly 30 working days, so full attendance
   only earns the full 1.250 in a month that happens to have exactly 30 working days.
3. The flow operates on one semi-monthly cutoff at a time; CSC credits are earned once per
   *calendar month*, and the idempotency guard means whichever half-month is processed first
   silently blocks the other half.
4. No transactional protection around the balance update + audit log.

This change fixes 1–3 by reworking the flow to operate at calendar-month granularity, aggregating
the existing per-cutoff `cutoff_summaries` rows on the fly. It keeps the flow manual/button-
triggered (no scheduled job) and makes **no schema changes** — everything works within the current
`cutoff_summaries`, `cutoff_summaries_master`, `monthly_earned_points`, `leave_credits`, and
`credit_earned_logs` tables as they exist today.

Bug #4 is explicitly **not fixed**: every table involved is MyISAM (confirmed via `SHOW TABLE
STATUS` this session), so `DB::transaction()` would silently no-op. Converting to InnoDB is an
engine-level schema change, which is out of scope per the "don't touch the schema" constraint on
this project. The atomicity gap is accepted as-is, matching the rest of this MyISAM schema.

**Bonus finding:** the current "Compute Leave Credits Earned" button
(`#btn_compute_leave_credits` in `cutoff_summaries.blade.php`) is permanently `d-none` with nothing
in the file ever removing that class — it has been unreachable from the UI. This explains why the
bugs above went unnoticed. This change removes that dead button and adds a real, reachable entry
point.

## Scope

`app/Http/Controllers/CreditController.php`, `app/Services/LeaveCreditService.php`,
`routes/web.php`'s two `cutoff-leave-credits-*` routes, and the "Leave Credits Earned" section of
`resources/views/settings/cutoff_summaries.blade.php`. The DTR/cutoff-generation logic
(`PayrollServices`, `DTRServices`, the cutoff-summary detail modal) and payroll salary computation
are untouched. No backfill of balances already posted under the old per-cutoff logic.

## Behavior Changes

1. The trigger becomes **"Compute Monthly Leave Credits"**: pick a Year + Month, not a cutoff
   period. Still 100% manual — admin clicks Preview, reviews, clicks Save.
2. Preview aggregates every `cutoff_summaries` row belonging to that calendar month (matched via
   `cutoff_summaries_master.period_start`) per employee, and reports each employee as **Complete**
   (has a row for every cutoff master in that month) or **Incomplete** (missing one or more —
   e.g. 2nd half not generated yet), naming the missing period(s).
3. Only Complete employees get a computed preview row (present/working-days/late-undertime summed
   across their cutoffs for the month, net points via the corrected formula). Incomplete employees
   are listed but excluded from computation.
4. Save/Apply processes only the employees the preview marked Complete, and credits `net_points`
   (gross minus deduction) — fixing the gross/net bug.
5. The points lookup still uses the existing `monthly_earned_points` table (1–30 → points), but the
   lookup key is now `round((total_present / total_working_days) * 30)` capped at 30, instead of
   raw `total_present` — so full attendance always resolves to key 30 (full 1.250) regardless of
   how many working days the real month had. The existing February-specific hack in
   `computeEarnedCredits()` (lines 22–27, forcing key 30 when present ≥ days-in-month) is removed;
   the general formula subsumes it for every month.
6. Idempotency (`leave_credits.last_updated_month` keyed on `"$year-$month"`) is unchanged — it's
   already correct once every apply operates at month granularity.

## Data Model

No changes. No new tables, no new columns, no engine changes.

## Backend Flow

**`app/Services/LeaveCreditService.php`:**
- `computeEarnedCredits()` gains one new optional parameter, inserted before the existing `$month`/
  `$year`: `computeEarnedCredits($totalPresent, $totalLateUndertime, $totalWorkingDays = null,
  $month = null, $year = null)`.
  - When `$totalWorkingDays` is provided and > 0: `$lookupPresent = min(30, (int) round(($totalPresent
    / $totalWorkingDays) * 30))`, and the February hack is skipped entirely (this path replaces it).
  - When `$totalWorkingDays` is `null` (no caller passes it after this change, but keeps
    `tests/Unit/LeaveCreditServiceTest.php`'s existing 2-arg calls behaving identically): falls
    back to exactly today's logic (raw `min($totalPresent, 30)` + the February hack), so those
    tests need no changes.
  - Rest of the method (deduction lookup, net points, return shape) is unchanged.
- `updateEarnedCredits()` is unchanged — the gross/net fix is entirely a call-site change in
  `CreditController`.

**`app/Http/Controllers/CreditController.php`:**
- `previewCutoffLeaveCredits()` → renamed `previewMonthlyLeaveCredits(Request $request)`.
  - Validates `year` (`required|integer`), `month` (`required|integer|between:1,12`), optional
    `employees` array.
  - Loads that month's `cutoff_summaries_master` rows (`whereYear('period_start', $year)
    ->whereMonth('period_start', $month)`). If none exist, returns `success: true, data: []` with a
    message that no cutoffs exist yet for that month.
  - Loads all `cutoff_summaries` rows whose `cutoff_id` is in that set (optionally filtered to the
    given employees), joined to `tdh_user.users` for names, grouped by `employee_id`.
  - Per employee: complete if the set of `cutoff_id`s they have rows for equals the full set of
    that month's cutoff master ids. Complete employees get their `days_present`,
    `total_working_days`, and `late_minutes + undertime_minutes` summed across their rows and
    passed into `computeEarnedCredits()`. Incomplete employees get a `status: 'incomplete'` row
    naming which cutoff period(s) (by title/date range) they're missing.
- `applyCutoffLeaveCredits()` → renamed `applyMonthlyLeaveCredits(Request $request)`.
  - Same validation, plus `employees` required (mirrors today's pattern where the frontend sends
    back the preview's employee list).
  - Re-derives month coverage and aggregates server-side from stored `cutoff_summaries` — never
    trusts client-sent totals, consistent with this project's frozen-snapshot/read-only-downstream
    convention. Silently skips any requested employee who isn't actually Complete (defense in
    depth; the UI won't normally send incomplete ones).
  - For each Complete employee: aggregates, computes via `computeEarnedCredits(...,
    $totalWorkingDays, $month, $year)`, then calls `updateEarnedCredits($employeeId, $year, $month,
    $presentSum, $lateUndertimeSum, $computed['net_points'], $computed['total_deductions'])` —
    passing `net_points` where today it passes `monthly_points_earned`.
  - Response includes which employee ids were applied vs. skipped as incomplete.

**`routes/web.php`:** update the two `cutoff-leave-credits-*` route definitions to point at the
renamed controller methods. Route names/URLs stay identical (`previewCutoffLeaveCredits` /
`applyCutoffLeaveCredits`), so no other change is needed anywhere that references them by name.

## UI

**`resources/views/settings/cutoff_summaries.blade.php`:**
- Remove `#btn_compute_leave_credits` from `#cutoff_detail_modal`'s footer (dead button, per-cutoff
  concept no longer applies there).
- Add a new toolbar button next to `#btn_create_cutoff`: **"Compute Monthly Leave Credits"**,
  opening a small new modal (`#monthly_credits_select_modal`) with a Year `<select>` and Month
  `<select>`, and a "Preview" action.
- Preview calls the (renamed, same-URL) `previewCutoffLeaveCredits` route with `year`/`month` query
  params and reuses the existing `#leave_credits_modal` table, with one added **Status** column
  ("Complete" / "Incomplete — missing: …"). Incomplete rows render muted and are excluded from the
  Save payload.
- `saveLeaveCredits()` now sends `{year, month, employees: <complete employee_ids only>}` to the
  (renamed, same-URL) `applyCutoffLeaveCredits` route.
- Replace the JS `currentCutoffDetail` state used by this flow with `currentMonthlyCredit = {year,
  month}`; the cutoff-detail modal's own DTR/attendance display is untouched.

## Testing / Verification

- `tests/Unit/LeaveCreditServiceTest.php`: keep the two existing 2-arg-call tests passing unchanged
  (validates the backward-compatible fallback). Add cases exercising the new working-days scaling —
  e.g. `computeEarnedCredits(20, 0, 22)` (20/22 present, scaled) vs. `computeEarnedCredits(22, 0,
  22)` (full attendance, any working-day count → key 30) — asserting the scaled lookup key resolves
  correctly against the seeded/overridden points table.
- New Feature test coverage for `CreditController`'s renamed methods (following
  `PayrollCutoffTest`'s pattern: synthetic high test-user id, `DatabaseTransactions` +
  explicit MyISAM-safe teardown, `withoutMiddleware()`):
  - Two seeded `cutoff_summaries` rows (1st + 2nd half) for one employee/month → preview reports
    `status: complete` with correctly summed present/working-days/late-undertime and net points;
    apply writes the net figure to `leave_credits.based_credit` and inserts a
    `credit_earned_logs` row.
  - Only one half seeded → preview reports `status: incomplete` naming the missing period; apply
    does not touch that employee's `leave_credits` row.
  - Re-running apply for an already-processed month is blocked by the existing
    `last_updated_month` idempotency guard (regression check that fixing the aggregation didn't
    disturb this).
- Manual UI smoke check using the safe test employee (`reference-test-employee-apangcatan`) since
  MySQL is running this session: generate both halves' cutoff summaries for a real month, use the
  new "Compute Monthly Leave Credits" button end-to-end, confirm the balance change in
  `hris.leave_credits`, then clean up.
