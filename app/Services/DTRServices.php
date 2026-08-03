<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DTRServices
{
    private function calculatePersonalPassSlipMinutes($date, $timeOut, $timeIn): int
    {
        if (empty($timeOut) || empty($timeIn)) {
            return 0;
        }

        $start = Carbon::parse($date . ' ' . $timeOut);
        $end = Carbon::parse($date . ' ' . $timeIn);

        return $end->gt($start) ? $start->diffInMinutes($end) : 0;
    }

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
        $lwopDates = $this->getApprovedLwopDatesByRange($userid, $start_date, $end_date);
        $hospitalOrders = array_fill_keys($this->getApprovedSpecialDutyDatesByRange($userid, $start_date, $end_date), true);
        $passSlips = array_fill_keys(
            $this->getApprovedPassSlipsByRange($userid, $start_date, $end_date)->pluck('slip_date')->unique()->all(),
            true
        );

        $specialDutyDates = $this->getApprovedSpecialDutyDatesByRange($userid, $start_date, $end_date);
        $officialPassSlipDates = $this->getApprovedPassSlipDatesByRange($userid, $start_date, $end_date);
        $absenceExemptDates = array_fill_keys(
            array_unique(array_merge($leaveDates, $lwopDates, $specialDutyDates, $officialPassSlipDates)),
            true
        );

        return [
            'holidays' => $holidays,
            'leaves' => array_fill_keys(array_unique(array_merge($leaveDates, $lwopDates)), true),
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

    private function resolveExpectedWorkDates(array $schedules, $startDate, $endDate, array $holidays): array
    {
        if (!empty($schedules)) {
            return collect(array_keys($schedules))
                ->reject(function ($date) use ($holidays) {
                    return in_array($date, $holidays, true);
                })
                ->values()
                ->all();
        }

        return collect($this->getAllDaysByRange($startDate, $endDate))
            ->filter(function ($date) use ($holidays) {
                $day = Carbon::parse($date);
                return $day->isWeekday() && !in_array($date, $holidays, true);
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
            ->whereBetween('lda.date_applied', [$startDate, $endDate])
            ->pluck('lda.date_applied')
            ->map(function ($date) {
                return Carbon::parse($date)->toDateString();
            })
            ->toArray();
    }

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
        $approvedLwop = $this->getApprovedLwopDatesByRange($userid, $startDate, $endDate);
        $approvedHospitalOrders = $this->getApprovedHospitalOrderDatesByRange($userid, $startDate, $endDate);
        $approvedPassSlips = $this->getApprovedPassSlipsByRange($userid, $startDate, $endDate);
        $approvedOvertimeMinutes = $this->getApprovedOvertimeMinutesByRange($userid, $startDate, $endDate);

        $personalPassSlipMinutes = $approvedPassSlips
            ->filter(function ($passSlip) {
                return strtolower((string) ($passSlip->type ?? '')) === 'personal';
            })
            ->sum(function ($passSlip) {
                return $this->calculatePersonalPassSlipMinutes(
                    $passSlip->slip_date,
                    $passSlip->time_out,
                    $passSlip->time_in
                );
            });

        return [
            'late_minutes' => (int) ($result['late_minutes'] ?? 0),
            'undertime_minutes' => (int) ($result['undertime_minutes'] ?? 0),
            'absences' => (int) ($result['absences'] ?? 0),
            'approved_overtime_minutes' => $approvedOvertimeMinutes,
            'approved_pass_slips_minutes' => (int) $personalPassSlipMinutes,
            'approved_leave_days' => count(array_unique($approvedLeaves)),
            'lwop_days' => count(array_unique($approvedLwop)),
            'hospital_order_days' => count(array_unique($approvedHospitalOrders)),
        ];
    }

}

