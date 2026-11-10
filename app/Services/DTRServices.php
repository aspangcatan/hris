<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DTRServices
{
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
     * LWOP = approved LWOP filings (not cancelled, not DH-disapproved) plus
     * CSC MC 41 s. 1998, Rule XVI, Sec. 33 as set in LWOP Settings (the rule
     * set in force on $startDate, LwopRuleService). With the base rules: an
     * absence right before/after a rest day or holiday does NOT make that day
     * LWOP; only a continuous LWOP run longer than 7 calendar days also counts
     * the rest days and holidays inside it (days 1.0, hours null). Rest days
     * follow the plotted schedule (OFF; unplotted dates -> Sat/Sun). A
     * partial-day LWOP counts filed hours / scheduled hours (4 of 8 = 0.5) and
     * neither starts nor continues a run.
     */
    private function getLwopDaysByRange($userid, $startDate, $endDate): array
    {
        $rules = $this->lwopRules($startDate);
        $filings = $this->collectLwopFilings($userid, $startDate, $endDate, $rules);
        if ($filings === null) {
            return [];
        }

        $runDates = $filings['whole'];
        if (!$rules->partial_day_breaks_run) {
            $runDates = array_merge($runDates, array_keys($filings['partial']));
        }

        $effective = $rules->rest_day_rule === 'after_lwop'
            ? $this->withRestDaysAfterLwop($filings['whole'], $filings['holidays'], $filings['scheduleCodes'], $rules)
            : $this->calculateEffectiveLwopDates($runDates, $filings['holidays'], $filings['scheduleCodes'], $rules);

        $lwop = $filings['partial'];
        foreach ($effective as $date) {
            if (!isset($filings['partial'][$date])) {
                $lwop[$date] = ['days' => 1.0, 'hours' => null];
            }
        }

        $lwop = array_filter($lwop, fn($date) => $date >= $startDate && $date <= $endDate, ARRAY_FILTER_USE_KEY);
        ksort($lwop);

        return $lwop;
    }

    /**
     * Days without pay for earned VL/SL credits in the range (audit C1):
     * approved LWOP plus unexcused absences (scheduled work day, no complete
     * logs, no leave/OB/HO), with Sec. 33 applied to both together, all as set
     * in LWOP Settings. With the base rules a continuous run of LWOP and/or
     * absences longer than 7 calendar days also counts the rest days and
     * holidays inside it, and partial-day LWOP adds its fraction without
     * joining a run. Credits use the month basis (30) minus this number.
     */
    public function getCreditWithoutPayDaysByRange($userid, $startDate, $endDate): float
    {
        $rules = $this->lwopRules($startDate);
        $filings = $this->collectLwopFilings($userid, $startDate, $endDate, $rules)
            ?? ['whole' => [], 'partial' => [], 'holidays' => null, 'scheduleCodes' => null];

        $absentDates = [];
        if ($rules->absences_count_without_pay) {
            // Absences one day past the run threshold either side are enough to
            // tell whether a run crossing the range edge is long enough; never
            // past yesterday (days not yet worked have no logs).
            $edge = $rules->long_run_min_days + 1;
            $absenceStart = Carbon::parse($startDate)->copy()->subDays($edge)->toDateString();
            $absenceEnd = Carbon::parse($endDate)->copy()->addDays($edge)->min(Carbon::yesterday())->toDateString();
            $absentDates = $absenceEnd >= $absenceStart
                ? $this->getDailyBreakdown($userid, $absenceStart, $absenceEnd)
                    ->filter(fn($day) => !empty($day['is_absent']))
                    ->keys()
                    ->all()
                : [];
        }

        if ($filings['holidays'] === null) {
            if (empty($absentDates)) {
                return 0.0;
            }
            // No LWOP filed: still need holidays and plotted rest days for the runs.
            [$bufferedStart, $bufferedEnd] = $this->lwopBuffer($startDate, $endDate, $rules);
            $filings['holidays'] = $this->getHolidayDatesByRange($bufferedStart, $bufferedEnd);
            $filings['scheduleCodes'] = array_map(fn($schedule) => $schedule->code, $this->mapSchedulesWithBreaks(
                $this->getEmployeePlottedSchedules($userid, $bufferedStart, $bufferedEnd)
            )->all());
        }

        $inRange = fn($date) => $date >= $startDate && $date <= $endDate;

        // Agency rule (LWOP Settings "rest days right after an LWOP day"): only filed
        // whole-day LWOP pulls in the rest days after it; absences count as themselves.
        if ($rules->rest_day_rule === 'after_lwop') {
            $counted = [];
            foreach ($this->withRestDaysAfterLwop($filings['whole'], $filings['holidays'], $filings['scheduleCodes'], $rules) as $date) {
                if ($inRange($date)) {
                    $counted[$date] = 1.0;
                }
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

        $runDates = $filings['whole'];
        if (!$rules->partial_day_breaks_run) {
            $runDates = array_merge($runDates, array_keys($filings['partial']));
        }
        $looseAbsences = [];
        if ($rules->absences_join_runs) {
            $runDates = array_merge($runDates, $absentDates);
        } else {
            $looseAbsences = $absentDates;
        }

        $counted = [];
        foreach ($this->calculateEffectiveLwopDates(array_values(array_unique($runDates)), $filings['holidays'], $filings['scheduleCodes'], $rules) as $date) {
            if ($inRange($date)) {
                $counted[$date] = isset($filings['partial'][$date]) ? $filings['partial'][$date]['days'] : 1.0;
            }
        }
        foreach ($filings['partial'] as $date => $lwop) {
            if ($inRange($date) && !isset($counted[$date])) {
                $counted[$date] = $lwop['days'];
            }
        }
        foreach ($looseAbsences as $date) {
            if ($inRange($date) && !isset($counted[$date])) {
                $counted[$date] = 1.0;
            }
        }

        return round(array_sum($counted), 3);
    }

    /**
     * Agency rule (LWOP Settings rest_day_rule = after_lwop): each filed whole-day
     * LWOP date plus the rest days right after it, up to the next work day -
     * Fri LWOP = Fri + Sat + Sun; Mon LWOP = Mon only; Fri + Mon = 4. A holiday in
     * that stretch counts only when after_lwop_includes_holidays is on; otherwise
     * it ends the stretch. Rest days follow the plotted schedule as elsewhere.
     *
     * @param string[] $wholeDates filed whole-day LWOP dates
     * @return string[] sorted dates
     */
    private function withRestDaysAfterLwop(array $wholeDates, array $holidayDates, array $scheduleCodes, object $rules): array
    {
        $holidaySet = array_fill_keys($holidayDates, true);
        $dates = array_fill_keys($wholeDates, true);

        foreach ($wholeDates as $date) {
            $cursor = Carbon::parse($date)->addDay();
            // A month of rest days in a row only happens with bad schedule data; stop there.
            for ($i = 0; $i < 31; $i++) {
                $day = $cursor->toDateString();
                $isRest = isset($holidaySet[$day])
                    ? $rules->after_lwop_includes_holidays
                    : $this->isLwopRestDay($cursor, [], $scheduleCodes, $rules);
                if (!$isRest) {
                    break;
                }
                $dates[$day] = true;
                $cursor->addDay();
            }
        }

        $result = array_keys($dates);
        sort($result);

        return $result;
    }

    /** The LWOP Settings rule set in force on $date. */
    private function lwopRules(string $date): object
    {
        return (new LwopRuleService())->forDate($date);
    }

    /** Look-around window: wide enough to see a run that started before / ends after the range. */
    private function lwopBuffer(string $startDate, string $endDate, object $rules): array
    {
        $days = max(60, $rules->long_run_min_days + 1);

        return [
            Carbon::parse($startDate)->copy()->subDays($days)->toDateString(),
            Carbon::parse($endDate)->copy()->addDays($days)->toDateString(),
        ];
    }

    /**
     * Approved LWOP filings around the range (see lwopBuffer), split into
     * whole-day dates and partial days [date => ['days' =>, 'hours' =>]], with
     * the holidays and plotted schedule codes Sec. 33 needs. Partial days are
     * whole days when the rule set says so. Null when nothing was filed.
     */
    private function collectLwopFilings($userid, $startDate, $endDate, object $rules): ?array
    {
        [$bufferedStart, $bufferedEnd] = $this->lwopBuffer($startDate, $endDate, $rules);

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

            if ($days >= 1.0 || $rules->partial_day_mode === 'whole') {
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

    /**
     * Rest day for Sec. 33: a holiday (when holidays bridge runs), a plotted
     * OFF day, or - when the date is not plotted, or the rule set says to
     * always use the calendar - a Saturday/Sunday.
     *
     * @param array<string, true>    $holidaySet
     * @param array<string, ?string> $scheduleCodes date => plotted schedule code
     */
    private function isLwopRestDay(Carbon $day, array $holidaySet, array $scheduleCodes, object $rules): bool
    {
        $date = $day->toDateString();
        if (isset($holidaySet[$date])) {
            return $rules->holidays_bridge_runs;
        }
        if ($rules->rest_day_source === 'schedule' && array_key_exists($date, $scheduleCodes)) {
            return strtoupper(trim((string) $scheduleCodes[$date])) === 'OFF';
        }

        return $day->isWeekend();
    }

    /**
     * Run dates plus, for each continuous run longer than the rule set's
     * threshold, the rest days and holidays inside it.
     *
     * @param string[]               $lwopDates     whole-day run dates
     * @param string[]               $holidayDates
     * @param array<string, ?string> $scheduleCodes date => plotted schedule code (empty = Sat/Sun rule)
     * @param object|null            $rules         LwopRuleService rule set (null = base rules)
     */
    private function calculateEffectiveLwopDates(array $lwopDates, array $holidayDates, array $scheduleCodes = [], ?object $rules = null): array
    {
        $rules = $rules ?? LwopRuleService::defaults();
        $sortedDates = array_values(array_unique($lwopDates));
        sort($sortedDates);

        if (!$rules->count_rest_days_in_long_runs) {
            return $sortedDates;
        }

        $holidaySet = array_fill_keys($holidayDates, true);
        $runs = $this->groupIntoLwopRuns($sortedDates, $holidaySet, $scheduleCodes, $rules);

        $effectiveDates = [];
        foreach ($runs as $run) {
            foreach ($run as $date) {
                $effectiveDates[] = $date;
            }

            $first = Carbon::parse($run[0]);
            $last = Carbon::parse(end($run));
            $span = $first->diffInDays($last) + 1;

            if ($span > $rules->long_run_min_days) {
                $cursor = $first->copy();
                while ($cursor->lte($last)) {
                    $dateString = $cursor->toDateString();
                    if ($this->isLwopRestDay($cursor, $holidaySet, $scheduleCodes, $rules) && !in_array($dateString, $run, true)) {
                        $effectiveDates[] = $dateString;
                    }
                    $cursor->addDay();
                }
            }
        }

        return array_values(array_unique($effectiveDates));
    }

    private function groupIntoLwopRuns(array $sortedDates, array $holidaySet, array $scheduleCodes, object $rules): array
    {
        $runs = [];
        $currentRun = [];

        foreach ($sortedDates as $date) {
            if (empty($currentRun)) {
                $currentRun[] = $date;
                continue;
            }

            $previousDate = end($currentRun);
            if ($this->isBridgedGap($previousDate, $date, $holidaySet, $scheduleCodes, $rules)) {
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

    /** True when every day between the two run dates is a rest day (or bridging holiday). */
    private function isBridgedGap(string $fromDate, string $toDate, array $holidaySet, array $scheduleCodes, object $rules): bool
    {
        $cursor = Carbon::parse($fromDate)->copy()->addDay();
        $to = Carbon::parse($toDate);

        while ($cursor->lt($to)) {
            if (!$this->isLwopRestDay($cursor, $holidaySet, $scheduleCodes, $rules)) {
                return false;
            }
            $cursor->addDay();
        }

        return true;
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

