# DTR Half-Day Flat Undertime Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When an employee on a full-day schedule has zero logs for one entire half of the day (AM or PM) but a complete IN+OUT pair for the other half, `printDTR` should charge a flat half of that schedule's rendered minutes as undertime instead of running the precise minute-by-minute late/departure math.

**Architecture:** Single isolated addition inside the existing per-day closure in `DTRController::printDTR`. A new `elseif`-equivalent branch (implemented as a guarded standalone `if`, matching the existing code's style of sibling `if` blocks rather than a true `elseif` chain) computes the flat value and populates the same `$data['undertime_hours']` / `$data['undertime_minutes']` fields and `$undertimeCount` / `$undertimeTotalMinutes` accumulators the rest of the method already uses. The existing precise-math branch gets one extra guard clause so it doesn't also fire on the same day.

**Tech Stack:** PHP 8 / Laravel 8, no new dependencies.

**Reference:** Design spec at `docs/superpowers/specs/2026-07-02-dtr-half-day-flat-undertime-design.md`.

**Testing note:** `DTRController::printDTR`'s per-day computation is an inline closure with no service extraction and no existing unit test coverage (the sibling `DTRServices` class tested in `tests/Unit/DTRServicesTest.php` is a separate implementation used by Payroll/Credit, not by `printDTR`). Extracting this closure into a testable service is out of scope per the design spec's non-goals. Verification for this plan is therefore: (1) `php -l` syntax check, (2) a hand-traced arithmetic check against the exact reported real-world case, and (3) a functional check against the running app printing the actual affected employee/date.

---

### Task 1: Add the flat half-day undertime branch

**Files:**
- Modify: `app/Http/Controllers/DTRController.php:1136-1170`

- [ ] **Step 1: Read the current state of the target region to confirm line numbers haven't shifted**

Run: view `app/Http/Controllers/DTRController.php` lines 1130-1175. Confirm it still matches:

```php
                        $hasIncompleteScheduledLogs =
                            $rawSched &&
                            !$isOffSchedule &&
                            !$isOvernightSpilloverOnly &&
                            ($hasTodayIn || $hasTodayOut || $firstIn || $lastOut) &&
                            !($firstIn && $lastOut);

                        if (!$skipLateUndertimeComputation && $hasIncompleteScheduledLogs) {
                            $firstIn = null;
                            $lastOut = null;

                            $absentUndertimeMinutes = 0;
                            if ($rawSched) {
                                $absentSchedIn = Carbon::parse($currentDay . ' ' . $rawSched->time_in);
                                $absentSchedOut = Carbon::parse($currentDay . ' ' . $rawSched->time_out);
                                if ($absentSchedOut->lte($absentSchedIn)) {
                                    $absentSchedOut->addDay();
                                }

                                $absentUndertimeMinutes = $data['rendered_minutes'] > 0
                                    ? (int) $data['rendered_minutes']
                                    : $absentSchedIn->diffInMinutes($absentSchedOut);
                            }

                            if ($absentUndertimeMinutes > 0) {
                                $undertimeCount++;
                                $undertimeTotalMinutes += $absentUndertimeMinutes;
                                $data['undertime_hours'] = floor($absentUndertimeMinutes / 60) ?: '';
                                $data['undertime_minutes'] = ($absentUndertimeMinutes % 60) ?: '';
                            }
                        }

                        // Do not compute late/undertime for days without an IN log.
                        // This avoids false penalties when an AM OUT is just a spillover from a previous day.
                        if (!$skipLateUndertimeComputation && $calcSched && !$hasApprovedPassSlip && $firstIn) {
```

If the surrounding code differs from this, stop and re-read the full file before proceeding — do not guess at line numbers.

- [ ] **Step 2: Insert the new detection variables and branch, and guard the precise-math branch**

Replace this exact block:

```php
                        if (!$skipLateUndertimeComputation && $hasIncompleteScheduledLogs) {
                            $firstIn = null;
                            $lastOut = null;

                            $absentUndertimeMinutes = 0;
                            if ($rawSched) {
                                $absentSchedIn = Carbon::parse($currentDay . ' ' . $rawSched->time_in);
                                $absentSchedOut = Carbon::parse($currentDay . ' ' . $rawSched->time_out);
                                if ($absentSchedOut->lte($absentSchedIn)) {
                                    $absentSchedOut->addDay();
                                }

                                $absentUndertimeMinutes = $data['rendered_minutes'] > 0
                                    ? (int) $data['rendered_minutes']
                                    : $absentSchedIn->diffInMinutes($absentSchedOut);
                            }

                            if ($absentUndertimeMinutes > 0) {
                                $undertimeCount++;
                                $undertimeTotalMinutes += $absentUndertimeMinutes;
                                $data['undertime_hours'] = floor($absentUndertimeMinutes / 60) ?: '';
                                $data['undertime_minutes'] = ($absentUndertimeMinutes % 60) ?: '';
                            }
                        }

                        // Do not compute late/undertime for days without an IN log.
                        // This avoids false penalties when an AM OUT is just a spillover from a previous day.
                        if (!$skipLateUndertimeComputation && $calcSched && !$hasApprovedPassSlip && $firstIn) {
```

with:

```php
                        if (!$skipLateUndertimeComputation && $hasIncompleteScheduledLogs) {
                            $firstIn = null;
                            $lastOut = null;

                            $absentUndertimeMinutes = 0;
                            if ($rawSched) {
                                $absentSchedIn = Carbon::parse($currentDay . ' ' . $rawSched->time_in);
                                $absentSchedOut = Carbon::parse($currentDay . ' ' . $rawSched->time_out);
                                if ($absentSchedOut->lte($absentSchedIn)) {
                                    $absentSchedOut->addDay();
                                }

                                $absentUndertimeMinutes = $data['rendered_minutes'] > 0
                                    ? (int) $data['rendered_minutes']
                                    : $absentSchedIn->diffInMinutes($absentSchedOut);
                            }

                            if ($absentUndertimeMinutes > 0) {
                                $undertimeCount++;
                                $undertimeTotalMinutes += $absentUndertimeMinutes;
                                $data['undertime_hours'] = floor($absentUndertimeMinutes / 60) ?: '';
                                $data['undertime_minutes'] = ($absentUndertimeMinutes % 60) ?: '';
                            }
                        }

                        // Flat half-day undertime: one entire half of the day (AM or PM) has zero
                        // logs while the other half has a complete IN+OUT pair, on a full-day
                        // schedule. Charge half the schedule's rendered minutes instead of running
                        // the precise late/departure math for that day.
                        $hasAmAttendance = ($data['date_timein_am'] !== '' || $data['date_timeout_am'] !== '');
                        $hasPmAttendance = ($data['date_timein_pm'] !== '' || $data['date_timeout_pm'] !== '');
                        $isFullDaySchedule = $rawSched
                            && !$isOffSchedule
                            && !$isOvernightSchedule
                            && (int) ($data['rendered_minutes'] ?? 0) > 240;
                        $isHalfDayMissingHalf = $isFullDaySchedule
                            && !$isOvernightSpilloverOnly
                            && $firstIn
                            && $lastOut
                            && ($hasAmAttendance xor $hasPmAttendance);

                        if (!$skipLateUndertimeComputation && $isHalfDayMissingHalf) {
                            $halfDayUndertimeMinutes = (int) round($data['rendered_minutes'] / 2);
                            if ($halfDayUndertimeMinutes > 0) {
                                $undertimeCount++;
                                $undertimeTotalMinutes += $halfDayUndertimeMinutes;
                                $data['undertime_hours'] = floor($halfDayUndertimeMinutes / 60) ?: '';
                                $data['undertime_minutes'] = ($halfDayUndertimeMinutes % 60) ?: '';
                            }
                        }

                        // Do not compute late/undertime for days without an IN log.
                        // This avoids false penalties when an AM OUT is just a spillover from a previous day.
                        if (!$skipLateUndertimeComputation && $calcSched && !$hasApprovedPassSlip && $firstIn && !$isHalfDayMissingHalf) {
```

- [ ] **Step 3: Syntax check**

Run: `php -l app/Http/Controllers/DTRController.php`
Expected: `No syntax errors detected in app/Http/Controllers/DTRController.php`

- [ ] **Step 4: Hand-trace the reported real case against the new code**

Using the schedule you confirmed matches `CWW` (`time_in` 07:00, `time_in_latest` 08:00, `time_out` 18:00, `time_out_latest` 19:00, `break_out` 12:00, `break_in` 13:00, type `GLIDING`) and the actual logs (IN 12:55 PM, OUT 18:33, no AM logs):

- `rendered_minutes` = (18:00 − 07:00 = 660 min span) − (60 min break) = 600
- `hasAmAttendance` = false (no AM logs at all)
- `hasPmAttendance` = true (both `date_timein_pm` and `date_timeout_pm` set)
- `isFullDaySchedule` = true (600 > 240, not OFF, not overnight)
- `firstIn` = 12:55 PM, `lastOut` = 18:33 (both matched within the schedule window, so both truthy)
- `isHalfDayMissingHalf` = true (`false xor true` = true, plus all other conditions true)
- `halfDayUndertimeMinutes` = round(600 / 2) = 300
- `$data['undertime_hours']` = `floor(300 / 60) ?: ''` = 5
- `$data['undertime_minutes']` = `(300 % 60) ?: ''` = `''` (300 % 60 is 0, and 0 is falsy, so the `?:` shorthand converts it to an empty string — this matches the existing pattern used everywhere else in this file for a clean whole-hour value, e.g. the identical `?: ''` construct in the `hasIncompleteScheduledLogs` branch just above)

Confirm this matches by re-reading the edited code and doing this arithmetic yourself — do not skip this step even though it looks like it was already done above.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/DTRController.php
git commit -m "$(cat <<'EOF'
feat: flat half-day undertime for full-day schedules missing one half

When AM or PM has zero logs but the other half has a complete in/out
pair on a full-day schedule, charge half the schedule's rendered
minutes as undertime instead of the precise late/departure math,
which produces odd non-round totals for this pattern.
EOF
)"
```

---

### Task 2: Functional verification against the running app

**Files:** None (verification only, no code changes).

- [ ] **Step 1: Identify the real employee and date from the original report**

You'll need the specific employee and date that produced the "4hrs and 47 minutes" result on the `CWW` schedule with logs 12:55 PM IN / 18:33 OUT, mentioned earlier in this conversation. If you don't have this handy, ask the user which employee/date to check before proceeding — do not guess or fabricate a test employee.

- [ ] **Step 2: Print the DTR for that employee/month via the running app**

Use the `run` skill (or start the app manually per its existing launch instructions) to open the HRIS app in a browser, navigate to the DTR print feature for that employee and the month containing the affected date, and generate the PDF.

- [ ] **Step 3: Confirm the affected date now shows 5h00m undertime**

Visually inspect the printed PDF row for that date. Expected: the undertime/late columns show `5` and `0` (5 hours, 0 minutes), not the previous `4` and some non-zero minutes value.

- [ ] **Step 4: Regression-check a normal complete-attendance day**

Pick a different date for the same employee (or any employee) where both AM and PM have logged in/out normally with no lateness. Confirm the DTR for that date is unchanged — no undertime marker, normal times displayed, matching pre-change behavior.

- [ ] **Step 5: Regression-check a single-missing-punch day, if one exists in the data**

If you can find a date where the employee has only one log for the entire day (e.g., clocked in but never clocked out, on either half), confirm it still shows the full-day flat undertime (the existing `hasIncompleteScheduledLogs` behavior), not the new half-day rule. This exercises the guard added in Task 1 Step 2 that keeps the two branches mutually exclusive.

- [ ] **Step 6: Report results to the user**

Summarize what was checked and the outcome for each of the above. If any check fails, stop and report the discrepancy rather than proceeding — do not adjust the code without going back through the design.
