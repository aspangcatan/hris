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
        $lates = 0;
        $undertime = 0;
        $absences = 0;

        $holidays = $this->getHolidayDatesByRange($start_date, $end_date);
        $approvedLeaves = $this->getApprovedLeaveDatesByRange($userid, $start_date, $end_date);
        $approvedSpecialDutyDates = $this->getApprovedSpecialDutyDatesByRange($userid, $start_date, $end_date);
        $approvedPassSlips = $this->getApprovedPassSlipsByRange($userid, $start_date, $end_date);

        $schedules = $this->mapSchedules($this->getEmployeePlottedSchedules($userid, $start_date, $end_date));
        $logs = $this->getLogsByRange(
            $userid,
            Carbon::parse($start_date)->copy()->subDay()->toDateString(),
            Carbon::parse($end_date)->copy()->addDay()->toDateString()
        );

        foreach ($schedules as $date => $schedule) {
            // Skip weekends and holidays
            if (in_array($date, $holidays) || in_array(Carbon::parse($date)->format('l'), ['Saturday', 'Sunday'])) {
                continue;
            }

            // Skip approved leaves
            if (in_array($date, $approvedLeaves) || in_array($date, $approvedSpecialDutyDates)) {
                continue;
            }

            [$firstIn, $lastOut] = $this->resolveAttendanceLogs($logs, $schedules, $date);

            // Handle approved pass slips
            $datePassSlips = $approvedPassSlips->where('slip_date', $date);
            if ($datePassSlips->isNotEmpty()) {
                $hasOfficialPassSlip = $datePassSlips->contains(function ($passSlip) {
                    return strtolower((string) ($passSlip->type ?? '')) === 'official';
                });

                if ($hasOfficialPassSlip) {
                    // Excused: no late or undertime
                    continue;
                }

                $personalPassSlipMinutes = $datePassSlips
                    ->filter(function ($passSlip) {
                        return strtolower((string) ($passSlip->type ?? '')) === 'personal';
                    })
                    ->sum(function ($passSlip) use ($date) {
                        return $this->calculatePersonalPassSlipMinutes($date, $passSlip->time_out, $passSlip->time_in);
                    });

                if ($personalPassSlipMinutes > 0) {
                    // Personal pass slip hours are treated as undertime.
                    $undertime += $personalPassSlipMinutes;
                    continue;
                }
            }

            // If no logs count absence
            if (!$firstIn && !$lastOut) {
                $absences++;
                continue;
            }

            // Process late and undertime
            list($late, $undertimeMinutes) = $this->processLogs($firstIn, $lastOut, $schedule, $date);
            $lates += $late;
            $undertime += $undertimeMinutes;
        }

        // Compute absences over full range (including weekends) using the same present rules.
        [$presentDays, $absentDays] = $this->buildPresentAbsentByRange($userid, $start_date, $end_date);
        $absences = count($absentDays);

        return [
            'late_minutes' => $lates,
            'undertime_minutes' => $undertime,
            'absences' => $absences
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

    private function getFirstInLog($userid, $date)
    {
        return DB::table('hris.dtr_logs')
            ->where('userid', $userid)
            ->whereDate('created_at', $date)
            ->where('status', 'IN')
            ->orderBy('created_at', 'asc')
            ->value('created_at');
    }

    private function getLastOutLog($userid, $date)
    {
        return DB::table('hris.dtr_logs')
            ->where('userid', $userid)
            ->whereDate('created_at', $date)
            ->where('status', 'OUT')
            ->orderBy('created_at', 'desc')
            ->value('created_at');
    }

    private function processLogs($firstIn, $lastOut, $shift, $date)
    {
        $lateMinutes = 0;
        $undertimeMinutes = 0;

        $expectedIn = Carbon::parse("$date {$shift['time_in']}")->startOfMinute();
        $expectedInLatest = $shift['time_in_latest'] ? Carbon::parse("$date {$shift['time_in_latest']}")->startOfMinute() : $expectedIn;
        $expectedOut = Carbon::parse("$date {$shift['time_out']}")->startOfMinute();
        $expectedOutLatest = $shift['time_out_latest'] ? Carbon::parse("$date {$shift['time_out_latest']}")->startOfMinute() : $expectedOut;

        if ($expectedInLatest->lt($expectedIn)) {
            $expectedInLatest->addDay();
        }
        if ($expectedOut->lte($expectedIn)) {
            $expectedOut->addDay();
        }
        if ($expectedOutLatest->lte($expectedOut)) {
            $expectedOutLatest->addDay();
        }

        $actualIn = Carbon::parse($firstIn)->startOfMinute();

        // Handle late check-in
        if ($actualIn->gt($expectedInLatest)) {
            $lateMinutes = $actualIn->diffInMinutes($expectedInLatest);
        }

        $requiredOut = $expectedOut->copy();
        $scheduleType = strtoupper((string) ($shift['type'] ?? 'FIXED'));
        if ($scheduleType === 'GLIDING') {
            if ($actualIn->gt($expectedIn) && $actualIn->lte($expectedInLatest)) {
                $requiredOut = $expectedOut->copy()->addMinutes($expectedIn->diffInMinutes($actualIn));
                if ($requiredOut->gt($expectedOutLatest)) {
                    $requiredOut = $expectedOutLatest->copy();
                }
            } elseif ($actualIn->gt($expectedInLatest)) {
                $requiredOut = $expectedOutLatest->copy();
            }
        }

        // Handle undertime if the employee checks out early
        if ($lastOut) {
            $actualOut = Carbon::parse($lastOut)->startOfMinute();

            // Apply half-day rule (if check-out before 1:00 PM)
            if ($actualOut->lt(Carbon::parse("$date 13:00:00"))) {
                // For half-day undertime calculation, consider 12:00 PM as the cutoff
                $undertimeMinutes = Carbon::parse("$date 12:00:00")->diffInMinutes($actualOut);
                $undertimeMinutes += 240; // Add 240 minutes (4 hours) for half-day
            } else {
                // For full-day undertime, calculate based on the required out time
                if ($actualOut->lt($requiredOut)) {
                    $undertimeMinutes = $actualOut->diffInMinutes($requiredOut);
                }
            }
        }

        return [$lateMinutes, $undertimeMinutes];
    }

    private function getNoScheduleDays($scheduleMap, $workdays, $holidays)
    {
        $plottedDays = array_keys($scheduleMap);
        return array_diff($workdays, $plottedDays, $holidays);
    }

    private function getWorkDays($year, $month)
    {
        $startDate = Carbon::createFromDate($year, $month, 1);
        $endDate = $startDate->copy()->endOfMonth();
        $workdays = [];

        while ($startDate <= $endDate) {
            if ($startDate->isWeekday()) {
                $workdays[] = $startDate->toDateString();
            }
            $startDate->addDay();
        }

        return $workdays;
    }

    // Helper: get all days in month (including weekends)
    private function getAllDays($year, $month)
    {
        $startDate = Carbon::createFromDate($year, $month, 1);
        $endDate = $startDate->copy()->endOfMonth();
        $days = [];

        while ($startDate <= $endDate) {
            $days[] = $startDate->toDateString();
            $startDate->addDay();
        }

        return $days;
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
            ->whereRaw('LOWER(la.status) = ?', ['approved'])
            ->whereBetween('lda.date_applied', [$startDate, $endDate])
            ->pluck('lda.date_applied')
            ->map(function ($date) {
                return Carbon::parse($date)->toDateString();
            })
            ->toArray();
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

    private function getApprovedSpecialDutyDatesByRange($userid, $startDate, $endDate)
    {
        $hospitalOrderDates = DB::table('hris.hospital_order_employees')
            ->where('userid', $userid)
            ->where('status', 'approved')
            ->whereBetween('filed_date', [$startDate, $endDate])
            ->pluck('filed_date')
            ->map(function ($date) {
                return Carbon::parse($date)->toDateString();
            })
            ->toArray();

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
            'hospital_order_days' => count(array_unique($approvedHospitalOrders)),
        ];
    }

}

