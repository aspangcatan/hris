# DTR: Additive Half-Day Late Charge

**Date:** 2026-07-02
**Status:** Approved (amended 2026-07-02 — see Correction)

## Context

In `printDTR` (`DTRController.php`), a prior change (commit `88d27ca`, reverted in `ba47641`) detected the case where one entire half of a full-day schedule (AM or PM) has zero timelogs while the other half has a complete IN+OUT pair, and *replaced* the precise late/undertime computation with a flat charge of half the schedule's rendered minutes.

The new requirement is different: instead of replacing the precise computation, **add** a flat half-day charge on top of it. The precise computation for the half that *is* present must also be re-scoped to that half's own boundary — otherwise it independently produces a large, meaningless number (today it compares the present half's actual time against the *full day's* schedule boundary, e.g. comparing a PM-only arrival against the AM's scheduled time-in-latest).

## Rules

1. When one half of a full-day schedule has zero logs and the other half has a complete IN+OUT pair, charge a flat amount equal to half the schedule's rendered minutes (e.g. a 10-hour schedule → 5 hours). ~~Categorized as **late**.~~ **See Correction below: categorization depends on which half is missing.**
2. Additionally, compute late and undertime normally for the half that *is* present, but scoped to that half's own boundary (not the full day's), and add this to the flat charge from rule 1.

## Detection

Reuse the existing detection condition from the reverted commit, with one added guard:

```
$hasAmAttendance = ($data['date_timein_am'] !== '' || $data['date_timeout_am'] !== '');
$hasPmAttendance = ($data['date_timein_pm'] !== '' || $data['date_timeout_pm'] !== '');
$isFullDaySchedule = $rawSched && !$isOffSchedule && !$isOvernightSchedule
    && (int) ($data['rendered_minutes'] ?? 0) > 240;
$hasBreakConfigured = !empty($calcSched->break_out) && !empty($calcSched->break_in);
$isHalfDayMissingHalf = $isFullDaySchedule
    && $hasBreakConfigured
    && !$isOvernightSpilloverOnly
    && $firstIn
    && $lastOut
    && ($hasAmAttendance xor $hasPmAttendance);
```

`$hasBreakConfigured` is new: without a break window, there is no defined AM/PM split for that schedule, so the rule does not apply.

This detection sits inside the existing `if (!$skipLateUndertimeComputation && $calcSched && !$hasApprovedPassSlip && $firstIn)` block (same exemptions as before: approved leave, hospital order, approved pass slip, no plotted schedule, and overnight-spillover-only days all continue to skip this rule, since they already skip the enclosing block).

## Computation changes

Inside the existing late/undertime block:

**Late (arrival) side** — only changes when AM is missing (PM-only day):
- Today: compares `firstIn` (PM's actual arrival) against `calcSched->time_in` / `time_in_latest` (AM's boundary).
- New: when `$isHalfDayMissingHalf && !$hasAmAttendance`, compare against `calcSched->break_in` for both the schedIn and schedInLatest reference (no grace period — there is no PM-specific grace field). When AM *is* present, behavior is unchanged.

**Undertime (departure) side** — only changes when PM is missing (AM-only day):
- Today: compares `lastOut` (AM's actual departure) against `calcSched->time_out` / gliding-adjusted requirement (PM's boundary).
- New: when `$isHalfDayMissingHalf && !$hasPmAttendance`, use `calcSched->break_out` as `$requiredOut` directly, and skip the gliding offset branch entirely (gliding shifts the day's departure requirement based on arrival time; not meaningful when the day was cut short at the half boundary). When PM *is* present, behavior is unchanged.

The existing break-overlap subtraction logic (for both late and undertime) needs no special-casing: since the adjusted boundaries no longer span the break window, the overlap will naturally compute to zero.

**Flat half-day addition:**
```
if ($isHalfDayMissingHalf) {
    $halfDayLateMinutes = (int) round($data['rendered_minutes'] / 2);
    if ($halfDayLateMinutes > 0) {
        $lateMinutes += $halfDayLateMinutes;
    }
}
```
This is added into `$lateMinutes` before the existing tally code (`lateCount++`, `lateTotalMinutes +=`, shared `undertime_hours`/`undertime_minutes` display fields) runs, so no new display fields or summary counters are needed — the flat charge flows through the same paths as ordinary lateness.

## Out of scope

- Schedules without a configured break (no AM/PM split possible).
- Half-day schedules (`rendered_minutes <= 240`) — they have no "other half" to be missing.
- Overnight schedules and overnight-spillover-only days — unaffected, same as before.

## Correction (2026-07-02)

Rule 1 as originally written and implemented always charged the flat half-day amount to `$lateMinutes`, regardless of which half was missing. This was wrong for the missing-PM case: an employee who works the AM half and never returns for PM left/failed to return, which is an **undertime** concept (not completing required hours), not a **late** concept (tardy arrival). Charging the whole flat amount as "late" caused the `L -` / `U -` summary box (and the underlying `late_total_minutes` / `undertime_total_minutes` totals used elsewhere) to misattribute large chunks of undertime as lateness.

Reported case: employee on a GLIDING full-day schedule (07:00–08:00 AM in-window, 12:00–1:00 PM break, 6:00–7:00 PM out-window), logged IN 07:09 / OUT 12:01, no PM logs. Correct result: `late = 0` (07:09 is within the gliding grace window), `undertime = 300 min` (5h, the entire missing PM half). The original rule instead produced `late = 300 min`, `undertime = 0`.

**Corrected rule:** the flat half-day charge is categorized based on which half is missing:
- **AM missing** (only showed up for PM) → charge to `$lateMinutes` (treated as an extreme late arrival — they effectively missed their entire scheduled start).
- **PM missing** (showed up for AM, never returned) → charge to `$undertimeMinutes` (treated as undertime — they didn't complete the required hours).

```php
if ($isHalfDayMissingHalf) {
    $halfDayMinutes = (int) round($data['rendered_minutes'] / 2);
    if ($halfDayMinutes > 0) {
        if (!$hasAmAttendance) {
            $lateMinutes += $halfDayMinutes;
        } else {
            $undertimeMinutes += $halfDayMinutes;
        }
    }
}
```

This categorization is independent of schedule `type` (GLIDING/FIXED/etc.) — type only affects the precise computation for the half that *is* present (per the "Computation changes" section above), not which bucket the flat charge lands in.
