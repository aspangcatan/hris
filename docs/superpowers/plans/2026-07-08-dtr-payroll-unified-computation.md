# DTR/Payroll Unified Late-Undertime Computation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `printDTR` (the printed DTR report) and the cutoff-generation path that feeds payroll deductions use one shared late/undertime/absence computation, so an employee's printed DTR and their actual payroll deduction can never disagree again.

**Architecture:** Extract the money-relevant core of `DTRController::printDTR()`'s per-day closure — schedule matching, break deduction, half-day/gliding/overnight logic, late/undertime arithmetic — into a new `App\Services\DtrDayComputationService::computeRange()`. `printDTR` and `DTRServices` both call it. A new `cutoff_summary_days` table persists the per-day facts alongside the existing `cutoff_summaries` aggregate, so payroll disputes don't require recomputing against logs that may have since changed.

**Tech Stack:** Laravel 8, PHPUnit (`DatabaseTransactions` against the real shared `hris`/`tdh_user` MySQL databases — no separate test DB, see [[feedback-refreshdatabase-wipes-live-db]]), Carbon.

**Spec:** `docs/superpowers/specs/2026-07-08-dtr-payroll-unified-computation-design.md`

**Important scope note discovered during planning:** `printDTR` has no "is this day absent" boolean of its own — it just renders empty cells with markers (leave code, HOLIDAY, etc.). The only existing absence-determination logic is `DTRServices::buildPresentAbsentByRange()` (a day is "present" if exempted by leave/special-duty/official-pass-slip, or has both a first-in and last-out; otherwise "absent" on an expected work date). That logic is not part of the late/undertime divergence this project fixes, so `computeRange()`'s `is_absent` output preserves `buildPresentAbsentByRange()`'s semantics as-is rather than inventing something new — it's being consolidated into one call, not re-derived.

---

### Task 1: Characterization test harness + first three scenarios

**Files:**
- Create: `tests/Feature/DtrPrintCharacterizationTest.php`

This test proves what `printDTR` currently outputs for specific, carefully-constructed scenarios, *before any extraction happens*. It captures `printDTR`'s internal `$dtrData` (the array handed to the PDF view) via a `View::composer` hook on `pdf.old_dtr`, short-circuiting before the actual PDF renders (wkhtmltopdf is slow; we don't need the PDF, just the data that would have been rendered).

- [ ] **Step 1: Write the test file with the harness and scenario (a): plain late + undertime with break deduction**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class DtrPrintCharacterizationTest extends TestCase
{
    use DatabaseTransactions;

    private int $userId = 9501;
    private int $scheduleMasterId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        DB::table('hris.dtr_logs')->where('userid', $this->userId)->delete();
        DB::table('hris.schedules')->where('employee_id', $this->userId)->delete();
        DB::table('hris.schedule_master_lists')->where('code', 'CHAR_TEST_SCHED')->delete();
        DB::table('tdh_user.users')->where('id', $this->userId)->delete();
        DB::table('hris.calendar_holidays')->where('description', 'Characterization Test Holiday')->delete();
        DB::table('hris.leave_applications')->where('user_id', $this->userId)->delete();
    }

    private function actAsAdmin(): void
    {
        Auth::shouldReceive('id')->andReturn($this->userId);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'admin']);
    }

    private function seedUser(): void
    {
        DB::table('tdh_user.users')->where('id', $this->userId)->delete();
        DB::table('tdh_user.users')->insert([
            'id' => $this->userId,
            'fname' => 'Charz',
            'lname' => 'Tester',
            'username' => 'chartester' . $this->userId,
            'password' => 'x',
            'division' => 2,
            'section' => 21,
            'designation' => 52,
        ]);
    }

    /**
     * @param string $timeIn e.g. '08:00:00'
     * @param string $timeOut e.g. '17:00:00'
     * @param string|null $breakOut e.g. '12:00:00'
     * @param string|null $breakIn e.g. '13:00:00'
     * @param string $type 'FIXED' or 'GLIDING'
     */
    private function seedSchedule(string $date, string $timeIn, string $timeOut, ?string $breakOut = null, ?string $breakIn = null, string $type = 'FIXED', ?string $timeInLatest = null, ?string $timeOutLatest = null): void
    {
        DB::table('hris.schedule_master_lists')->where('code', 'CHAR_TEST_SCHED')->delete();
        $this->scheduleMasterId = DB::table('hris.schedule_master_lists')->insertGetId([
            'code' => 'CHAR_TEST_SCHED',
            'label' => 'Characterization Test Schedule',
            'type' => $type,
            'time_in' => $timeIn,
            'time_in_latest' => $timeInLatest ?? $timeIn,
            'time_out' => $timeOut,
            'time_out_latest' => $timeOutLatest ?? $timeOut,
            'break_out' => $breakOut,
            'break_in' => $breakIn,
        ]);

        DB::table('hris.schedules')->updateOrInsert(
            ['employee_id' => $this->userId, 'schedule_date' => $date],
            ['schedule_id' => $this->scheduleMasterId]
        );
    }

    private function seedLog(string $dateTime, string $status): void
    {
        DB::table('hris.dtr_logs')->insert([
            'userid' => $this->userId,
            'status' => $status,
            'type' => 2,
            'created_at' => $dateTime,
            'updated_at' => $dateTime,
        ]);
    }

    /**
     * Calls printDTR for the given month/year and captures the $dtrData array
     * that would have been handed to the PDF view, without actually rendering
     * a PDF (wkhtmltopdf is slow and we don't need the binary output).
     *
     * @return array The 'employees' data array (one entry per requested user)
     */
    private function capturePrintDtrData(int $month, int $year): array
    {
        $captured = null;

        View::composer('pdf.old_dtr', function ($view) use (&$captured) {
            $captured = $view->getData()['employees'];
            throw new \RuntimeException('__captured__');
        });

        try {
            $this->withoutMiddleware()->get("/print_dtr?userid={$this->userId}&month={$month}&year={$year}&cutoff=full&enable_automation=yes");
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== '__captured__') {
                throw $e;
            }
        } finally {
            View::composer('pdf.old_dtr', function () {});
        }

        $this->assertNotNull($captured, 'printDTR never reached PDF rendering - check the route/permissions.');

        return $captured;
    }

    private function dayResult(array $employeeData, int $day): array
    {
        $match = collect($employeeData['dtrs'])->firstWhere('day', $day);
        $this->assertNotNull($match, "Day {$day} not found in printDTR output.");
        return $match;
    }

    /** @test */
    public function plain_late_arrival_and_early_departure_deduct_break_time_correctly(): void
    {
        $this->actAsAdmin();
        $this->seedUser();
        // 08:00-17:00 schedule, 12:00-13:00 break. Arrives 13:07 (late arrival
        // spans the break), leaves 16:30 (undertime, no break overlap).
        $this->seedSchedule('2027-04-05', '08:00:00', '17:00:00', '12:00:00', '13:00:00');
        $this->seedLog('2027-04-05 13:07:00', 'IN');
        $this->seedLog('2027-04-05 16:30:00', 'OUT');

        $data = $this->capturePrintDtrData(4, 2027);
        $day = $this->dayResult($data[0], 5);

        // Raw late diff is 13:07 - 08:00 = 307 min; minus the 60-min break
        // overlap = 247 min (4h 7m), matching the documented dtr-computation-rules example.
        $this->assertEquals(247, $day['late_minutes']);
        // Undertime: 17:00 - 16:30 = 30 min, no break overlap in that window.
        $this->assertEquals(30, (int) $day['undertime_hours'] * 60 + (int) $day['undertime_minutes'] - 247);
    }

    /** @test */
    public function holiday_with_actual_timelogs_still_computes_penalty(): void
    {
        $this->actAsAdmin();
        $this->seedUser();
        DB::table('hris.calendar_holidays')->updateOrInsert(
            ['date' => '2027-04-09'],
            ['type' => 'Regular', 'description' => 'Characterization Test Holiday']
        );
        $this->seedSchedule('2027-04-09', '08:00:00', '17:00:00', '12:00:00', '13:00:00');
        $this->seedLog('2027-04-09 08:30:00', 'IN');
        $this->seedLog('2027-04-09 17:00:00', 'OUT');

        $data = $this->capturePrintDtrData(4, 2027);
        $day = $this->dayResult($data[0], 9);

        // 30 min late despite the holiday - holidays with logs still get penalized.
        $this->assertEquals(30, $day['late_minutes']);
    }

    /** @test */
    public function half_day_missing_am_charges_flat_half_plus_precise_pm_math(): void
    {
        $this->actAsAdmin();
        $this->seedUser();
        // Full-day 08:00-17:00 schedule (540 rendered minutes), break 12-1.
        // No AM logs at all; arrives for PM only at 13:10, leaves on time.
        $this->seedSchedule('2027-04-12', '08:00:00', '17:00:00', '12:00:00', '13:00:00');
        $this->seedLog('2027-04-12 13:10:00', 'IN');
        $this->seedLog('2027-04-12 17:00:00', 'OUT');

        $data = $this->capturePrintDtrData(4, 2027);
        $day = $this->dayResult($data[0], 12);

        // Half of rendered_minutes (480 non-break minutes / 2 = 240) added to
        // late, plus 10 min precise lateness against break_in (13:00) = 250.
        $this->assertEquals(250, $day['late_minutes']);
    }
}
```

- [ ] **Step 2: Run the tests to confirm they pass against current `printDTR` (nothing extracted yet)**

Run: `php artisan test --filter=DtrPrintCharacterizationTest`
Expected: `3 passed`. If any of these fail, the fixture is wrong (not the production code, which memory already confirms is correct) — check the exact times/dates against [[dtr-computation-rules]] before changing anything.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/DtrPrintCharacterizationTest.php
git commit -m "test: characterize current printDTR late/undertime output (plain, holiday, half-day-AM)"
```

