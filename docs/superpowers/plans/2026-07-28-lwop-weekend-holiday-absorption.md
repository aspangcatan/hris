# LWOP Weekend/Holiday Absorption Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `DTRServices::getApprovedLwopDatesByRange()` correctly absorb bridged Saturdays/Sundays/holidays into the LWOP date set whenever a continuous LWOP run exceeds 7 calendar days, while leaving short/intermittent LWOP untouched (weekends/holidays never counted) — per `docs/superpowers/specs/2026-07-28-lwop-weekend-holiday-absorption-design.md`.

**Architecture:** A pure, DB-free private method (`calculateEffectiveLwopDates`) does the run-grouping and span-threshold absorption logic given plain arrays of LWOP dates and holiday dates. `getApprovedLwopDatesByRange()` becomes a thin wrapper: fetch a padded (±60 day) window of raw LWOP + holiday dates from the DB, hand them to the pure function, then intersect the result back down to the caller's requested date range. Existing callers (`getCutoffBreakdown()`, `buildComputationExemptions()`) need no changes since the method's return contract (array of date strings) is unchanged.

**Tech Stack:** Laravel 8, PHPUnit + Mockery (facade mocking via `DB::shouldReceive`), Carbon for date math. No database access in tests — this project's live `hris` database must never be touched by the test suite (`RefreshDatabase` is unsafe here).

---

### Task 1: Pure run-detection/absorption calculator

