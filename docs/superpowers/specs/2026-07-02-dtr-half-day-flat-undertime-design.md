# DTR Half-Day Flat Undertime Rule

## Problem

`printDTR` (in [DTRController.php](../../../app/Http/Controllers/DTRController.php)) computes late/undertime minute-by-minute: late = grace-period-end to actual clock-in, undertime = actual clock-out to schedule-end. This is accurate for normal attendance, but produces odd, non-round totals (e.g. 4h27m, 4h47m) for a specific recurring pattern: an employee on a full-day schedule who skips one entire half of the day (no logs at all) but has a clean, complete IN+OUT pair for the other half.

Example: employee on the `CWW` schedule (07:00–08:00 AM in / 06:00–07:00 PM out, 12:00–1:00 PM break, GLIDING — 10hrs rendered) logs in at 12:55 PM and out at 18:33, with zero AM logs. HR already treats this pattern as a half-day absence and wants the DTR to reflect a clean half of the schedule's rendered hours (5h00m for this schedule) as undertime, not the literal minute-precision gap.

## Goal

When an employee's timelog for a day shows one entire half (AM or PM, split at 12:00 NN) with zero logs while the other half has a complete IN+OUT pair, on a full-day schedule, charge a flat **half of that schedule's rendered minutes** as undertime instead of running the precise late/departure math.

## Non-Goals

- No change to any other computation path: normal complete-day attendance (both halves have logs), single-missing-punch days (already handled by the existing "incomplete logs" flat-full-day-undertime path), OFF days, overnight-spanning schedules, holidays, pass slips, overtime markers.
- No new `schedule_master_lists` row or column. The half-day value is derived at runtime as `rendered_minutes / 2` from the employee's already-assigned schedule for that date, so it can't drift out of sync with the schedule's actual hours.
- No change to how the flat value is displayed (reuses existing `undertime_hours` / `undertime_minutes` fields and count/total accumulators).

## Design

### Trigger conditions (all must hold for a given day)

1. A schedule is assigned that day (`$rawSched` present), it is not an OFF day, and it is not an overnight-spanning schedule.
2. The schedule is a "full day" schedule: `rendered_minutes > 240` (reuses the existing 4-hour threshold already used elsewhere in the file to identify half-day-by-design schedules like `HALF`).
3. Exactly one of AM/PM has zero logs (`date_timein_am === '' && date_timeout_am === ''`, or the PM equivalent) while the other half has a complete matched IN+OUT pair (`$firstIn && $lastOut` both present, as already computed by the existing schedule-matching logic).

### Behavior when triggered

- Skip the existing "incomplete logs" flat-absent branch and the precise late/departure math branch for that day (new `elseif` sibling, mutually exclusive with both).
- Compute `halfDayUndertimeMinutes = round(rendered_minutes / 2)`.
- If `> 0`: increment `$undertimeCount`, add to `$undertimeTotalMinutes`, and set `$data['undertime_hours']` / `$data['undertime_minutes']` from it — identical mechanics to the existing incomplete-logs branch, just a different source number.
- `$data['late_minutes']` is left at its default (0) — consistent with how the existing incomplete-logs branch already behaves (it also only sets undertime fields).
- This value still flows into the existing `$hasLateOrUndertime` check used for day markers (e.g. OB) — no changes needed there.

### Placement

Inserted as a new branch in the existing `if (!$skipLateUndertimeComputation && $hasIncompleteScheduledLogs) { ... } elseif (...) { /* precise math */ }` chain around [DTRController.php:1143-1285](../../../app/Http/Controllers/DTRController.php#L1143-L1285), computed alongside the existing `$hasIncompleteScheduledLogs` variable using data already available at that point (`$data['date_timein_am']` etc., `$firstIn`, `$lastOut`, `$isOvernightSchedule`, `$data['rendered_minutes']`).

## Testing

Manually verify against the reported case (CWW schedule, 12:55 PM IN / 18:33 OUT, no AM logs) that the printed DTR shows 5h00m undertime for that day, and spot-check that normal complete-day attendance and single-missing-punch days on the same schedule are unaffected.