---

### Task 2: Three more characterization scenarios

**Files:**
- Modify: `tests/Feature/DtrPrintCharacterizationTest.php`

- [ ] **Step 1: Add gliding-schedule, overnight-spillover, and leave-exemption scenarios**

```php
    /** @test */
    public function gliding_schedule_shifts_required_out_by_the_late_arrival_offset(): void
    {
        $this->actAsAdmin();
        $this->seedUser();
        // Gliding 07:00(-08:00 latest)/16:00(-17:00 latest), no break.
        $this->seedSchedule('2027-04-14', '07:00:00', '16:00:00', null, null, 'GLIDING', '08:00:00', '17:00:00');
        // Arrives 07:30 (within gliding window, 30 min after core time_in) - required out shifts to 16:30.
        $this->seedLog('2027-04-14 07:30:00', 'IN');
        $this->seedLog('2027-04-14 16:15:00', 'OUT');

        $data = $this->capturePrintDtrData(4, 2027);
        $day = $this->dayResult($data[0], 14);

        // No lateness (07:30 is within the gliding IN window). Required out
        // shifted to 16:30 (16:00 + 30min offset); left at 16:15 = 15 min undertime.
        $this->assertEquals(0, $day['late_minutes']);
        $this->assertEquals(15, (int) $day['undertime_hours'] * 60 + (int) $day['undertime_minutes']);
    }

    /** @test */
    public function overnight_schedule_matches_out_log_on_the_following_calendar_day(): void
    {
        $this->actAsAdmin();
        $this->seedUser();
        // Overnight 22:00-06:00 (out is on the next calendar day).
        $this->seedSchedule('2027-04-16', '22:00:00', '06:00:00');
        $this->seedLog('2027-04-16 22:00:00', 'IN');
        $this->seedLog('2027-04-17 06:00:00', 'OUT');

        $data = $this->capturePrintDtrData(4, 2027);
        $day = $this->dayResult($data[0], 16);

        $this->assertEquals(0, $day['late_minutes']);
        $this->assertEquals('', $day['undertime_hours']);
        $this->assertEquals('', $day['undertime_minutes']);
    }

    /** @test */
    public function approved_leave_waives_late_undertime_computation_entirely(): void
    {
        $this->actAsAdmin();
        $this->seedUser();
        $this->seedSchedule('2027-04-19', '08:00:00', '17:00:00', '12:00:00', '13:00:00');
        // Arrives very late - would normally be a large penalty.
        $this->seedLog('2027-04-19 11:00:00', 'IN');
        $this->seedLog('2027-04-19 17:00:00', 'OUT');

        $leaveTypeId = DB::table('hris.leave_types')->where('code', 'VL')->value('id');
        $this->assertNotNull($leaveTypeId, 'Seed a VL leave type before running this test.');

        $applicationId = DB::table('hris.leave_applications')->insertGetId([
            'user_id' => $this->userId,
            'status' => 'Approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('hris.leave_date_applied')->insert([
            'leave_application_id' => $applicationId,
            'leave_type_id' => $leaveTypeId,
            'date_applied' => '2027-04-19',
            'hours' => 8,
        ]);

        $data = $this->capturePrintDtrData(4, 2027);
        $day = $this->dayResult($data[0], 19);

        // Waived entirely despite the late arrival - late_minutes stays 0.
        $this->assertEquals(0, $day['late_minutes']);
    }
```

Add corresponding cleanup for `leave_applications`/`leave_date_applied` to the `cleanup()` method:

```php
    private function cleanup(): void
    {
        DB::table('hris.dtr_logs')->where('userid', $this->userId)->delete();
        DB::table('hris.schedules')->where('employee_id', $this->userId)->delete();
        DB::table('hris.schedule_master_lists')->where('code', 'CHAR_TEST_SCHED')->delete();
        DB::table('hris.leave_date_applied')->whereIn(
            'leave_application_id',
            DB::table('hris.leave_applications')->where('user_id', $this->userId)->pluck('id')
        )->delete();
        DB::table('hris.leave_applications')->where('user_id', $this->userId)->delete();
        DB::table('tdh_user.users')->where('id', $this->userId)->delete();
        DB::table('hris.calendar_holidays')->where('description', 'Characterization Test Holiday')->delete();
    }
```

- [ ] **Step 2: Run all six characterization tests**

Run: `php artisan test --filter=DtrPrintCharacterizationTest`
Expected: `6 passed`

