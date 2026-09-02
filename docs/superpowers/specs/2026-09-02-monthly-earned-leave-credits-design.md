# Cutoff-Based Earned Leave Credits — Design

## Revision note

This spec originally proposed aggregating an employee's semi-monthly cutoff pairs into a calendar
month on the fly, on the assumption this org's cutoffs are always 1st-half/2nd-half pairs. After
implementing and testing that version, the user pointed out (screenshot of a real cutoff spanning
2026-06-01 to 2026-06-30) that cutoffs here are **arbitrary admin-defined ranges** — sometimes a
full month, sometimes a half-month — and directed the flow to compute directly from whichever
single cutoff is being viewed, with the trigger living inside that cutoff's own detail modal. This
revision replaces the monthly-aggregation design below with that simpler, corrected one. The
underlying bug fixes (net vs. gross, working-day-scaled lookup) are unchanged.

## Purpose

The "Compute Leave Credits Earned" flow (`CreditController::previewCutoffLeaveCredits` /
`applyCutoffLeaveCredits`) is meant to automate CSC-compliant VL/SL credit earning, but had two
real bugs (tracked in project memory `csc-earned-credit-automation-gaps`):

1. The computed late/undertime deduction is never actually subtracted from the stored balance
   (gross points applied instead of net).
2. The points lookup table assumes every period has exactly 30 working days, so full attendance
   only earns the full 1.250 in a period that happens to have exactly 30 working days.

A third originally-suspected gap — "credits should aggregate two semi-monthly cutoffs into one
calendar month" — turned out not to apply: the admin already scopes each cutoff to whatever period
they want credits computed for (per the screenshot, a full calendar month), so the fix is to
compute correctly from that one cutoff's own data, not to aggregate across cutoffs.

A separate finding — the current `#btn_compute_leave_credits` button is permanently `d-none` with
nothing in the file ever removing that class, so this flow has been unreachable from the UI — is
fixed here too by restoring a real, reachable button.

Bug #4 from the original audit (no `DB::transaction()` around the balance update) is **not
fixed**: every table involved is MyISAM (confirmed via `SHOW TABLE STATUS`), so `DB::transaction()`
would silently no-op, and converting to InnoDB was ruled out by the user as an out-of-scope schema
change. Accepted as-is.

## Scope

`app/Http/Controllers/CreditController.php`, `app/Services/LeaveCreditService.php`,
`routes/web.php`'s two `cutoff-leave-credits-*` routes, and the "Leave Credits Earned" section of
`resources/views/settings/cutoff_summaries.blade.php`. **No schema/migration changes.** DTR/cutoff
generation logic and payroll salary computation are untouched. No backfill of balances already
posted under the old logic.

## Behavior

1. The trigger lives inside the existing per-cutoff **Cutoff Summary Details** modal (the same one
   shown when clicking "View cutoff summary" on a generated cutoff) as a **"Compute Leave Credits
   Earned"** button — restoring the button that was there before but permanently hidden.
2. Clicking it computes directly from that cutoff's own `cutoff_summaries` rows (`days_present`,
   `total_working_days`, `late_minutes + undertime_minutes` per employee) — no cross-cutoff
   aggregation. The month/year used for the `monthly_earned_points` seasonal check and the
   idempotency key is derived from the cutoff's own `period_start`.
3. Results preview in the existing "Leave Credits Earned" modal (Employee / Total Present /
   Late+Undertime / Earned / Deducted / Net Points) before the admin confirms with **Save** — this
   preview-then-confirm shape is unchanged from the original flow.
4. Save applies `net_points` (gross minus the late/undertime deduction) to `leave_credits
   .based_credit`, fixing the gross/net bug.
5. The points lookup still uses the existing `monthly_earned_points` table (1–30 → points), but the
   lookup key is `round((days_present / total_working_days) * 30)` capped at 30 — using *that
   cutoff's own* `total_working_days` — instead of raw `days_present`, so full attendance always
   resolves to key 30 regardless of how many working days the cutoff period actually had. The old
   February-specific hack in `computeEarnedCredits()` is removed; this formula subsumes it.