**Files:**
- Modify: `app/Services/DTRServices.php` (add 3 new private methods near `getApprovedLwopDatesByRange`, currently at [DTRServices.php:372-385](app/Services/DTRServices.php#L372))
- Test: `tests/Unit/DTRServicesTest.php`

- [ ] **Step 1: Write the failing tests**

Add this reflection helper and six test methods to `tests/Unit/DTRServicesTest.php`, right before the closing `}` of the `DTRServicesTest` class:

```php
    private function invokePrivateMethod(string $method, array $args)
    {
        $reflection = new \ReflectionMethod($this->dtrService, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($this->dtrService, $args);
    }

    /** @test */
    public function it_does_not_absorb_weekend_for_a_short_intermittent_lwop_run()
    {
        // Fri 2025-09-05 LWOP, weekend either side, no other filed date nearby.
        $effective = $this->invokePrivateMethod('calculateEffectiveLwopDates', [
            ['2025-09-05'],
            [],
        ]);

        sort($effective);
        $this->assertSame(['2025-09-05'], $effective);
    }

    /** @test */
    public function it_absorbs_bridged_weekend_when_continuous_run_exceeds_seven_days()
    {
        // Mon 09-01 .. Fri 09-05 LWOP, weekend 09-06/07, Mon 09-08 .. Tue 09-09 LWOP.
        // Span = 9 calendar days (> 7) -> weekend absorbed.
        $effective = $this->invokePrivateMethod('calculateEffectiveLwopDates', [
            ['2025-09-01', '2025-09-02', '2025-09-03', '2025-09-04', '2025-09-05', '2025-09-08', '2025-09-09'],
            [],
        ]);

        sort($effective);
        $this->assertSame([
            '2025-09-01', '2025-09-02', '2025-09-03', '2025-09-04',
            '2025-09-05', '2025-09-06', '2025-09-07', '2025-09-08', '2025-09-09',
        ], $effective);
    }

    /** @test */
    public function it_does_not_absorb_weekend_when_run_span_is_exactly_seven_days()
    {
        // Tue 09-02 .. Fri 09-05 LWOP, weekend 09-06/07, Mon 09-08 LWOP.
        // Span = 7 calendar days exactly -> NOT absorbed (rule requires > 7).
        $effective = $this->invokePrivateMethod('calculateEffectiveLwopDates', [
            ['2025-09-02', '2025-09-03', '2025-09-04', '2025-09-05', '2025-09-08'],
            [],
        ]);

        sort($effective);
        $this->assertSame([
            '2025-09-02', '2025-09-03', '2025-09-04', '2025-09-05', '2025-09-08',
        ], $effective);
    }

    /** @test */
    public function it_absorbs_weekend_when_run_span_is_eight_days()
    {
        // Same as above plus Tue 09-09 LWOP -> span = 8 calendar days (> 7) -> absorbed.
        $effective = $this->invokePrivateMethod('calculateEffectiveLwopDates', [
            ['2025-09-02', '2025-09-03', '2025-09-04', '2025-09-05', '2025-09-08', '2025-09-09'],
            [],
        ]);

        sort($effective);
        $this->assertSame([
            '2025-09-02', '2025-09-03', '2025-09-04', '2025-09-05',
            '2025-09-06', '2025-09-07', '2025-09-08', '2025-09-09',
        ], $effective);
    }

    /** @test */
    public function it_bridges_a_midweek_holiday_and_absorbs_it_when_run_exceeds_seven_days()
    {
        // Mon 09-01, Tue 09-02 LWOP, Wed 09-03 declared holiday (bridges), Thu 09-04,
        // Fri 09-05 LWOP, weekend 09-06/07, Mon 09-08, Tue 09-09 LWOP.
        // Span = 9 calendar days (> 7) -> holiday AND weekend both absorbed.
        $effective = $this->invokePrivateMethod('calculateEffectiveLwopDates', [
            ['2025-09-01', '2025-09-02', '2025-09-04', '2025-09-05', '2025-09-08', '2025-09-09'],
            ['2025-09-03'],
        ]);

        sort($effective);
        $this->assertSame([
            '2025-09-01', '2025-09-02', '2025-09-03', '2025-09-04', '2025-09-05',
            '2025-09-06', '2025-09-07', '2025-09-08', '2025-09-09',
        ], $effective);
    }

    /** @test */
    public function it_treats_an_unauthorized_absence_day_as_breaking_run_continuity()
    {
        // Mon 09-01 LWOP, then Tue/Wed/Thu (09-02..09-04) are genuine unfiled absences
        // (not LWOP, not holiday, not weekend), then Fri 09-05 LWOP.
        // The gap is NOT bridged, so these are two separate 1-day runs -> no absorption.
        $effective = $this->invokePrivateMethod('calculateEffectiveLwopDates', [
            ['2025-09-01', '2025-09-05'],
            [],
        ]);

        sort($effective);
        $this->assertSame(['2025-09-01', '2025-09-05'], $effective);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/DTRServicesTest.php --filter="absorb|unauthorized_absence"`

Expected: FAIL, all 6 new tests — `ReflectionException: Method calculateEffectiveLwopDates does not exist` (the method doesn't exist yet).

- [ ] **Step 3: Implement the pure calculator**

In `app/Services/DTRServices.php`, add these three private methods immediately after the existing `getApprovedLwopDatesByRange` method (which Task 2 will modify next — for now just add these alongside it, don't touch it yet):

```php
    private function calculateEffectiveLwopDates(array $lwopDates, array $holidayDates): array
    {
        $sortedDates = array_values(array_unique($lwopDates));
        sort($sortedDates);

        $holidaySet = array_fill_keys($holidayDates, true);
        $runs = $this->groupIntoLwopRuns($sortedDates, $holidaySet);

        $effectiveDates = [];
        foreach ($runs as $run) {
            foreach ($run as $date) {
                $effectiveDates[] = $date;
            }

            $first = Carbon::parse($run[0]);
            $last = Carbon::parse(end($run));
            $span = $first->diffInDays($last) + 1;

            if ($span > 7) {
                $cursor = $first->copy();
                while ($cursor->lte($last)) {
                    $dateString = $cursor->toDateString();
                    $isBridgeDay = $cursor->isWeekend() || isset($holidaySet[$dateString]);
                    if ($isBridgeDay && !in_array($dateString, $run, true)) {
                        $effectiveDates[] = $dateString;
                    }
                    $cursor->addDay();
                }
            }
        }

        return array_values(array_unique($effectiveDates));
    }

    private function groupIntoLwopRuns(array $sortedDates, array $holidaySet): array
    {
        $runs = [];
        $currentRun = [];

        foreach ($sortedDates as $date) {
            if (empty($currentRun)) {
                $currentRun[] = $date;
                continue;
            }

            $previousDate = end($currentRun);
            if ($this->isBridgedGap($previousDate, $date, $holidaySet)) {
                $currentRun[] = $date;
            } else {
                $runs[] = $currentRun;
                $currentRun = [$date];
            }
        }

        if (!empty($currentRun)) {
            $runs[] = $currentRun;
        }

        return $runs;
    }

    private function isBridgedGap(string $fromDate, string $toDate, array $holidaySet): bool
    {
        $cursor = Carbon::parse($fromDate)->copy()->addDay();
        $to = Carbon::parse($toDate);

        while ($cursor->lt($to)) {
            $dateString = $cursor->toDateString();
            if (!$cursor->isWeekend() && !isset($holidaySet[$dateString])) {
                return false;
            }
            $cursor->addDay();
        }

        return true;
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/DTRServicesTest.php --filter="absorb|unauthorized_absence"`

Expected: PASS (6 tests, 6 assertions or more).

- [ ] **Step 5: Commit**

```bash
git add app/Services/DTRServices.php tests/Unit/DTRServicesTest.php
git commit -m "$(cat <<'EOF'
feat: add pure LWOP run-detection/absorption calculator

Groups approved LWOP dates into continuous runs (bridged by
weekends/holidays) and absorbs the bridged non-working days into
the effective LWOP set only when a run's span exceeds 7 calendar
days, per CSC Omnibus Rules on Leave. Not yet wired into
getApprovedLwopDatesByRange() - that's the next task.
EOF
)"
```

---

### Task 2: Wire the calculator into `getApprovedLwopDatesByRange()`

**Files:**
- Modify: `app/Services/DTRServices.php:372-385` (the existing `getApprovedLwopDatesByRange` method)
- Test: `tests/Unit/DTRServicesTest.php`

- [ ] **Step 1: Write the failing test**

Add this test to `tests/Unit/DTRServicesTest.php`, after the tests added in Task 1:

```php
    /** @test */
    public function it_pads_the_query_window_and_returns_only_dates_within_the_requested_range()
    {
        DB::shouldReceive('table->join->where->where->whereRaw->whereBetween->pluck->map->unique->values->toArray')
            ->once()
            ->andReturn([
                '2025-08-29', // Friday before the requested range - buffer-only, part of the same bridged run
                '2025-09-01', '2025-09-02', '2025-09-03', '2025-09-04', '2025-09-05',
                '2025-09-08', '2025-09-09',
                '2025-10-01', // Unrelated date well after the requested range - buffer-only
            ]);

        DB::shouldReceive('table->whereBetween->pluck->map->toArray')
            ->once()
            ->andReturn([]);

        $effective = $this->invokePrivateMethod('getApprovedLwopDatesByRange', [1, '2025-09-01', '2025-09-09']);

        sort($effective);
        $this->assertSame([
            '2025-09-01', '2025-09-02', '2025-09-03', '2025-09-04',
            '2025-09-05', '2025-09-06', '2025-09-07', '2025-09-08', '2025-09-09',
        ], $effective);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/DTRServicesTest.php --filter=it_pads_the_query_window_and_returns_only_dates_within_the_requested_range`

Expected: FAIL — the current implementation's DB chain is `table->join->where->where->whereRaw->whereBetween->pluck->map->toArray` (no `unique`/`values`), so Mockery reports no matching handler for the chain in the test, or the assertion fails because the returned array still contains `2025-08-29` and `2025-10-01` (no intersection filtering exists yet) and lacks the absorbed weekend dates.

- [ ] **Step 3: Implement the buffered fetch + intersection**

Replace the existing `getApprovedLwopDatesByRange` method (currently [DTRServices.php:372-385](app/Services/DTRServices.php#L372-L385)):

```php
    private function getApprovedLwopDatesByRange($userid, $startDate, $endDate)
    {
        $bufferedStart = Carbon::parse($startDate)->copy()->subDays(60)->toDateString();
        $bufferedEnd = Carbon::parse($endDate)->copy()->addDays(60)->toDateString();

        $bufferedLwopDates = DB::table('hris.leave_applications as la')
            ->join('hris.leave_date_applied as lda', 'la.id', '=', 'lda.leave_application_id')
            ->where('la.user_id', $userid)
            ->where('la.is_lwop', 1)
            ->whereRaw('LOWER(la.status) = ?', ['approved'])
            ->whereBetween('lda.date_applied', [$bufferedStart, $bufferedEnd])
            ->pluck('lda.date_applied')
            ->map(function ($date) {
                return Carbon::parse($date)->toDateString();
            })
            ->unique()
            ->values()
            ->toArray();

        $bufferedHolidayDates = $this->getHolidayDatesByRange($bufferedStart, $bufferedEnd);

        $effectiveDates = $this->calculateEffectiveLwopDates($bufferedLwopDates, $bufferedHolidayDates);

        return array_values(array_filter($effectiveDates, function ($date) use ($startDate, $endDate) {
            return $date >= $startDate && $date <= $endDate;
        }));
    }
```

This is a straight replacement — the three helper methods added in Task 1 (`calculateEffectiveLwopDates`, `groupIntoLwopRuns`, `isBridgedGap`) stay exactly as written.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/DTRServicesTest.php --filter=it_pads_the_query_window_and_returns_only_dates_within_the_requested_range`

Expected: PASS.

Then run the full test file to confirm no regressions on the pre-existing tests (`it_counts_present_days_from_expected_schedules_only`, `it_returns_late_and_absence_totals_using_plotted_schedule_dates`) and Task 1's tests:

Run: `vendor/bin/phpunit tests/Unit/DTRServicesTest.php`

Expected: PASS (all tests in the file, 0 failures).

- [ ] **Step 5: Commit**

```bash
git add app/Services/DTRServices.php tests/Unit/DTRServicesTest.php
git commit -m "$(cat <<'EOF'
feat: apply CSC weekend/holiday LWOP absorption in cutoff generation

getApprovedLwopDatesByRange() now queries a padded window so runs
crossing the requested date range's boundary are fully visible,
runs the result through the Task 1 absorption calculator, then
intersects back down to the requested range. getCutoffBreakdown()
and buildComputationExemptions() both consume this transparently,
so cutoff_summaries.lwop_days is now CSC-compliant with no changes
needed in PayrollServices or CreditController.
EOF
)"
```

---

### Task 3: Manual verification against a live cutoff

**Files:** none (manual verification only — no automated DB tests exist in this project; `RefreshDatabase` is unsafe against the shared live `hris` database)

- [ ] **Step 1: Pick or create a test employee with no other LWOP history in the target month.**

- [ ] **Step 2: File and approve a short, intermittent LWOP application** for a single weekday immediately before or after a weekend (e.g. a Friday), inside an existing (or new) cutoff period.

- [ ] **Step 3: Generate/regenerate the cutoff summary** for that employee/period via the Cutoff Summary UI ("+ New Cutoff Summary" or the regenerate action), and confirm via `php artisan tinker`:

```php
app(\App\Services\DTRServices::class)->getCutoffBreakdown($employeeId, $periodStart, $periodEnd)['lwop_days'];
```

Expected: `1` (the single filed day only — no weekend inflation).

- [ ] **Step 4: File and approve a continuous LWOP application spanning more than 7 calendar days**, crossing at least one weekend, inside/around the same or a different cutoff period.

- [ ] **Step 5: Regenerate that cutoff summary and re-run the same tinker check.**

Expected: `lwop_days` count includes the bridged weekend day(s) in addition to the filed weekdays.

- [ ] **Step 6: Confirm no regression** on an employee with ordinary approved (non-LWOP) leave and normal attendance in the same period — `days_present`, `approved_leave_days`, and `days_absent` should be unaffected by this change.

---

## Post-Implementation Note

This plan fixes `cutoff_summaries.lwop_days` computation only. It intentionally does **not** touch:
- `LeaveCreditService`'s fixed 30-day point lookup table (still not scaled to actual monthly working days)
- The missing monthly aggregation of the two semi-monthly cutoffs before posting earned credits
- The gross-vs-net earned-points bug in `CreditController::applyCutoffLeaveCredits()`
- The missing DB transaction around the leave-credit balance update

These remain known, separately tracked gaps for future work.