Note for later coverage (not required to prove the extraction, since it's the identical `$hasExcusedRecord` code branch as the leave test above): hospital-order exemption and pass-slip exemption follow the exact same waive-entirely pattern — seed `hris.hospital_order_employees`/`hris.pass_slips` with `status='approved'` instead of a leave application, same assertion (`late_minutes` stays 0 despite a late arrival).

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/DtrPrintCharacterizationTest.php
git commit -m "test: characterize gliding, overnight, and leave-exemption printDTR output"
```

---

### Task 3: Extract `DtrDayComputationService`

**Files:**
- Create: `app/Services/DtrDayComputationService.php`
- Reference (read-only, do not modify yet): `app/Http/Controllers/DTRController.php:801-1397`

This is a **precise extraction**, not a rewrite. Copy the logic body from `DTRController.php` lines 801-1397 (the `map(function ($day) use (...) {...})` closure body) into the new method below, applying exactly these substitutions and nothing else:

| Old (DTRController closure) | New (`DtrDayComputationService`) |
|---|---|
| `$month, $year, $day` + `Carbon::createFromDate($year, $month, $day)` | Iterate `CarbonPeriod::create($startDate, $endDate)`, use `$date->toDateString()` as `$currentDay` directly |
| `$startDay, $endDay` + `isInsideCutoff` check (lines 868, 928-929) | Delete entirely — every date in the loop is already in range |
| `$data['day']`, `$data['day_name']`, `$data['off_label']`, `$data['suppress_off_label']`, `$data['planned_schedule']` | Delete — presentational, stays in the controller |
| `$holidays[$currentDay]` | `$holidays[$currentDay]` (param renamed from closure `use`, same shape: `[date => ['type'=>.., 'description'=>..]]`) |
| `$leaves[$currentDay]` (array with code/hours) | `$exemptions['leaves']` — flat `[date => true]`, only `array_key_exists` is used for computation |
| `$hospitalOrders[$currentDay]` | `$exemptions['hospitalOrders']` — flat `[date => true]` |
| `$approvedPassSlips[$currentDay]`, `$officialPassSlips[$currentDay]` | `$exemptions['passSlips']` — flat `[date => true]` (any approved pass slip, either type — matches `$hasApprovedPassSlip`'s original semantics used for the computation-skip gate; official-vs-personal only ever affected the day_marker display, not the number, so it's dropped here) |
| `$overtimeRequests` | Delete entirely — confirmed by re-reading lines 1360-1372: `$overtimeRequests` only feeds the `day_marker` display string, never the late/undertime/absence computation |
| `$data['undertime_hours']` / `$data['undertime_minutes']` (combined display fields, lines 1163-1164, 1332-1334) | Don't build these — return the raw `$lateMinutes` and `$undertimeMinutes` locals directly (they exist before being combined for display) |
| `$data['late_minutes'] = $lateMinutes;` (line 1328) | Return `late_minutes` from the same local `$lateMinutes` |
| `&$lateCount, &$undertimeCount, &$lateTotalMinutes, &$undertimeTotalMinutes` | Delete — these are printDTR's own summary counters, not part of a single day's result |
| Lines 1338-1394 (day_marker / LEAVE-HOLIDAY-HO-OB-OT marker building) | Delete entirely — presentational, stays in the controller |

- [ ] **Step 1: Create the new service**

```php
<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class DtrDayComputationService
{
    /**
     * Computes late/undertime/absence facts for one employee across a date
     * range. This is the single source of truth shared by printDTR (display)
     * and cutoff generation (payroll deduction) - see
     * docs/superpowers/specs/2026-07-08-dtr-payroll-unified-computation-design.md
     *
     * @param Collection $schedules Keyed by date string ('Y-m-d'), values are
     *   rows with time_in/time_in_latest/time_out/time_out_latest/break_out/
     *   break_in/type/code. Must include one buffer day before $startDate and
     *   one after $endDate for overnight-shift lookups to work at range edges.
     * @param Collection $logs dtr_logs rows (created_at, status) spanning
     *   $startDate minus one day through $endDate plus one day.
     * @param array $exemptions ['holidays' => [date => ['type'=>,'description'=>]],
     *   'leaves' => [date => true], 'hospitalOrders' => [date => true],
     *   'passSlips' => [date => true], 'absenceExemptDates' => [date => true]].
     *   IMPORTANT: 'absenceExemptDates' is a *different* set than the other
     *   three, used only for is_absent - it must be pre-computed by the
     *   caller as the union of leave dates, hospital-order dates, approved
     *   overtime dates, and *official-only* pass slip dates. This exactly
     *   mirrors DTRServices::buildPresentAbsentByRange()'s existing (and
     *   uncontested) exemption set, which differs from the late/undertime
     *   exemption set: it includes overtime (which never affects lateness)
     *   and only official pass slips (not personal ones, which do exempt
     *   lateness but were never treated as an absence exemption). Passing an
     *   empty array here is fine when the caller (printDTR) never reads the
     *   is_absent field.
     * @return Collection Keyed by date string => ['late_minutes' => int,
     *   'undertime_minutes' => int, 'is_absent' => bool, 'is_holiday' => bool,
     *   'exemption_reason' => ?string]
     */
    public function computeRange(
        int $employeeId,
        string $startDate,
        string $endDate,
        Collection $schedules,
        Collection $logs,
        array $exemptions,
        bool $skipAllAutomation = false
    ): Collection {
        $holidays = $exemptions['holidays'] ?? [];
        $leaves = $exemptions['leaves'] ?? [];
        $hospitalOrders = $exemptions['hospitalOrders'] ?? [];
        $passSlips = $exemptions['passSlips'] ?? [];
        $absenceExemptDates = $exemptions['absenceExemptDates'] ?? [];

        $expectedDates = $this->resolveExpectedWorkDates($schedules, $startDate, $endDate, $holidays);

        $results = [];
        foreach (CarbonPeriod::create($startDate, $endDate) as $date) {
            $currentDay = $date->toDateString();
            $results[$currentDay] = $this->computeDay(
                $currentDay,
                $schedules,
                $logs,
                $holidays,
                $leaves,
                $hospitalOrders,
                $passSlips,
                $skipAllAutomation,
                in_array($currentDay, $expectedDates, true),
                !empty($absenceExemptDates[$currentDay])
            );
        }

        return collect($results);
    }

    /**
     * Preserves DTRServices::buildPresentAbsentByRange()'s existing semantics
     * as-is (uncontested, not part of the late/undertime divergence this
     * service fixes): schedule-plotted dates if any exist in range, otherwise
     * every non-holiday weekday.
     */
    private function resolveExpectedWorkDates(Collection $schedules, string $startDate, string $endDate, array $holidays): array
    {
        if ($schedules->isNotEmpty()) {
            return $schedules->keys()
                ->filter(function ($date) use ($startDate, $endDate) {
                    return $date >= $startDate && $date <= $endDate;
                })
                ->reject(fn($date) => array_key_exists($date, $holidays))
                ->values()
                ->all();
        }

        $dates = [];
        foreach (CarbonPeriod::create($startDate, $endDate) as $date) {
            if ($date->isWeekday() && !array_key_exists($date->toDateString(), $holidays)) {
                $dates[] = $date->toDateString();
            }
        }
        return $dates;
    }

    /**
     * Break minutes actually overlapping the employee's plotted shift, e.g.
     * a 12:00-13:00 break inside an 08:00-17:00 shift = 60 minutes. Returns 0
     * if the schedule has no break configured. Verbatim from DTRController's
     * printDTR (formerly a closure, now a method).
     */
    private function getScheduledBreakMinutes($schedule, string $scheduleDate): int
    {
        if (!$schedule || empty($schedule->break_out) || empty($schedule->break_in)) {
            return 0;
        }

        $scheduleIn = Carbon::parse($scheduleDate . ' ' . $schedule->time_in);
        $scheduleOut = Carbon::parse($scheduleDate . ' ' . $schedule->time_out);
        if ($scheduleOut->lte($scheduleIn)) {
            $scheduleOut->addDay();
        }

        $breakOut = Carbon::parse($scheduleDate . ' ' . $schedule->break_out);
        $breakIn = Carbon::parse($scheduleDate . ' ' . $schedule->break_in);

        if ($breakOut->lt($scheduleIn)) {
            $breakOut->addDay();
        }
        if ($breakIn->lte($breakOut)) {
            $breakIn->addDay();
        }

        $effectiveBreakStart = $breakOut->copy()->max($scheduleIn);
        $effectiveBreakEnd = $breakIn->copy()->min($scheduleOut);

        if ($effectiveBreakEnd->lte($effectiveBreakStart)) {
            return 0;
        }

        return $effectiveBreakStart->diffInMinutes($effectiveBreakEnd);
    }

    /**
     * Extracted from DTRController::printDTR()'s per-day closure (lines
     * 886-1336 as of the pre-extraction version of that file). Presentational
     * fields (day_marker, planned_schedule text, off_label) are intentionally
     * not reproduced here - they stay in the controller. Everything that
     * feeds the actual late/undertime/absence NUMBERS is preserved exactly.
     */
    private function computeDay(
        string $currentDay,
        Collection $schedules,
        Collection $logs,
        array $holidays,
        array $leaves,
        array $hospitalOrders,
        array $passSlips,
        bool $skipAllAutomation,
        bool $isExpectedWorkDate,
        bool $isAbsenceExempt
    ): array {
        $isHoliday = array_key_exists($currentDay, $holidays);
        $hasLeave = array_key_exists($currentDay, $leaves);
        $hasHospitalOrder = !empty($hospitalOrders[$currentDay]);
        $hasApprovedPassSlip = !empty($passSlips[$currentDay]);
        $hasExcusedRecord = $hasLeave || $hasHospitalOrder || $hasApprovedPassSlip;
        $hasComputationExemption = $hasExcusedRecord;

        $exemptionReason = null;
        if ($hasLeave) {
            $exemptionReason = 'leave';
        } elseif ($hasHospitalOrder) {
            $exemptionReason = 'hospital_order';
        } elseif ($hasApprovedPassSlip) {
            $exemptionReason = 'pass_slip';
        } elseif ($isHoliday) {
            $exemptionReason = 'holiday';
        }

        $schedule = $schedules[$currentDay] ?? null;
        $isOffSchedule = false;
        $renderedMinutes = 0;

        if ($schedule) {
            $scheduleCode = trim((string) ($schedule->code ?? ''));
            if ($scheduleCode !== '' && strtoupper($scheduleCode) === 'OFF') {
                $isOffSchedule = true;
            }

            if (!$isOffSchedule) {
                $scheduledBreakMinutes = $this->getScheduledBreakMinutes($schedule, $currentDay);
                $scheduleInForSpan = Carbon::parse($currentDay . ' ' . $schedule->time_in);
                $scheduleOutForSpan = Carbon::parse($currentDay . ' ' . $schedule->time_out);
                if ($scheduleOutForSpan->lte($scheduleInForSpan)) {
                    $scheduleOutForSpan->addDay();
                }
                $scheduledSpanMinutes = $scheduleInForSpan->diffInMinutes($scheduleOutForSpan);
                $renderedMinutes = max($scheduledSpanMinutes - $scheduledBreakMinutes, 0);
            }
        }

        $lateMinutes = 0;
        $undertimeMinutes = 0;
        $isAbsent = false;
        $firstIn = null;
        $lastOut = null;

        $dayLogs = $logs->filter(fn($log) => Carbon::parse($log->created_at)->toDateString() === $currentDay);

        if ($dayLogs->isNotEmpty()) {
            $amIns = [];
            $pmIns = [];
            $amOuts = [];
            $pmOuts = [];

            foreach ($dayLogs as $log) {
                $timeString = Carbon::parse($log->created_at)->format('H:i:s');

                if ($log->status === 'IN') {
                    if ($timeString < '12:00:00') {
                        $amIns[] = $timeString;
                    } else {
                        $pmIns[] = $timeString;
                    }
                } else {
                    if ($timeString < '13:00:00') {
                        $amOuts[] = $timeString;
                    } else {
                        $pmOuts[] = $timeString;
                    }
                }
            }

            $hasAmIn = !empty($amIns);
            $hasPmIn = !empty($pmIns);
            $hasAmOut = !empty($amOuts);
            $hasPmOut = !empty($pmOuts);

            $skipLateUndertimeComputation = $hasComputationExemption || $skipAllAutomation;

            $rawSched = $schedule;
            $isOvernightSchedule = false;
            if ($rawSched) {
                $rawSchedIn = Carbon::parse($currentDay . ' ' . $rawSched->time_in);
                $rawSchedOut = Carbon::parse($currentDay . ' ' . $rawSched->time_out);
                $isOvernightSchedule = $rawSchedOut->lte($rawSchedIn);
            }

            $previousDay = Carbon::parse($currentDay)->subDay()->toDateString();
            $prevSched = $schedules[$previousDay] ?? null;
            $previousDayIsOvernightSchedule = false;
            if ($prevSched) {
                $prevSchedIn = Carbon::parse($previousDay . ' ' . $prevSched->time_in);
                $prevSchedOut = Carbon::parse($previousDay . ' ' . $prevSched->time_out);
                $previousDayIsOvernightSchedule = $prevSchedOut->lte($prevSchedIn);
            }

            if ($rawSched && !$isOffSchedule) {
                if ($isOvernightSchedule) {
                    $nextDay = Carbon::parse($currentDay)->addDay()->toDateString();

                    $currentDayLogs = $logs->filter(fn($log) => Carbon::parse($log->created_at)->toDateString() === $currentDay);
                    $nextDayLogs = $logs->filter(fn($log) => Carbon::parse($log->created_at)->toDateString() === $nextDay);

                    $schedInWindowStart = Carbon::parse($currentDay . ' ' . $rawSched->time_in)->subHours(6);
                    $schedInWindowEnd = Carbon::parse($currentDay . ' 23:59:59');
                    $firstIn = $currentDayLogs
                        ->where('status', 'IN')
                        ->pluck('created_at')
                        ->filter(function ($createdAt) use ($schedInWindowStart, $schedInWindowEnd) {
                            $logTime = Carbon::parse($createdAt);
                            return $logTime->gte($schedInWindowStart) && $logTime->lte($schedInWindowEnd);
                        })
                        ->min();

                    if ($firstIn) {
                        $firstInTime = Carbon::parse($firstIn)->startOfMinute();
                        $schedOutWindowStart = $firstInTime->copy();
                        $schedOutWindowEnd = Carbon::parse($nextDay . ' ' . $rawSched->time_out)->addHours(8);

                        $lastOut = $nextDayLogs
                            ->where('status', 'OUT')
                            ->pluck('created_at')
                            ->filter(function ($createdAt) use ($schedOutWindowStart, $schedOutWindowEnd) {
                                $logTime = Carbon::parse($createdAt);
                                return $logTime->gte($schedOutWindowStart) && $logTime->lte($schedOutWindowEnd);
                            })
                            ->max();
                    }
                } else {
                    $calcSchedIn = Carbon::parse($currentDay . ' ' . $rawSched->time_in);
                    $calcSchedOut = Carbon::parse($currentDay . ' ' . $rawSched->time_out);
                    if ($calcSchedOut->lte($calcSchedIn)) {
                        $calcSchedOut->addDay();
                    }

                    $windowStart = $calcSchedIn->copy()->subHours(6);
                    $windowEnd = $calcSchedOut->copy()->addHours(8);

                    $scheduleMatchedLogs = $logs->filter(function ($log) use ($windowStart, $windowEnd) {
                        $logTime = Carbon::parse($log->created_at);
                        return $logTime->gte($windowStart) && $logTime->lte($windowEnd);
                    });

                    $firstIn = $scheduleMatchedLogs->where('status', 'IN')->min('created_at');
                    if ($firstIn) {
                        $firstInTime = Carbon::parse($firstIn)->startOfMinute();
                        $lastOut = $scheduleMatchedLogs
                            ->where('status', 'OUT')
                            ->pluck('created_at')
                            ->filter(fn($out) => Carbon::parse($out)->startOfMinute()->gte($firstInTime))
                            ->max();
                    }
                }
            }

            $hasTodayIn = ($hasAmIn || $hasPmIn);
            $hasTodayOut = ($hasAmOut || $hasPmOut);
            $isOvernightSpilloverOnly =
                !$hasTodayIn &&
                $hasTodayOut &&
                $hasAmOut &&
                !$hasPmOut &&
                $previousDayIsOvernightSchedule;

            $calcSched = null;
            if ($isOvernightSpilloverOnly) {
                $calcSched = null;
            } elseif ($rawSched) {
                $calcSched = $rawSched;
            }

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

                    $absentUndertimeMinutes = $renderedMinutes > 0
                        ? (int) $renderedMinutes
                        : $absentSchedIn->diffInMinutes($absentSchedOut);
                }

                if ($absentUndertimeMinutes > 0) {
                    $undertimeMinutes += $absentUndertimeMinutes;
                }
            }

            $hasAmAttendance = ($hasAmIn || $hasAmOut);
            $hasPmAttendance = ($hasPmIn || $hasPmOut);
            $isFullDaySchedule = $rawSched
                && !$isOffSchedule
                && !$isOvernightSchedule
                && $renderedMinutes > 240;
            $hasBreakConfigured = $calcSched
                && !empty($calcSched->break_out)
                && !empty($calcSched->break_in);
            $isHalfDayMissingHalf = $isFullDaySchedule
                && $hasBreakConfigured
                && !$isOvernightSpilloverOnly
                && $firstIn
                && $lastOut
                && ($hasAmAttendance xor $hasPmAttendance);

            if (!$skipLateUndertimeComputation && $calcSched && !$hasApprovedPassSlip && $firstIn) {
                $lateMinutes = 0;
                $undertimeMinutes = 0;

                if ($firstIn) {
                    $firstInTime = Carbon::parse($firstIn)->startOfMinute();
                    if ($isHalfDayMissingHalf && !$hasAmAttendance) {
                        $schedIn = Carbon::parse($currentDay . ' ' . $calcSched->break_in);
                        $schedInLatest = $schedIn->copy();
                    } else {
                        $schedIn = Carbon::parse($currentDay . ' ' . $calcSched->time_in);
                        $schedInLatest = Carbon::parse($currentDay . ' ' . $calcSched->time_in_latest);
                    }
                    if ($schedInLatest->lt($schedIn)) {
                        $schedInLatest->addDay();
                    }

                    if ($firstInTime->gt($schedInLatest)) {
                        $lateDiffMinutes = $schedInLatest->diffInMinutes($firstInTime);

                        if (!empty($calcSched->break_out) && !empty($calcSched->break_in)) {
                            $breakOutTime = Carbon::parse($currentDay . ' ' . $calcSched->break_out);
                            $breakInTime  = Carbon::parse($currentDay . ' ' . $calcSched->break_in);
                            if ($breakInTime->lte($breakOutTime)) {
                                $breakInTime->addDay();
                            }

                            $overlapStart = $schedInLatest->copy()->max($breakOutTime);
                            $overlapEnd   = $firstInTime->copy()->min($breakInTime);

                            if ($overlapEnd->gt($overlapStart)) {
                                $lateDiffMinutes = max(0, $lateDiffMinutes - $overlapStart->diffInMinutes($overlapEnd));
                            }
                        }

                        $lateMinutes += $lateDiffMinutes;
                    }
                }

                if ($lastOut) {
                    $lastOutTime = Carbon::parse($lastOut)->startOfMinute();

                    $schedIn = Carbon::parse($currentDay . ' ' . $calcSched->time_in);
                    $schedOut = Carbon::parse($currentDay . ' ' . $calcSched->time_out);
                    if ($schedOut->lte($schedIn)) {
                        $schedOut->addDay();
                    }
                    $isGliding = isset($calcSched->type) && trim(strtoupper($calcSched->type)) === 'GLIDING';

                    if ($isHalfDayMissingHalf && !$hasPmAttendance) {
                        $requiredOut = Carbon::parse($currentDay . ' ' . $calcSched->break_out);
                    } elseif ($isGliding && $firstIn && !($isHalfDayMissingHalf && !$hasAmAttendance)) {
                        $effectiveIn = Carbon::parse($firstIn)->startOfMinute();
                        $schedInLatest = Carbon::parse($currentDay . ' ' . $calcSched->time_in_latest);
                        $schedOutLatest = Carbon::parse($currentDay . ' ' . $calcSched->time_out_latest);
                        if ($schedInLatest->lt($schedIn)) {
                            $schedInLatest->addDay();
                        }
                        if ($schedOutLatest->lt($schedOut)) {
                            $schedOutLatest->addDay();
                        }

                        if ($effectiveIn->lte($schedIn)) {
                            $requiredOut = $schedOut->copy();
                        } elseif ($effectiveIn->lte($schedInLatest)) {
                            $offsetMinutes = $schedIn->diffInMinutes($effectiveIn);
                            $requiredOut = $schedOut->copy()->addMinutes($offsetMinutes);
                            if ($requiredOut->gt($schedOutLatest)) {
                                $requiredOut = $schedOutLatest->copy();
                            }
                        } else {
                            $requiredOut = $schedOutLatest->copy();
                        }
                    } else {
                        $requiredOut = $schedOut;
                    }

                    if ($lastOutTime->lt($requiredOut)) {
                        $undertimeMinutes = $lastOutTime->diffInMinutes($requiredOut);

                        if (!empty($calcSched->break_out) && !empty($calcSched->break_in)) {
                            $breakOutTime = Carbon::parse($currentDay . ' ' . $calcSched->break_out);
                            $breakInTime  = Carbon::parse($currentDay . ' ' . $calcSched->break_in);
                            if ($breakInTime->lte($breakOutTime)) {
                                $breakInTime->addDay();
                            }

                            $overlapStart = $lastOutTime->copy()->max($breakOutTime);
                            $overlapEnd   = $requiredOut->copy()->min($breakInTime);

                            if ($overlapEnd->gt($overlapStart)) {
                                $undertimeMinutes = max(0, $undertimeMinutes - $overlapStart->diffInMinutes($overlapEnd));
                            }
                        }
                    }
                }

                if ($isHalfDayMissingHalf) {
                    $halfDayMinutes = (int) round($renderedMinutes / 2);
                    if ($halfDayMinutes > 0) {
                        if (!$hasAmAttendance) {
                            $lateMinutes += $halfDayMinutes;
                        } else {
                            $undertimeMinutes += $halfDayMinutes;
                        }
                    }
                }
            }
        }

        // Absence mirrors buildPresentAbsentByRange()'s existing semantics
        // exactly: an expected work date is "absent" unless it's covered by
        // $isAbsenceExempt (leave/hospital-order/overtime/official-pass-slip
        // - a *different*, wider set than $hasComputationExemption above) or
        // has both a first-in and last-out resolved for the day.
        if ($isExpectedWorkDate && !$isAbsenceExempt) {
            $isAbsent = !($firstIn && $lastOut);
        }

        return [
            'late_minutes' => $lateMinutes,
            'undertime_minutes' => $undertimeMinutes,
            'is_absent' => $isAbsent,
            'is_holiday' => $isHoliday,
            'exemption_reason' => $exemptionReason,
        ];
    }
}
```

- [ ] **Step 2: Verify the extraction against the source**

Diff the late/undertime-affecting logic in `computeDay()` above against `DTRController.php` lines 886-1336 (the pre-extraction closure) side by side. Confirm every conditional branch and every `Carbon` calculation matches — the only changes should be: reading `$schedules`/`$logs` parameters instead of closure-captured variables, using local variables (`$renderedMinutes`, `$hasAmIn` etc.) instead of `$data[...]` array keys for values that were never presentational, and the deletions/additions called out in this task's substitution table (day_marker, planned_schedule, off_label, `$overtimeRequests` all gone; `is_absent`/`is_holiday`/`exemption_reason` added).

- [ ] **Step 3: Syntax check**

Run: `php -l app/Services/DtrDayComputationService.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add app/Services/DtrDayComputationService.php
git commit -m "feat: extract shared DTR day computation engine from printDTR"
```

---

### Task 4: Direct unit tests for `DtrDayComputationService`

**Files:**
- Create: `tests/Unit/DtrDayComputationServiceTest.php`

These test the new service directly (no HTTP, no PDF), using the same scenario values as Task 1/2, proving the extraction preserved the numbers independent of the controller.

- [ ] **Step 1: Write the test**

```php
<?php

