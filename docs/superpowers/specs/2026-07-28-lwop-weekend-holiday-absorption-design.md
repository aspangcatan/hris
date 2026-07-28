# LWOP Weekend/Holiday Absorption — Design

## Purpose

CSC's Omnibus Rules on Leave treat intermittent LWOP and continuous LWOP differently with
respect to weekends and holidays:

- **Intermittent LWOP**: a weekend/holiday adjacent to a single (or short) LWOP stretch is never
  itself counted as LWOP. Only the actual filed working day(s) count.
- **Continuous LWOP exceeding 7 consecutive calendar days**: the Saturdays, Sundays, and
  Holidays *within* that continuous run are absorbed into the LWOP count too.

Today, `cutoff_summaries.lwop_days` (added in
[2026-07-27-lwop-cutoff-summary-design.md](2026-07-27-lwop-cutoff-summary-design.md)) is just a
distinct count of explicitly filed `is_lwop=1` approved leave dates — it has no concept of runs
or absorption. The intermittent case already happens to produce the right answer today (weekends
are never filed as leave dates, so they were never counted), but the continuous >7-day case is
entirely unimplemented: a long LWOP stretch never pulls its bridged weekends/holidays into the
count.

## Scope

This change is scoped **only** to correctly computing the effective LWOP date set (and therefore
`cutoff_summaries.lwop_days`) at cutoff-generation time. Explicitly out of scope, deferred to
later work:

- Reworking `LeaveCreditService`'s point lookup to scale against actual working days of the
  month instead of a fixed 30-day table.
- Combining the two semi-monthly cutoffs into one monthly earned-credits run.
- The gross-vs-net earned-points bug in `applyCutoffLeaveCredits()`.
- Wrapping the leave-credit balance update in a DB transaction.

Those are known, separately tracked gaps. Once a cutoff is generated, downstream earned-credit
computation should read `cutoff_summaries.days_present` / `cutoff_summaries.lwop_days` directly
and never re-query DTR/leave data — this design is what makes `lwop_days` trustworthy enough for
that to be safe.

## Rule Definition

Given an employee's approved (`is_lwop=1`, `status='approved'`) leave dates, sorted:

1. **Group into runs.** Two LWOP dates belong to the same run if every calendar day between them
   is one of: another LWOP date, a weekend day (Sat/Sun), or a declared holiday. A genuine
   working day with no LWOP filed (including an unauthorized absence) breaks the run — it does
   **not** bridge two LWOP stretches into one run, and does not itself become part of any run.
2. **Compute each run's span**: `last_date - first_date + 1` calendar days.
3. **Span ≤ 7**: only the actually-filed LWOP dates in the run count as LWOP. Weekends/holidays
   inside the run stay uncounted (neutral — not LWOP, not present, not absent).
4. **Span > 7**: every weekend/holiday date inside `[first_date, last_date]` is added to the
   effective LWOP set, in addition to the filed dates. This is an all-or-nothing determination
   per run — once a run's total span exceeds 7 calendar days, all of its bridged weekend/holiday
   days count, not just the ones after day 7.

## Algorithm & Placement

All of this lives inside `DTRServices::getApprovedLwopDatesByRange($userid, $startDate, $endDate)`
([DTRServices.php:372-385](../../../app/Services/DTRServices.php#L372)). Its return contract is
unchanged — a flat array of date strings — so both existing callers
(`getCutoffBreakdown()` and `buildComputationExemptions()`) automatically pick up correct
behavior with no changes on their end.

**Step 1 — Buffered fetch.** Query approved LWOP dates and holiday dates over a padded window:
`startDate - 60 days` to `endDate + 60 days`. The padding exists solely to detect runs that start
before or continue after the requested range — the final returned set is still intersected down
to `[startDate, endDate]` in Step 3.

Rationale for a fixed 60-day buffer rather than an unbounded/dynamic search: it comfortably
covers realistic continuous-LWOP durations without an unbounded query, and even if a genuine run
is longer than the buffer, everything relevant to *this* cutoff's own date range is still
correctly classified (the far-out edge of a very long run is irrelevant to what falls inside
`[startDate, endDate]`).

**Step 2 — Pure run/absorption calculation.** Extract a pure function (no DB access) that takes:
- a list of LWOP date strings (buffered range)
- a list of holiday date strings (buffered range)

and returns the effective LWOP date list (filed dates + absorbed weekend/holiday dates for
qualifying runs), per the Rule Definition above. Because it's pure, it's directly unit-testable
with hand-built fixtures — no database involved. Suggested location: a private method on
`DTRServices` (e.g. `calculateEffectiveLwopDates(array $lwopDates, array $holidayDates): array`),
kept free of any `DB::` calls so it can be tested in isolation.

**Step 3 — Intersect and return.** Filter the pure function's output down to dates within
`[startDate, endDate]` before returning from `getApprovedLwopDatesByRange()`.

## Edge Cases

- **Mid-week holidays bridge runs too**, not just weekends — the rule text lists Saturdays,
  Sundays, and Holidays together, and the algorithm treats them identically.
- **Unauthorized absences do not bridge runs.** A workday with no LWOP filed and no attendance
  breaks continuity, even if it sits between two LWOP stretches. It is not itself absorbed, and
  it splits what would otherwise be one run into two independent (likely both ≤7-day, thus
  non-absorbing) runs.
- **Exactly 7 calendar days does not qualify.** The rule requires the span to exceed 7 days; a
  run spanning exactly Mon-Sun (7 days) does not absorb its weekend, only Mon-through-next-Mon (8
  days) or longer does.
- **Weekend/holiday LWOP dates should never occur as filed dates** in `leave_applications` (no
  schedule is plotted for rest days, so nothing gets filed against them) — the algorithm doesn't
  need to special-case a filed date that's already a weekend/holiday.
- **Cross-month and cross-cutoff runs** are handled naturally by the buffered fetch — there's no
  special-casing needed for a run that happens to cross a cutoff or calendar-month boundary.

## Testing / Verification

No automated DB tests exist on this project (`RefreshDatabase` is unsafe — it wipes the live
shared database). Plan:

1. **Unit tests for the pure function** (`calculateEffectiveLwopDates`), no DB required:
   - Intermittent case: Thu+Fri LWOP, Sat/Sun weekend, Mon present → effective set is
     `{Thu, Fri}` only (span = 2, not absorbed).
   - Continuous case: Mon-Fri LWOP, weekend, following Mon-Tue LWOP → span = 9 days (>7) →
     effective set includes the bridged Sat/Sun in addition to the 7 filed dates.
   - Boundary case: exactly 7-day span → weekend NOT absorbed.
   - Boundary case: 8-day span → weekend IS absorbed.
   - Mid-week holiday bridging two LWOP stretches, combined span > 7 → holiday absorbed.
   - Unauthorized-absence-breaks-continuity case: two short LWOP stretches separated by a
     genuine absent workday → treated as two separate runs, neither absorbing its weekend.
2. **Manual verification via tinker + UI**, following the same pattern as the prior LWOP design
   doc: file/approve a long LWOP stretch spanning a weekend for a test employee, regenerate the
   cutoff summary, and confirm `lwop_days` includes the bridged weekend days when the span
   exceeds 7, and excludes them for a short intermittent stretch.
