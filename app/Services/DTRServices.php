<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DTRServices
{
    /** True while absences are looked up for LWOP cases (rest days are skipped then). */
    private bool $resolvingLwopCases = false;

    public function getTotalPresent($userid, $month, $year)
    {
        $startDate = Carbon::createFromDate($year, $month, 1)->toDateString();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();
        return $this->getTotalPresentByRange($userid, $startDate, $endDate);
    }

    public function getTotalLateAbsent($userid, $start_date, $end_date)
    {
        $results = $this->getDailyBreakdown($userid, $start_date, $end_date);

        return [
            'late_minutes' => $results->sum('late_minutes'),
            'undertime_minutes' => $results->sum('undertime_minutes'),
            'absences' => $results->where('is_absent', true)->count(),
            'pass_slip_minutes' => (int) $results->sum('pass_slip_minutes'),
        ];
    }

    public function getDailyBreakdown($userid, $start_date, $end_date): \Illuminate\Support\Collection
    {
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
            $this->buildComputationExemptions($userid, $start_date, $end_date)
        );
    }

    /**
     * absenceExemptDates mirrors buildPresentAbsentByRange()'s own exemption
     * set exactly (leave + special-duty[HO+overtime] + official-only pass
     * slips) - a different, wider set than the late/undertime exemption
     * (which excludes overtime and includes personal pass slips too).
     * Reuses the same private range-lookup helpers buildPresentAbsentByRange()
     * already calls.
     */
    private function buildComputationExemptions($userid, $start_date, $end_date): array
    {
        $holidays = $this->getHolidayDatesByRangeWithDetails($start_date, $end_date);
        $leaveDates = $this->getApprovedLeaveDatesByRange($userid, $start_date, $end_date);
        $lwopDays = $this->getLwopDaysByRange($userid, $start_date, $end_date);
        $lwopDates = array_keys($lwopDays);
        $hospitalOrders = array_fill_keys($this->getApprovedSpecialDutyDatesByRange($userid, $start_date, $end_date), true);
        // Official slips excuse the day; personal slips carry their times so
        // only their in-hours minutes are charged (to earned credits).
        $passSlips = [];
        foreach ($this->getApprovedPassSlipsByRange($userid, $start_date, $end_date) as $slip) {
            $date = Carbon::parse($slip->slip_date)->toDateString();
            $passSlips[$date] = $passSlips[$date] ?? ['official' => false, 'personal' => []];
            if (strtolower((string) ($slip->type ?? '')) === 'personal') {
                $passSlips[$date]['personal'][] = ['time_out' => $slip->time_out, 'time_in' => $slip->time_in];
            } else {
                $passSlips[$date]['official'] = true;
            }
        }

        $specialDutyDates = $this->getApprovedSpecialDutyDatesByRange($userid, $start_date, $end_date);
        $officialPassSlipDates = $this->getApprovedPassSlipDatesByRange($userid, $start_date, $end_date);
        $absenceExemptDates = array_fill_keys(
            array_unique(array_merge($leaveDates, $lwopDates, $specialDutyDates, $officialPassSlipDates)),
            true
        );

        // Paid leave carries its filed hours so a partial-day leave only covers
        // part of the schedule. Whole-day LWOP (incl. rest days/holidays absorbed
        // under CSC Sec. 33) is a full-day exemption; partial-day LWOP covers
        // only its filed hours, like partial paid leave.
        $leaves = [];
        foreach ($this->getApprovedLeaveEntriesByRange($userid, $start_date, $end_date) as $date => $entry) {
            $leaves[$date] = $entry;
        }
        foreach ($lwopDays as $date => $lwop) {
            $leaves[$date] = $lwop['hours'] === null ? true : ['hours' => $lwop['hours']];
        }

        return [
            'holidays' => $holidays,
            'leaves' => $leaves,
            'hospitalOrders' => $hospitalOrders,
            'passSlips' => $passSlips,
            'absenceExemptDates' => $absenceExemptDates,
        ];
    }

    public function getTotalPresentByRange($userid, $start_date, $end_date)
    {
        [$presentDays] = $this->buildPresentAbsentByRange($userid, $start_date, $end_date);

        return count($presentDays);
    }

    /**
     * The employee's own work days in the range: plotted, non-OFF, non-holiday
     * schedule dates (e.g. 18 for a Mon-Thu CWW month instead of 22 weekdays).
     * Falls back to non-holiday weekdays when nothing is plotted. This is the
     * same date set days present is counted against, so perfect attendance
     * always gives present == scheduled work days.
     */
    public function getScheduledWorkDaysByRange($userid, $start_date, $end_date): int
    {
        $holidays = $this->getHolidayDatesByRange($start_date, $end_date);
        $schedules = $this->mapSchedules($this->getEmployeePlottedSchedules($userid, $start_date, $end_date));

        return count($this->resolveExpectedWorkDates($schedules, $start_date, $end_date, $holidays));
    }

    private function buildPresentAbsentByRange($userid, $start_date, $end_date)
    {
        $holidays = $this->getHolidayDatesByRange($start_date, $end_date);
        $leaveDates = $this->getApprovedLeaveDatesByRange($userid, $start_date, $end_date);
        $specialDutyDates = $this->getApprovedSpecialDutyDatesByRange($userid, $start_date, $end_date);
        $officialPassSlipDates = $this->getApprovedPassSlipDatesByRange($userid, $start_date, $end_date);
        $schedules = $this->mapSchedules($this->getEmployeePlottedSchedules($userid, $start_date, $end_date));
        $expectedDates = $this->resolveExpectedWorkDates($schedules, $start_date, $end_date, $holidays);
        $logs = $this->getLogsByRange(
            $userid,
            Carbon::parse($start_date)->copy()->subDay()->toDateString(),
            Carbon::parse($end_date)->copy()->addDay()->toDateString()
        );

        $presentDays = [];
        $absentDays = [];

        foreach ($expectedDates as $date) {
            if (in_array($date, $leaveDates) || in_array($date, $specialDutyDates) || in_array($date, $officialPassSlipDates)) {
                $presentDays[$date] = true;
                continue;
            }

            [$firstIn, $lastOut] = $this->resolveAttendanceLogs($logs, $schedules, $date);

            if ($firstIn && $lastOut) {
                $presentDays[$date] = true;
            } else {
                $absentDays[$date] = true;
            }
        }

        return [$presentDays, $absentDays, $expectedDates, $holidays, $leaveDates, $specialDutyDates];
    }

    public function getHolidays($month, $year)
    {
        return DB::table('hris.calendar_holidays')
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->pluck('date')
            ->toArray();
    }

    private function getHolidayDatesByRange($startDate, $endDate)
    {
        return DB::table('hris.calendar_holidays')
            ->whereBetween('date', [$startDate, $endDate])
            ->pluck('date')
            ->map(function ($date) {
                return Carbon::parse($date)->toDateString();
            })
            ->toArray();
    }

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

    public function getEmployeePlottedSchedules($userid, $startDate, $endDate)
    {
        return DB::table('hris.schedules as s')
            ->join('hris.schedule_master_lists as sml', 's.schedule_id', '=', 'sml.id')
            ->where('s.employee_id', $userid)
            ->whereBetween('s.schedule_date', [$startDate, $endDate])
            ->get();
    }

    private function mapSchedules($plottedSchedules)
    {
        $scheduleMap = [];
        foreach ($plottedSchedules as $schedule) {
            $scheduleMap[$schedule->schedule_date] = [
                'code' => $schedule->code ?? null,
                'type' => $schedule->type ?? 'FIXED',
                'time_in' => $schedule->time_in,
                'time_out' => $schedule->time_out,
                'time_in_latest' => $schedule->time_in_latest,
                'time_out_latest' => $schedule->time_out_latest
            ];
        }
        return $scheduleMap;
    }

    private function mapSchedulesWithBreaks($plottedSchedules)
    {
        $scheduleMap = [];
        foreach ($plottedSchedules as $schedule) {
            $scheduleMap[$schedule->schedule_date] = (object) [
                'code' => $schedule->code ?? null,
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

    /**
     * Work days in the range, decided date by date (same rule as
     * DtrDayComputationService::resolveExpectedWorkDates, so days present,
     * scheduled work days and absences always agree): a plotted date follows
     * its schedule (OFF = rest day), an unplotted date uses the weekday rule,
     * holidays are never work days.
     */
    private function resolveExpectedWorkDates(array $schedules, $startDate, $endDate, array $holidays): array
    {
        return collect($this->getAllDaysByRange($startDate, $endDate))
            ->filter(function ($date) use ($schedules, $holidays) {
                if (in_array($date, $holidays, true)) {
                    return false;
                }

                if (isset($schedules[$date])) {
                    $code = trim((string) ($schedules[$date]['code'] ?? ''));
                    return !($code !== '' && strtoupper($code) === 'OFF');
                }

                return Carbon::parse($date)->isWeekday();
            })
            ->values()
            ->all();
    }

    private function getAllDaysByRange($startDate, $endDate)
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $days = [];

        while ($start <= $end) {
            $days[] = $start->toDateString();
            $start->addDay();
        }

        return $days;
    }

    private function getDailyLogMap($userid, $startDate, $endDate)
    {
        $logs = DB::table('hris.dtr_logs')
            ->where('userid', $userid)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->orderBy('created_at', 'asc')
            ->get(['created_at', 'status']);

        $map = [];
        foreach ($logs as $log) {
            $date = Carbon::parse($log->created_at)->toDateString();
            if (!isset($map[$date])) {
                $map[$date] = ['in' => null, 'out' => null];
            }
            if ($log->status === 'IN') {
                if ($map[$date]['in'] === null) {
                    $map[$date]['in'] = $log->created_at;
                }
            } elseif ($log->status === 'OUT') {
                $map[$date]['out'] = $log->created_at;
            }
        }

        return $map;
    }

    private function getLogsByRange($userid, $startDate, $endDate)
    {
        return DB::table('hris.dtr_logs')
            ->where('userid', $userid)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->orderBy('created_at', 'asc')
            ->get(['created_at', 'status']);
    }

    private function resolveAttendanceLogs($logs, array $schedules, string $date): array
    {
        $schedule = $schedules[$date] ?? null;
        if (!$schedule) {
            return $this->resolveDailyLogs($logs, $date);
        }

        return $this->resolveScheduledLogs($logs, $date, $schedule);
    }

    private function resolveDailyLogs($logs, string $date): array
    {
        $dayLogs = $logs->filter(function ($log) use ($date) {
            return Carbon::parse($log->created_at)->toDateString() === $date;
        });

        return [
            $dayLogs->where('status', 'IN')->min('created_at'),
            $dayLogs->where('status', 'OUT')->max('created_at'),
        ];
    }

    private function resolveScheduledLogs($logs, string $date, array $shift): array
    {
        $scheduleIn = Carbon::parse("$date {$shift['time_in']}")->startOfMinute();
        $scheduleOut = Carbon::parse("$date {$shift['time_out']}")->startOfMinute();

        if ($scheduleOut->lte($scheduleIn)) {
            $scheduleOut->addDay();
        }

        // Match the controller behavior so overnight next-day OUT logs are
        // tied back to the scheduled shift date without changing tardiness rules.
        $windowStart = $scheduleIn->copy()->subHours(6);
        $windowEnd = $scheduleOut->copy()->addHours(8);

        $relevantLogs = $logs->filter(function ($log) use ($windowStart, $windowEnd) {
            $logTime = Carbon::parse($log->created_at);
            return $logTime->gte($windowStart) && $logTime->lte($windowEnd);
        });

        $firstIn = $relevantLogs->where('status', 'IN')->min('created_at');
        if (!$firstIn) {
            return [null, null];
        }

        $firstInTime = Carbon::parse($firstIn)->startOfMinute();
        $lastOut = $relevantLogs
            ->where('status', 'OUT')
            ->pluck('created_at')
            ->filter(function ($out) use ($firstInTime) {
                return Carbon::parse($out)->startOfMinute()->gte($firstInTime);
            })
            ->max();

        return [$firstIn, $lastOut];
    }

    private function getApprovedLeaveDates($userid, $month, $year)
    {
        return DB::table('hris.leave_applications as la')
            ->join('hris.leave_date_applied as lda', 'la.id', '=', 'lda.leave_application_id')
            ->where('la.user_id', $userid)
            ->whereRaw('LOWER(la.status) = ?', ['approved']) // only approved leaves
            ->whereYear('lda.date_applied', $year)
            ->whereMonth('lda.date_applied', $month)
            ->pluck('lda.date_applied')
            ->toArray();
    }

    private function getApprovedLeaveDatesByRange($userid, $startDate, $endDate)
    {
        return DB::table('hris.leave_applications as la')
            ->join('hris.leave_date_applied as lda', 'la.id', '=', 'lda.leave_application_id')
            ->where('la.user_id', $userid)
            ->where('la.is_lwop', 0)
            ->whereRaw('LOWER(la.status) = ?', ['approved'])
            ->where('lda.status', 'active') // cancelled dates no longer count as leave
            ->whereBetween('lda.date_applied', [$startDate, $endDate])
            ->pluck('lda.date_applied')
            ->map(function ($date) {
                return Carbon::parse($date)->toDateString();
            })
            ->toArray();
    }

    /** [date => total filed hours] for approved, active, non-LWOP leave dates. */
    /**
     * [date => leave entry] for approved, active, non-LWOP leave dates, in the shape
     * DtrDayComputationService expects:
     *  - hour-based types: ['hours' => n] (fewer hours than the schedule = partial-day leave)
     *  - day-based types : true for a whole day (covers the whole schedule, whatever its
     *                      length), ['fraction' => 0.5] for a half day (half the schedule)
     */
    private function getApprovedLeaveEntriesByRange($userid, $startDate, $endDate): array
    {
        $entries = [];
        $rows = DB::table('hris.leave_applications as la')
            ->join('hris.leave_date_applied as lda', 'la.id', '=', 'lda.leave_application_id')
            ->leftJoin('hris.leave_types as lt', 'lt.id', '=', 'lda.leave_type_id')
            ->where('la.user_id', $userid)
            ->where('la.is_lwop', 0)
            ->whereRaw('LOWER(la.status) = ?', ['approved'])
            ->where('lda.status', 'active')
            ->whereBetween('lda.date_applied', [$startDate, $endDate])
            ->get(['lda.date_applied', 'lda.hours', 'lt.deduction_unit']);

        foreach ($rows as $row) {
            $date = Carbon::parse($row->date_applied)->toDateString();
            if (strtolower((string) $row->deduction_unit) === 'days') {
                $entries[$date] = (int) $row->hours >= 8 ? true : ['fraction' => 0.5];
                continue;
            }
            $previous = is_array($entries[$date] ?? null) ? (int) ($entries[$date]['hours'] ?? 0) : 0;
            $entries[$date] = ['hours' => $previous + (int) $row->hours];
        }

        return $entries;
    }

    /** Dates in the range that count as LWOP (see getLwopDaysByRange). */
    private function getApprovedLwopDatesByRange($userid, $startDate, $endDate)
    {
        return array_keys($this->getLwopDaysByRange($userid, $startDate, $endDate));
    }

    /**
     * LWOP per date in the range: [date => ['days' => float, 'hours' => ?int]].
     *
     * LWOP = approved LWOP filings (dates not cancelled, not DH-disapproved;
     * a partial day counts filed hours / scheduled hours, e.g. 4 of 8 = 0.5)
     * plus the Saturdays/Sundays that an LWOP Settings weekend case marks as
     * LWOP when the Friday before or the Monday after is an LWOP day
     * (days 1.0, hours null). Every other weekend and every holiday is present.
     */
    private function getLwopDaysByRange($userid, $startDate, $endDate): array
    {
        $filings = $this->collectLwopFilings($userid, $startDate, $endDate);
        if ($filings === null) {
            return [];
        }

        $lwop = $filings['partial'];
        foreach ($filings['whole'] as $date) {
            $lwop[$date] = ['days' => 1.0, 'hours' => null];
        }
        if (!$this->resolvingLwopCases) {
            foreach ($this->weekendDaysByCases($userid, $startDate, $endDate, $filings, true) as $date) {
                $lwop[$date] = ['days' => 1.0, 'hours' => null];
            }
        }

        $lwop = array_filter($lwop, fn($date) => $date >= $startDate && $date <= $endDate, ARRAY_FILTER_USE_KEY);
        ksort($lwop);

        return $lwop;
    }

    /**
     * Days without pay for earned VL/SL credits in the range: approved LWOP
     * (partial days as fractions), unexcused absences (scheduled work day, no
     * complete logs, no leave/OB/HO) and the weekend days a case marks as LWOP.
     * Credits use 30 minus this number (CSC Table III, 30-day month).
     */
    public function getCreditWithoutPayDaysByRange($userid, $startDate, $endDate): float
    {
        $filings = $this->collectLwopFilings($userid, $startDate, $endDate) ?? $this->emptyLwopFilings($userid, $startDate, $endDate);

        // The Friday before / Monday after the range can decide its weekends; never past
        // yesterday (days not yet worked have no logs and would look like absences).
        $absenceStart = Carbon::parse($startDate)->copy()->subDays(3)->toDateString();
        $absenceEnd = Carbon::parse($endDate)->copy()->addDays(3)->min(Carbon::yesterday())->toDateString();
        $absentDates = $absenceEnd >= $absenceStart ? $this->absentDatesBetween($userid, $absenceStart, $absenceEnd) : [];

        $inRange = fn($date) => $date >= $startDate && $date <= $endDate;
        $counted = [];
        foreach ($filings['whole'] as $date) {
            if ($inRange($date)) {
                $counted[$date] = 1.0;
            }
        }
        foreach ($this->weekendDaysByCases($userid, $startDate, $endDate, $filings, false, $absentDates) as $date) {
            $counted[$date] = 1.0;
        }
        foreach ($filings['partial'] as $date => $lwop) {
            if ($inRange($date) && !isset($counted[$date])) {
                $counted[$date] = $lwop['days'];
            }
        }
        foreach ($absentDates as $date) {
            if ($inRange($date) && !isset($counted[$date])) {
                $counted[$date] = 1.0;
            }
        }

        return round(array_sum($counted), 3);
    }

    /**
     * Unexcused absence dates (scheduled work day, no complete logs, no
     * leave/OB/HO). Weekends never affect absences, so the LWOP lookups inside
     * skip the weekend cases (resolvingLwopCases) - no recursion, less work.
     */
    private function absentDatesBetween($userid, string $from, string $to): array
    {
        $wasResolving = $this->resolvingLwopCases;
        $this->resolvingLwopCases = true;
        try {
            return $this->getDailyBreakdown($userid, $from, $to)
                ->filter(fn($day) => !empty($day['is_absent']))
                ->keys()
                ->all();
        } finally {
            $this->resolvingLwopCases = $wasResolving;
        }
    }

    /**
     * LWOP Settings weekend cases: the Saturdays and Sundays in the range that
     * count as LWOP. Each weekend is judged by the status of the Friday before
     * and the Monday after it - lwop, present, absent or paid_leave - against
     * the cases ('friday|monday' => saturday/sunday). No case = present.
     * Holidays always count as present, and a holiday ends the weekend (a
     * Friday holiday leaves Saturday with no Friday status, a Saturday holiday
     * does the same for Sunday). A Saturday or Sunday plotted as a work day is
     * not a weekend day and is never judged here.
     *
     * @param bool          $lwopNeighbourOnly only weekends next to an LWOP day (the lwop_days figure)
     * @param string[]|null $absentDates       known absences (null = look them up)
     * @return string[] dates within $startDate..$endDate
     */
    private function weekendDaysByCases($userid, string $startDate, string $endDate, array $filings, bool $lwopNeighbourOnly, ?array $absentDates = null): array
    {
        $cases = (new LwopCaseService())->cases();
        $anyCounts = false;
        foreach ($cases as $case) {
            $anyCounts = $anyCounts || $case['saturday'] || $case['sunday'];
        }
        if (!$anyCounts) {
            return [];
        }

        $holidaySet = array_fill_keys($filings['holidays'], true);
        $codes = $filings['scheduleCodes'];
        $isPlottedWorkDay = fn(string $date) => array_key_exists($date, $codes)
            && strtoupper(trim((string) $codes[$date])) !== 'OFF';

        // Each weekend touching the range: [saturday, sunday, friday, monday].
        $weekends = [];
        for ($day = Carbon::parse($startDate)->startOfDay(); $day->toDateString() <= $endDate; $day->addDay()) {
            if ($day->isSaturday() || ($day->isSunday() && $day->toDateString() === $startDate)) {
                $saturday = $day->isSaturday() ? $day->copy() : $day->copy()->subDay();
                $weekends[$saturday->toDateString()] = [
                    'saturday' => $saturday->toDateString(),
                    'sunday' => $saturday->copy()->addDay()->toDateString(),
                    'friday' => $saturday->copy()->subDay()->toDateString(),
                    'monday' => $saturday->copy()->addDays(2)->toDateString(),
                ];
            }
        }
        if (empty($weekends)) {
            return [];
        }

        $neighbours = array_merge(array_column($weekends, 'friday'), array_column($weekends, 'monday'));
        $from = min($neighbours);
        $to = max($neighbours);
        $whole = array_fill_keys($filings['whole'], true);
        $paidLeave = array_fill_keys($this->getApprovedLeaveDatesByRange($userid, $from, $to), true);
        $yesterday = Carbon::yesterday()->toDateString();
        if ($absentDates === null) {
            $absentTo = min($to, $yesterday);
            $absentDates = $absentTo >= $from ? $this->absentDatesBetween($userid, $from, $absentTo) : [];
        }
        $absent = array_fill_keys($absentDates, true);

        $statusOf = function (string $date) use ($whole, $paidLeave, $absent, $holidaySet, $codes, $yesterday): string {
            if (isset($whole[$date])) {
                return 'lwop';
            }
            if (isset($holidaySet[$date])) {
                return 'holiday';
            }
            if (array_key_exists($date, $codes) && strtoupper(trim((string) $codes[$date])) === 'OFF') {
                return 'off';
            }
            if (isset($paidLeave[$date])) {
                return 'paid_leave';
            }
            if ($date > $yesterday) {
                return 'unknown';
            }

            return isset($absent[$date]) ? 'absent' : 'present';
        };

        $counted = [];
        foreach ($weekends as $w) {
            $satRest = !isset($holidaySet[$w['saturday']]) && !$isPlottedWorkDay($w['saturday']);
            $sunRest = !isset($holidaySet[$w['sunday']]) && !$isPlottedWorkDay($w['sunday']);

            // Saturday: Friday before it; after it, Monday - unless Sunday is not a plain rest day.
            if ($satRest) {
                $before = $statusOf($w['friday']);
                $after = $sunRest ? $statusOf($w['monday']) : $statusOf($w['sunday']);
                if ($this->weekendCaseCounts($cases, $before, $after, 'saturday', $lwopNeighbourOnly)) {
                    $counted[] = $w['saturday'];
                }
            }
            // Sunday: Monday after it; before it, Friday - unless Saturday is not a plain rest day.
            if ($sunRest) {
                $before = $satRest ? $statusOf($w['friday']) : $statusOf($w['saturday']);
                $after = $statusOf($w['monday']);
                if ($this->weekendCaseCounts($cases, $before, $after, 'sunday', $lwopNeighbourOnly)) {
                    $counted[] = $w['sunday'];
                }
            }
        }

        return array_values(array_filter($counted, fn($date) => $date >= $startDate && $date <= $endDate));
    }

    private function weekendCaseCounts(array $cases, string $friday, string $monday, string $day, bool $lwopNeighbourOnly): bool
    {
        if ($lwopNeighbourOnly && $friday !== 'lwop' && $monday !== 'lwop') {
            return false;
        }

        return (bool) ($cases[$friday . '|' . $monday][$day] ?? false);
    }

    /** Holidays and plotted schedule codes around the range, for when no LWOP was filed. */
    private function emptyLwopFilings($userid, string $startDate, string $endDate): array
    {
        [$bufferedStart, $bufferedEnd] = $this->lwopBuffer($startDate, $endDate);

        return [
            'whole' => [],
            'partial' => [],
            'holidays' => $this->getHolidayDatesByRange($bufferedStart, $bufferedEnd),
            'scheduleCodes' => array_map(fn($schedule) => $schedule->code, $this->mapSchedulesWithBreaks(
                $this->getEmployeePlottedSchedules($userid, $bufferedStart, $bufferedEnd)
            )->all()),
        ];
    }

    /** A week either side covers the Friday before and the Monday after any weekend in the range. */
    private function lwopBuffer(string $startDate, string $endDate): array
    {
        return [
            Carbon::parse($startDate)->copy()->subDays(7)->toDateString(),
            Carbon::parse($endDate)->copy()->addDays(7)->toDateString(),
        ];
    }

    /**
     * Approved LWOP filings around the range (see lwopBuffer), split into
     * whole-day dates and partial days [date => ['days' =>, 'hours' =>]], with
     * the holidays and plotted schedule codes the weekend cases need. Null when
     * nothing was filed.
     */
    private function collectLwopFilings($userid, $startDate, $endDate): ?array
    {
        [$bufferedStart, $bufferedEnd] = $this->lwopBuffer($startDate, $endDate);

        $rows = DB::table('hris.leave_applications as la')
            ->join('hris.leave_date_applied as lda', 'la.id', '=', 'lda.leave_application_id')
            ->where('la.user_id', $userid)
            ->where('la.is_lwop', 1)
            ->whereRaw('LOWER(la.status) = ?', ['approved'])
            ->where('lda.status', 'active') // cancelled dates no longer count as LWOP
            ->where('lda.final_disapproved', 0) // DH-disapproved dates are not LWOP
            ->whereBetween('lda.date_applied', [$bufferedStart, $bufferedEnd])
            ->get(['lda.date_applied', 'lda.hours']);

        if ($rows->isEmpty()) {
            return null;
        }

        $hoursByDate = [];
        foreach ($rows as $row) {
            $date = Carbon::parse($row->date_applied)->toDateString();
            $hoursByDate[$date] = ($hoursByDate[$date] ?? 0) + (int) $row->hours;
        }

        $holidayDates = $this->getHolidayDatesByRange($bufferedStart, $bufferedEnd);
        $schedules = $this->mapSchedulesWithBreaks(
            $this->getEmployeePlottedSchedules($userid, $bufferedStart, $bufferedEnd)
        )->all();
        $scheduleCodes = array_map(fn($schedule) => $schedule->code, $schedules);

        $partial = [];
        $wholeDays = [];
        foreach ($hoursByDate as $date => $hours) {
            $scheduledMinutes = $this->scheduledWorkMinutes($schedules[$date] ?? null);
            $days = $scheduledMinutes > 0 ? min(1.0, round(($hours * 60) / $scheduledMinutes, 3)) : 1.0;

            if ($days >= 1.0) {
                $wholeDays[] = $date;
            } else {
                $partial[$date] = ['days' => $days, 'hours' => $hours];
            }
        }

        return [
            'whole' => $wholeDays,
            'partial' => $partial,
            'holidays' => $holidayDates,
            'scheduleCodes' => $scheduleCodes,
        ];
    }

    /**
     * Working minutes of a plotted shift (time_in..time_out, overnight aware,
     * less the break inside it). Unplotted dates = 8 hours; OFF = 0.
     */
    private function scheduledWorkMinutes($schedule): int
    {
        if (!$schedule) {
            return 480;
        }
        if (strtoupper(trim((string) ($schedule->code ?? ''))) === 'OFF'
            || empty($schedule->time_in) || empty($schedule->time_out)) {
            return 0;
        }

        $in = Carbon::parse('2000-01-01 ' . $schedule->time_in);
        $out = Carbon::parse('2000-01-01 ' . $schedule->time_out);
        if ($out->lte($in)) {
            $out->addDay();
        }
        $minutes = $in->diffInMinutes($out);

        if (!empty($schedule->break_out) && !empty($schedule->break_in)) {
            $breakOut = Carbon::parse('2000-01-01 ' . $schedule->break_out);
            if ($breakOut->lt($in)) {
                $breakOut->addDay();
            }
            $breakIn = Carbon::parse($breakOut->toDateString() . ' ' . $schedule->break_in);
            if ($breakIn->lte($breakOut)) {
                $breakIn->addDay();
            }
            $overlapStart = $breakOut->copy()->max($in);
            $overlapEnd = $breakIn->copy()->min($out);
            if ($overlapEnd->gt($overlapStart)) {
                $minutes -= $overlapStart->diffInMinutes($overlapEnd);
            }
        }

        return max(0, $minutes);
    }

    private function getApprovedSpecialDutyDates($userid, $month, $year)
    {
        $hospitalOrderDates = DB::table('hris.hospital_order_employees')
            ->where('userid', $userid)
            ->where('status', 'approved')
            ->whereMonth('filed_date', $month)
            ->whereYear('filed_date', $year)
            ->pluck('filed_date')
            ->toArray();

        $overtimeDates = DB::table('hris.overtime_requests')
            ->where('userid', $userid)
            ->where('status', 'approved')
            ->whereMonth('ot_date', $month)
            ->whereYear('ot_date', $year)
            ->pluck('ot_date')
            ->toArray();

        return array_values(array_unique(array_merge($hospitalOrderDates, $overtimeDates)));
    }

    /**
     * Coverage is the parent hospital_orders' start_date..end_date range,
     * not the single filed_date on the employee's filing - filing is a
     * one-time acknowledgement of the order, not a per-day attestation.
     */
    private function getApprovedSpecialDutyDatesByRange($userid, $startDate, $endDate)
    {
        $rangeStart = Carbon::parse($startDate)->startOfDay();
        $rangeEnd = Carbon::parse($endDate)->endOfDay();

        $hospitalOrderRanges = DB::table('hris.hospital_order_employees as hoe')
            ->join('hris.hospital_orders as ho', 'ho.id', '=', 'hoe.ho_id')
            ->where('hoe.userid', $userid)
            ->where('hoe.status', 'approved')
            ->where('ho.start_date', '<=', $endDate)
            ->where('ho.end_date', '>=', $startDate)
            ->select('ho.start_date', 'ho.end_date')
            ->distinct()
            ->get();

        $hospitalOrderDates = [];
        foreach ($hospitalOrderRanges as $ho) {
            $d = Carbon::parse($ho->start_date)->max($rangeStart)->startOfDay();
            $rangeCap = Carbon::parse($ho->end_date)->min($rangeEnd)->startOfDay();
            for (; $d->lte($rangeCap); $d->addDay()) {
                $hospitalOrderDates[] = $d->toDateString();
            }
        }

        $overtimeDates = DB::table('hris.overtime_requests')
            ->where('userid', $userid)
            ->where('status', 'approved')
            ->whereBetween('ot_date', [$startDate, $endDate])
            ->pluck('ot_date')
            ->map(function ($date) {
                return Carbon::parse($date)->toDateString();
            })
            ->toArray();

        return array_values(array_unique(array_merge($hospitalOrderDates, $overtimeDates)));
    }

    private function getApprovedPassSlipDates($userid, $month, $year)
    {
        $passSlips = DB::table('hris.pass_slips')
            ->where('user_id', $userid)
            ->whereRaw('LOWER(status) = ?', ['approved'])
            ->whereMonth('slip_date', $month)
            ->whereYear('slip_date', $year)
            ->get(['slip_date', 'type']);

        $result = [];
        foreach ($passSlips as $ps) {
            $result[$ps->slip_date] = $ps->type; // map date to type ('official' or 'personal')
        }

        return $result;
    }

    private function getApprovedPassSlipDatesByRange($userid, $startDate, $endDate)
    {
        return DB::table('hris.pass_slips')
            ->where('user_id', $userid)
            ->whereRaw('LOWER(status) = ?', ['approved'])
            ->whereRaw('LOWER(type) = ?', ['official'])
            ->whereBetween('slip_date', [$startDate, $endDate])
            ->pluck('slip_date')
            ->map(function ($date) {
                return Carbon::parse($date)->toDateString();
            })
            ->unique()
            ->values()
            ->toArray();
    }

    private function getApprovedPassSlipsByRange($userid, $startDate, $endDate)
    {
        return DB::table('hris.pass_slips')
            ->where('user_id', $userid)
            ->whereRaw('LOWER(status) = ?', ['approved'])
            ->whereBetween('slip_date', [$startDate, $endDate])
            ->get(['slip_date', 'type', 'time_out', 'time_in']);
    }

    private function getApprovedHospitalOrderDatesByRange($userid, $startDate, $endDate)
    {
        return DB::table('hris.hospital_order_employees')
            ->where('userid', $userid)
            ->where('status', 'approved')
            ->whereBetween('filed_date', [$startDate, $endDate])
            ->pluck('filed_date')
            ->map(function ($date) {
                return Carbon::parse($date)->toDateString();
            })
            ->unique()
            ->values()
            ->toArray();
    }

    private function getApprovedOvertimeMinutesByRange($userid, $startDate, $endDate): int
    {
        return (int) DB::table('hris.overtime_requests')
            ->where('userid', $userid)
            ->where('status', 'approved')
            ->whereBetween('ot_date', [$startDate, $endDate])
            ->get(['ot_date', 'time_from', 'time_to'])
            ->sum(function ($overtime) {
                if (empty($overtime->time_from) || empty($overtime->time_to)) {
                    return 0;
                }

                $start = Carbon::parse($overtime->ot_date . ' ' . $overtime->time_from);
                $end = Carbon::parse($overtime->ot_date . ' ' . $overtime->time_to);

                return $end->gt($start) ? $start->diffInMinutes($end) : 0;
            });
    }

    public function getCutoffBreakdown($userid, $startDate, $endDate): array
    {
        $result = $this->getTotalLateAbsent($userid, $startDate, $endDate);
        $approvedLeaves = $this->getApprovedLeaveDatesByRange($userid, $startDate, $endDate);
        $lwopDays = $this->getLwopDaysByRange($userid, $startDate, $endDate);
        $approvedHospitalOrders = $this->getApprovedHospitalOrderDatesByRange($userid, $startDate, $endDate);
        $approvedOvertimeMinutes = $this->getApprovedOvertimeMinutesByRange($userid, $startDate, $endDate);

        return [
            'late_minutes' => (int) ($result['late_minutes'] ?? 0),
            'undertime_minutes' => (int) ($result['undertime_minutes'] ?? 0),
            'absences' => (int) ($result['absences'] ?? 0),
            'approved_overtime_minutes' => $approvedOvertimeMinutes,
            // Personal pass slip minutes inside working hours on days actually
            // worked (not the raw time_out..time_in). Charged to VL earned
            // credits only - payroll does not read this column.
            'approved_pass_slips_minutes' => (int) ($result['pass_slip_minutes'] ?? 0),
            'approved_leave_days' => count(array_unique($approvedLeaves)),
            // CSC Sec. 33 LWOP days; half-day LWOP = 0.5 (see getLwopDaysByRange).
            'lwop_days' => round(array_sum(array_column($lwopDays, 'days')), 3),
            'hospital_order_days' => count(array_unique($approvedHospitalOrders)),
        ];
    }

}