namespace Tests\Unit;

use App\Services\DtrDayComputationService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class DtrDayComputationServiceTest extends TestCase
{
    private DtrDayComputationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DtrDayComputationService();
    }

    private function schedule(string $timeIn, string $timeOut, ?string $breakOut = null, ?string $breakIn = null, string $type = 'FIXED', ?string $timeInLatest = null, ?string $timeOutLatest = null): object
    {
        return (object) [
            'time_in' => $timeIn,
            'time_in_latest' => $timeInLatest ?? $timeIn,
            'time_out' => $timeOut,
            'time_out_latest' => $timeOutLatest ?? $timeOut,
            'break_out' => $breakOut,
            'break_in' => $breakIn,
            'type' => $type,
            'code' => 'TESTSCHED',
        ];
    }

    private function log(string $dateTime, string $status): object
    {
        return (object) ['created_at' => $dateTime, 'status' => $status];
    }

    /** @test */
    public function break_time_is_subtracted_from_late_minutes(): void
    {
        $schedules = collect(['2027-04-05' => $this->schedule('08:00:00', '17:00:00', '12:00:00', '13:00:00')]);
        $logs = collect([
            $this->log('2027-04-05 13:07:00', 'IN'),
            $this->log('2027-04-05 16:30:00', 'OUT'),
        ]);

        $result = $this->service->computeRange(9501, '2027-04-05', '2027-04-05', $schedules, $logs, []);

        $this->assertEquals(247, $result['2027-04-05']['late_minutes']);
        $this->assertEquals(30, $result['2027-04-05']['undertime_minutes']);
    }

    /** @test */
    public function holiday_with_logs_still_computes_penalty(): void
    {
        $schedules = collect(['2027-04-09' => $this->schedule('08:00:00', '17:00:00', '12:00:00', '13:00:00')]);
        $logs = collect([
            $this->log('2027-04-09 08:30:00', 'IN'),
            $this->log('2027-04-09 17:00:00', 'OUT'),
        ]);
        $exemptions = ['holidays' => ['2027-04-09' => ['type' => 'Regular', 'description' => 'Test Holiday']]];

        $result = $this->service->computeRange(9501, '2027-04-09', '2027-04-09', $schedules, $logs, $exemptions);

        $this->assertEquals(30, $result['2027-04-09']['late_minutes']);
        $this->assertTrue($result['2027-04-09']['is_holiday']);
    }

    /** @test */
    public function leave_exemption_waives_computation_entirely(): void
    {
        $schedules = collect(['2027-04-19' => $this->schedule('08:00:00', '17:00:00', '12:00:00', '13:00:00')]);
        $logs = collect([
            $this->log('2027-04-19 11:00:00', 'IN'),
            $this->log('2027-04-19 17:00:00', 'OUT'),
        ]);
        $exemptions = ['leaves' => ['2027-04-19' => true]];

        $result = $this->service->computeRange(9501, '2027-04-19', '2027-04-19', $schedules, $logs, $exemptions);

        $this->assertEquals(0, $result['2027-04-19']['late_minutes']);
        $this->assertEquals('leave', $result['2027-04-19']['exemption_reason']);
    }
}
```

- [ ] **Step 2: Run and verify all pass**

Run: `php artisan test --filter=DtrDayComputationServiceTest`
Expected: `3 passed`. If a number doesn't match, the extraction in Task 3 has a transcription error — compare against the corresponding characterization test's expected value, not against the source closure by re-reading it (that's how transcription errors get missed twice).

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/DtrDayComputationServiceTest.php
git commit -m "test: add direct unit tests for DtrDayComputationService"
```