6. Idempotency (`leave_credits.last_updated_month` keyed on the cutoff's `"$year-$month"`) is
   unchanged — re-running apply for a cutoff already processed for that month is a no-op, same as
   before. It's on the admin to scope cutoffs sensibly (e.g. don't generate two separate cutoffs
   for the same month and expect both applies to stack).

## Data Model

No changes.

## Backend Flow

**`app/Services/LeaveCreditService.php`:**
- `computeEarnedCredits($totalPresent, $totalLateUndertime, $totalWorkingDays = null, $month =
  null, $year = null)` — unchanged from the working-days-scaling fix: when `$totalWorkingDays` is
  provided and > 0, the lookup key scales to a 30-day equivalent; otherwise falls back to the
  original raw-present + February-hack behavior (keeps `tests/Unit/LeaveCreditServiceTest.php`'s
  existing 2-arg calls passing unchanged).
- `updateEarnedCredits()` unchanged.

**`app/Http/Controllers/CreditController.php`:**
- `previewCutoffLeaveCredits(Request $request)` — validates `cutoff_id` (required int) and
  optional `employees` array. Looks up the `cutoff_summaries_master` row by id (422 "Cutoff period
  not found" if missing), then computes per-employee figures directly from that cutoff's
  `cutoff_summaries` rows via a shared private `computeCreditsForCutoff()` helper.
- `applyCutoffLeaveCredits(Request $request)` — validates `cutoff_id` and required `employees`
  array, re-derives the same per-cutoff figures server-side (never trusts client-sent totals), and
  calls `updateEarnedCredits()` per employee with `net_points`.
- `computeCreditsForCutoff(int $cutoffId, ?array $employeeIds)` — the shared logic: resolve the
  cutoff master, derive `month`/`year` from its `period_start`, query that cutoff's
  `cutoff_summaries` rows (optionally filtered to given employees) joined to `tdh_user.users`, and
  run each through `computeEarnedCredits()`.

**`routes/web.php`:** both `cutoff-leave-credits-*` routes point at these two controller methods
(same names/URLs as the original code — no route changes needed for this revision beyond what was
already in place).

## UI

**`resources/views/settings/cutoff_summaries.blade.php`:**
- `#btn_compute_leave_credits` stays in `#cutoff_detail_modal`'s footer, with the `d-none` class
  removed so it's actually visible.
- The "View cutoff summary" button now also carries `data-id="${item.id}"` (the `getCutoffPeriods`
  response already includes `id`), captured into `currentCutoffDetail.id` alongside the existing
  start/end/title, so the credits flow knows which cutoff it's computing for.
- `computeLeaveCredits()` calls `previewCutoffLeaveCredits?cutoff_id=...` and renders the existing
  6-column `#leave_credits_modal` table (no Status column — there's no incomplete-period concept in
  this design).
- `saveLeaveCredits()` posts `{cutoff_id, employees}` to `applyCutoffLeaveCredits`.

## Testing / Verification

- `tests/Unit/LeaveCreditServiceTest.php`: unchanged from the working-days-scaling fix (5 tests,
  covers the scaling formula and the null-fallback backward-compatibility path).
- `tests/Feature/CutoffLeaveCreditsTest.php`: covers preview+apply computing correctly-scaled net
  points from one cutoff's own data (with deterministic overridden lookup-table rows, restored in
  `tearDown`, following `LeaveCreditServiceTest`'s pattern), idempotent re-apply not double-crediting,
  and preview rejecting a nonexistent `cutoff_id`.
- Manual UI smoke check recommended before relying on this in production: generate a cutoff, view
  its details, click "Compute Leave Credits Earned", confirm the preview numbers, Save, and check
  `hris.leave_credits`/`credit_earned_logs` — using the designated safe test employee
  (`reference-test-employee-apangcatan`), cleaned up afterward.