---

### Task 5: Wire `printDTR` to the shared engine

**Files:**
- Modify: `app/Http/Controllers/DTRController.php:801-1397`

- [ ] **Step 1: Replace the closure's computation with a call to the new service, keep all display logic**

Inside the `days = collect(range(1, $daysInMonth))->map(...)` closure, after building `$data` with the presentational fields (`day`, `day_name`, `isInsideCutoff`, `off_label`, `planned_schedule`, `rendered_minutes` — lines 852-929 stay untouched) and after the `isEmpty`/log-bucketing section (lines 931-983 stay untouched), replace lines 985-1336 (the entire late/undertime computation block, from `$skipLateUndertimeComputation = ...` through the end of the half-day additive charge) with:

```php
                        $dayResult = app(\App\Services\DtrDayComputationService::class)->computeRange(
                            $employee->id,
                            $currentDay,
                            $currentDay,
                            $schedules,
                            $logs,
                            [
                                'holidays' => $holidays,
                                'leaves' => $leaves,
                                'hospitalOrders' => $hospitalOrders,
                                'passSlips' => $approvedPassSlips,
                            ],
                            $skipAllAutomation
                        )[$currentDay];

                        $lateMinutes = $dayResult['late_minutes'];
                        $undertimeMinutes = $dayResult['undertime_minutes'];

                        if ($lateMinutes > 0) $lateCount++;
                        if ($undertimeMinutes > 0) $undertimeCount++;
                        $lateTotalMinutes += $lateMinutes;
                        $undertimeTotalMinutes += $undertimeMinutes;
                        $data['late_minutes'] = $lateMinutes;

                        $totalMinutes = $lateMinutes + $undertimeMinutes;
                        if ($totalMinutes > 0) {
                            $data['undertime_hours'] = floor($totalMinutes / 60) ?: '';
                            $data['undertime_minutes'] = ($totalMinutes % 60) ?: '';
                        }
```

Lines 1338-1394 (the day_marker / LEAVE-HOLIDAY-HO-OB-OT marker section, which reads `$data['late_minutes']` and other already-existing fields) stay exactly as they are — they don't need `$hasExcusedRecord`, `$isOffSchedule`, `$isHoliday`, etc. to change, since those are still computed earlier in the untouched portion of the closure (lines 854-863, 887-894).

Note: calling `computeRange()` once per day (rather than once for the whole month) is intentionally simple and mirrors the per-day closure structure exactly, to keep this step reviewable as "swap the computation, don't restructure the loop." If profiling later shows this is slow for bulk printing, batching into one `computeRange()` call per employee per month is a follow-up, not part of this change.

- [ ] **Step 2: Run the characterization tests from Task 1 and 2 — must still pass unchanged**

Run: `php artisan test --filter=DtrPrintCharacterizationTest`
Expected: `6 passed`. This is the proof that wiring `printDTR` to the new engine changed nothing about its output.

- [ ] **Step 3: Run the full existing DTR-related test suite for regressions**

Run: `php artisan test --filter=DTR`
Expected: all pass, same count as before this task.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/DTRController.php
git commit -m "refactor: wire printDTR to DtrDayComputationService, zero output change"
```

---

### Task 6: Rewrite `DTRServices` to use the shared engine

**Files:**
- Modify: `app/Services/DTRServices.php`

- [ ] **Step 1: Replace `getTotalLateAbsent()` and delete now-dead private methods**

Replace the entire `getTotalLateAbsent()` method (current lines 29-108) with:

```php
    public function getTotalLateAbsent($userid, $start_date, $end_date)
    {
        $holidays = $this->getHolidayDatesByRangeWithDetails($start_date, $end_date);
        $leaveDates = $this->getApprovedLeaveDatesByRange($userid, $start_date, $end_date);
        $hospitalOrders = array_fill_keys($this->getApprovedSpecialDutyDatesByRange($userid, $start_date, $end_date), true);
        $passSlips = array_fill_keys(
            $this->getApprovedPassSlipsByRange($userid, $start_date, $end_date)->pluck('slip_date')->unique()->all(),
            true
        );

        // absenceExemptDates mirrors buildPresentAbsentByRange()'s own
        // exemption set exactly (leave + special-duty[HO+overtime] +
        // official-only pass slips) - a different, wider set than the
        // late/undertime exemption above (which excludes overtime and
        // includes personal pass slips too). Reuses the same private
        // range-lookup helpers buildPresentAbsentByRange() already calls.
        $specialDutyDates = $this->getApprovedSpecialDutyDatesByRange($userid, $start_date, $end_date);
        $officialPassSlipDates = $this->getApprovedPassSlipDatesByRange($userid, $start_date, $end_date);
        $absenceExemptDates = array_fill_keys(
            array_unique(array_merge($leaveDates, $specialDutyDates, $officialPassSlipDates)),
            true
        );

        $schedules = $this->mapSchedulesWithBreaks($this->getEmployeePlottedSchedules(
            $userid,
            Carbon::parse($start_date)->copy()->subDay()->toDateString(),
            Carbon::parse($end_date)->copy()->addDay()->toDateString()
        ));
        $logs = $this->getLogsByRange(
            $userid,
            Carbon::parse($start_date)->copy()->subDay()->toDateString(),
            Carbon::parse($end_date)->copy()->addDay()->toDateString()
        );

        $results = app(\App\Services\DtrDayComputationService::class)->computeRange(
            $userid,
            $start_date,
            $end_date,
            $schedules,
            $logs,
            [
                'holidays' => $holidays,
                'leaves' => array_fill_keys($leaveDates, true),
                'hospitalOrders' => $hospitalOrders,
                'passSlips' => $passSlips,
                'absenceExemptDates' => $absenceExemptDates,
            ]
        );

        return [
            'late_minutes' => $results->sum('late_minutes'),
            'undertime_minutes' => $results->sum('undertime_minutes'),
            'absences' => $results->where('is_absent', true)->count(),
        ];
    }
```

Delete these now-unused private methods entirely: `processLogs()`, `mapSchedules()` (replaced by `mapSchedulesWithBreaks()` below), `resolveAttendanceLogs()`, `resolveDailyLogs()`, `resolveScheduledLogs()`, `getFirstInLog()`, `getLastOutLog()`, `getNoScheduleDays()`, `getWorkDays()`, `getAllDays()`. Keep `buildPresentAbsentByRange()` — it's still used by the separate public `getTotalPresentByRange()` method, which this task doesn't touch.

Add `mapSchedulesWithBreaks()` (the old `mapSchedules()` didn't carry `break_out`/`break_in` at all — that omission is exactly what the new engine needs fixed, since it now does its own break-time subtraction):

```php
    private function mapSchedulesWithBreaks($plottedSchedules)
    {
        $scheduleMap = [];
        foreach ($plottedSchedules as $schedule) {
            $scheduleMap[$schedule->schedule_date] = (object) [
                'type' => $schedule->type ?? 'FIXED',
                'time_in' => $schedule->time_in,
                'time_out' => $schedule->time_out,
                'time_in_latest' => $schedule->time_in_latest,
                'time_out_latest' => $schedule->time_out_latest,
                'break_out' => $schedule->break_out ?? null,
                'break_in' => $schedule->break_in ?? null,
            ];
        }
        return collect($scheduleMap);
    }
```

`getEmployeePlottedSchedules()` already selects `s.*`/`sml.*` via its join — verify it already selects `sml.break_out`/`sml.break_in` (it does an unqualified `->get()` with no `->select()`, so both are already included; no change needed there).

Add `getHolidayDatesByRangeWithDetails()` (the existing `getHolidayDatesByRange()` only returns date strings, but the new engine needs `type`/`description` per date, matching what `printDTR` already fetches):

```php
    private function getHolidayDatesByRangeWithDetails($startDate, $endDate): array
    {
        $rows = DB::table('hris.calendar_holidays')
            ->whereBetween('date', [$startDate, $endDate])
            ->get(['date', 'type', 'description']);

        $result = [];
        foreach ($rows as $row) {
            $result[Carbon::parse($row->date)->toDateString()] = [
                'type' => $row->type,
                'description' => $row->description,
            ];
        }
        return $result;
    }
```

`getHolidayDatesByRange()` itself stays (still used by `buildPresentAbsentByRange()`).

- [ ] **Step 2: Syntax check**

Run: `php -l app/Services/DTRServices.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Run existing DTR/Payroll tests**

Run: `php artisan test --filter=DTR`
Run: `php artisan test --filter=Payroll`
Expected: all pass. `PayrollCutoffTest::full_payroll_lifecycle_moves_through_approval_chain_to_released` exercises `getCutoffBreakdown()` (which calls `getTotalLateAbsent()`) end-to-end — this is the most important regression check for this task.

- [ ] **Step 4: Commit**

```bash
git add app/Services/DTRServices.php
git commit -m "refactor: DTRServices uses DtrDayComputationService, fixing missing break-time deduction"
```

---

### Task 7: Prove `DTRServices` now matches `printDTR`

**Files:**
- Create: `tests/Unit/DtrServicesUnifiedComputationTest.php`

This is the test that would have failed against the old `processLogs()` — proving the original bug is fixed.

- [ ] **Step 1: Write the test**

```php
<?php

namespace Tests\Unit;

use App\Services\DTRServices;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DtrServicesUnifiedComputationTest extends TestCase
{
    use DatabaseTransactions;

    private int $userId = 9502;

    protected function tearDown(): void
    {
        DB::table('hris.dtr_logs')->where('userid', $this->userId)->delete();
        DB::table('hris.schedules')->where('employee_id', $this->userId)->delete();
        DB::table('hris.schedule_master_lists')->where('code', 'UNIFIED_TEST_SCHED')->delete();
        parent::tearDown();
    }

    private function seedSchedule(string $date, string $timeIn, string $timeOut, ?string $breakOut, ?string $breakIn): void
    {
        DB::table('hris.schedule_master_lists')->where('code', 'UNIFIED_TEST_SCHED')->delete();
        $id = DB::table('hris.schedule_master_lists')->insertGetId([
            'code' => 'UNIFIED_TEST_SCHED',
            'label' => 'Unified Computation Test',
            'type' => 'FIXED',
            'time_in' => $timeIn,
            'time_in_latest' => $timeIn,
            'time_out' => $timeOut,
            'time_out_latest' => $timeOut,
            'break_out' => $breakOut,
            'break_in' => $breakIn,
        ]);
        DB::table('hris.schedules')->updateOrInsert(
            ['employee_id' => $this->userId, 'schedule_date' => $date],
            ['schedule_id' => $id]
        );
    }

    private function seedLog(string $dateTime, string $status): void
    {
        DB::table('hris.dtr_logs')->insert([
            'userid' => $this->userId,
            'status' => $status,
            'type' => 2,
            'created_at' => $dateTime,
            'updated_at' => $dateTime,
        ]);
    }

    /** @test */
    public function cutoff_generation_now_subtracts_break_time_matching_printdtr(): void
    {
        // Same fixture as DtrPrintCharacterizationTest::plain_late_arrival_and_early_departure...
        // 08:00-17:00, 12:00-13:00 break, arrives 13:07 (spans the break), leaves 16:30.
        $this->seedSchedule('2027-05-05', '08:00:00', '17:00:00', '12:00:00', '13:00:00');
        $this->seedLog('2027-05-05 13:07:00', 'IN');
        $this->seedLog('2027-05-05 16:30:00', 'OUT');

        $result = app(DTRServices::class)->getTotalLateAbsent($this->userId, '2027-05-05', '2027-05-05');

        // Before this project: the old processLogs() had no break deduction
        // at all and would have returned late_minutes=307 (13:07-08:00, no
        // subtraction). Now it must match printDTR's 247.
        $this->assertEquals(247, $result['late_minutes']);
        $this->assertEquals(30, $result['undertime_minutes']);
    }

    /** @test */
    public function half_day_missing_am_matches_printdtr_instead_of_the_old_blanket_rule(): void
    {
        // Same fixture as DtrPrintCharacterizationTest::half_day_missing_am...
        $this->seedSchedule('2027-05-12', '08:00:00', '17:00:00', '12:00:00', '13:00:00');
        $this->seedLog('2027-05-12 13:10:00', 'IN');
        $this->seedLog('2027-05-12 17:00:00', 'OUT');

        $result = app(DTRServices::class)->getTotalLateAbsent($this->userId, '2027-05-12', '2027-05-12');

        // Before this project: the old blanket "checkout before 1PM" rule
        // didn't apply here (checkout is 17:00, after 1PM) - undertime alone
        // would have been 0 late + 0 undertime (no rule caught this case at
        // all under the old algorithm, since it never modeled half-day-
        // missing-AM). Now it must match printDTR's 250.
        $this->assertEquals(250, $result['late_minutes']);
    }
}
```

- [ ] **Step 2: Run and verify both pass**

Run: `php artisan test --filter=DtrServicesUnifiedComputationTest`
Expected: `2 passed`

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/DtrServicesUnifiedComputationTest.php
git commit -m "test: prove DTRServices late/undertime now matches printDTR"
```

---

### Task 8: `cutoff_summary_days` migration

**Files:**
- Create: `database/migrations/2026_07_08_140000_create_cutoff_summary_days_table.php`

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cutoff_summary_days', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cutoff_id');
            $table->integer('employee_id');
            $table->date('date');
            $table->integer('late_minutes')->default(0);
            $table->integer('undertime_minutes')->default(0);
            $table->boolean('is_absent')->default(false);
            $table->boolean('is_holiday')->default(false);
            $table->string('exemption_reason', 20)->nullable();
            $table->timestamps();

            $table->unique(['cutoff_id', 'employee_id', 'date'], 'cutoff_summary_days_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cutoff_summary_days');
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `php artisan migrate --force`
Expected: `Migrated: 2026_07_08_140000_create_cutoff_summary_days_table`

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_07_08_140000_create_cutoff_summary_days_table.php
git commit -m "feat: add cutoff_summary_days table for per-day payroll audit trail"
```

---

### Task 9: Persist per-day snapshots from `PayrollServices`

**Files:**
- Modify: `app/Services/PayrollServices.php`

- [ ] **Step 1: Add a private helper that writes the snapshot rows, and call it from both `getOrCreateCutoffSummary()` and `generateCutoffSummary()`**

Both methods already call `$dtrService->getCutoffBreakdown($employeeId, $startDate, $endDate)`. Change `DTRServices::getCutoffBreakdown()` first (in the same file as Task 6, or as part of this task if not already done) to also return the raw per-day `Collection` from `computeRange()` under a new key `'daily'`, so callers don't need a second query:

In `DTRServices::getCutoffBreakdown()`, capture the result of `getTotalLateAbsent()`'s internal `computeRange()` call. Since `getTotalLateAbsent()` currently only returns the summed totals, add a second, explicit method instead of changing its return shape (avoids touching every existing caller of `getTotalLateAbsent()`):

```php
    public function getDailyBreakdown($userid, $start_date, $end_date): \Illuminate\Support\Collection
    {
        $holidays = $this->getHolidayDatesByRangeWithDetails($start_date, $end_date);
        $leaveDates = $this->getApprovedLeaveDatesByRange($userid, $start_date, $end_date);
        $hospitalOrders = array_fill_keys($this->getApprovedSpecialDutyDatesByRange($userid, $start_date, $end_date), true);
        $passSlips = array_fill_keys(
            $this->getApprovedPassSlipsByRange($userid, $start_date, $end_date)->pluck('slip_date')->unique()->all(),
            true
        );
        // Same absenceExemptDates construction as getTotalLateAbsent() above -
        // see that method's comment for why this differs from the late/
        // undertime exemption set.
        $specialDutyDates = $this->getApprovedSpecialDutyDatesByRange($userid, $start_date, $end_date);
        $officialPassSlipDates = $this->getApprovedPassSlipDatesByRange($userid, $start_date, $end_date);
        $absenceExemptDates = array_fill_keys(
            array_unique(array_merge($leaveDates, $specialDutyDates, $officialPassSlipDates)),
            true
        );
        $schedules = $this->mapSchedulesWithBreaks($this->getEmployeePlottedSchedules(
            $userid,
            Carbon::parse($start_date)->copy()->subDay()->toDateString(),
            Carbon::parse($end_date)->copy()->addDay()->toDateString()
        ));
        $logs = $this->getLogsByRange(
            $userid,
            Carbon::parse($start_date)->copy()->subDay()->toDateString(),
            Carbon::parse($end_date)->copy()->addDay()->toDateString()
        );

        return app(\App\Services\DtrDayComputationService::class)->computeRange(
            $userid,
            $start_date,
            $end_date,
            $schedules,
            $logs,
            [
                'holidays' => $holidays,
                'leaves' => array_fill_keys($leaveDates, true),
                'hospitalOrders' => $hospitalOrders,
                'passSlips' => $passSlips,
                'absenceExemptDates' => $absenceExemptDates,
            ]
        );
    }
```

Add to `PayrollServices`, a private helper called from both `getOrCreateCutoffSummary()` and `generateCutoffSummary()` right after the `DB::table($table)->insert(...)` / `->updateOrInsert(...)` call that writes the aggregate row:

```php
    private function persistCutoffSummaryDays($cutoffId, $employeeId, $startDate, $endDate): void
    {
        $daily = app(DTRServices::class)->getDailyBreakdown($employeeId, $startDate, $endDate);

        DB::table('hris.cutoff_summary_days')
            ->where('cutoff_id', $cutoffId)
            ->where('employee_id', $employeeId)
            ->delete();

        $rows = $daily->map(function ($day, $date) use ($cutoffId, $employeeId) {
            return [
                'cutoff_id' => $cutoffId,
                'employee_id' => $employeeId,
                'date' => $date,
                'late_minutes' => $day['late_minutes'],
                'undertime_minutes' => $day['undertime_minutes'],
                'is_absent' => $day['is_absent'] ? 1 : 0,
                'is_holiday' => $day['is_holiday'] ? 1 : 0,
                'exemption_reason' => $day['exemption_reason'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->values()->all();

        if (!empty($rows)) {
            DB::table('hris.cutoff_summary_days')->insert($rows);
        }
    }
```

In `getOrCreateCutoffSummary()`, immediately after the existing `DB::table($table)->insert([...]);` block that writes the new `cutoff_summaries` row, add:

```php
        $this->persistCutoffSummaryDays($master->id, $employeeId, $startDate, $endDate);
```

In `generateCutoffSummary()`, immediately after its `DB::table($table)->updateOrInsert(...)` call, add the same line.

- [ ] **Step 2: Syntax check both files**

Run: `php -l app/Services/PayrollServices.php && php -l app/Services/DTRServices.php`
Expected: `No syntax errors detected` for both

- [ ] **Step 3: Commit**

```bash
git add app/Services/PayrollServices.php app/Services/DTRServices.php
git commit -m "feat: persist per-day cutoff_summary_days snapshot on cutoff generation"
```

---

### Task 10: End-to-end test + full suite verification

**Files:**
- Create: `tests/Feature/CutoffSummaryDaysTest.php`

- [ ] **Step 1: Write a test proving cutoff generation persists the expected per-day rows**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CutoffSummaryDaysTest extends TestCase
{
    use DatabaseTransactions;

    private int $userId = 9503;
    private ?int $masterId = null;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);
    }

    protected function tearDown(): void
    {
        if ($this->masterId) {
            DB::table('hris.cutoff_summary_days')->where('cutoff_id', $this->masterId)->delete();
            DB::table('hris.cutoff_summaries')->where('cutoff_id', $this->masterId)->delete();
            DB::table('hris.cutoff_summaries_master')->where('id', $this->masterId)->delete();
        }
        DB::table('hris.dtr_logs')->where('userid', $this->userId)->delete();
        DB::table('hris.schedules')->where('employee_id', $this->userId)->delete();
        DB::table('hris.schedule_master_lists')->where('code', 'CSD_TEST_SCHED')->delete();
        parent::tearDown();
    }

    private function actAsAdmin(): void
    {
        Auth::shouldReceive('id')->andReturn($this->userId);
        Auth::shouldReceive('check')->andReturn(true);
        session(['access_rights' => 'admin']);
    }

    /** @test */
    public function generating_a_cutoff_summary_persists_matching_per_day_rows(): void
    {
        $this->actAsAdmin();

        DB::table('hris.schedule_master_lists')->where('code', 'CSD_TEST_SCHED')->delete();
        $schedId = DB::table('hris.schedule_master_lists')->insertGetId([
            'code' => 'CSD_TEST_SCHED', 'label' => 'CSD Test', 'type' => 'FIXED',
            'time_in' => '08:00:00', 'time_in_latest' => '08:00:00',
            'time_out' => '17:00:00', 'time_out_latest' => '17:00:00',
            'break_out' => '12:00:00', 'break_in' => '13:00:00',
        ]);
        DB::table('hris.schedules')->updateOrInsert(
            ['employee_id' => $this->userId, 'schedule_date' => '2027-06-01'],
            ['schedule_id' => $schedId]
        );
        DB::table('hris.dtr_logs')->insert([
            ['userid' => $this->userId, 'status' => 'IN', 'type' => 2, 'created_at' => '2027-06-01 09:15:00', 'updated_at' => '2027-06-01 09:15:00'],
            ['userid' => $this->userId, 'status' => 'OUT', 'type' => 2, 'created_at' => '2027-06-01 17:00:00', 'updated_at' => '2027-06-01 17:00:00'],
        ]);

        $response = $this->withoutMiddleware()->postJson('/cutoff-summaries', [
            'title' => 'CSD Test Cutoff',
            'start_date' => '2027-06-01',
            'end_date' => '2027-06-01',
            'employees' => [$this->userId],
        ]);
        $response->assertOk();

        $this->masterId = DB::table('hris.cutoff_summaries_master')
            ->where('title', 'CSD Test Cutoff')->value('id');
        $this->assertNotNull($this->masterId);

        $dayRow = DB::table('hris.cutoff_summary_days')
            ->where('cutoff_id', $this->masterId)
            ->where('employee_id', $this->userId)
            ->where('date', '2027-06-01')
            ->first();

        $this->assertNotNull($dayRow, 'Expected a persisted cutoff_summary_days row.');
        $this->assertEquals(75, $dayRow->late_minutes); // 09:15 - 08:00
        $this->assertEquals(0, $dayRow->undertime_minutes);
        $this->assertEquals(0, (int) $dayRow->is_absent);
    }
}
```

- [ ] **Step 2: Run this test**

Run: `php artisan test --filter=CutoffSummaryDaysTest`
Expected: `1 passed`

- [ ] **Step 3: Run the complete test suite**

Run: `php artisan test`
Expected: all passing, no regressions versus the count before this plan started (81 passing baseline from the previous session's test-suite fixes, plus this plan's new tests).

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/CutoffSummaryDaysTest.php
git commit -m "test: verify cutoff generation persists cutoff_summary_days end to end"
```
