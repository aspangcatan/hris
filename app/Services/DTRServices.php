<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DTRServices
{
    public function getTotalPresent($userid, $month, $year)
    {
        $workdays = $this->getAllDays($year, $month); // includes weekends
        $holidays = $this->getHolidays($month, $year); // fetch holidays
        $leaveDates = $this->getApprovedLeaveDates($userid, $month, $year); // fetch approved leave dates
        $specialDutyDates = $this->getApprovedSpecialDutyDates($userid, $month, $year); // approved H.O and OT dates
        $presentDays = [];
        $absentDays = [];

        // Step 1: Determine initial present/absent days
        foreach ($workdays as $date) {
            // If date is a holiday, approved leave, or approved special duty, mark as present directly
            if (in_array($date, $holidays) || in_array($date, $leaveDates) || in_array($date, $specialDutyDates)) {
                $presentDays[$date] = true;
                unset($absentDays[$date]);
                continue;
            }

            $firstIn = $this->getFirstInLog($userid, $date);
            $lastOut = $this->getLastOutLog($userid, $date);

            if ($firstIn && $lastOut) {
                $presentDays[$date] = true;
            } else {
                $absentDays[$date] = true;
            }
        }

        // Step 2: Apply weekend rules
        foreach ($workdays as $date) {
            $dayOfWeek = Carbon::parse($date)->format('l');

            // Skip weekend rule processing if holiday, leave, or approved special duty
            if (in_array($date, $holidays) || in_array($date, $leaveDates) || in_array($date, $specialDutyDates)) {
                continue;
            }

            if ($dayOfWeek === 'Saturday') {
                $friday = Carbon::parse($date)->copy()->subDay()->toDateString();
                $sunday = Carbon::parse($date)->copy()->addDay()->toDateString();
                $monday = Carbon::parse($date)->copy()->addDays(2)->toDateString();

                // Rule 1: Absent Friday + Present Monday => Sat & Sun Absent
                if (isset($absentDays[$friday]) && isset($presentDays[$monday])) {
                    $absentDays[$date] = true; // Saturday
                    $absentDays[$sunday] = true; // Sunday
                    unset($presentDays[$date], $presentDays[$sunday]);
                }
                // Rule 2: Present Friday + Absent Monday => Sat Absent (if no entry)
                elseif (isset($presentDays[$friday]) && isset($absentDays[$monday])) {
                    if (!isset($presentDays[$date])) {
                        $absentDays[$date] = true; // Saturday only
                        unset($presentDays[$date]);
                    }
                }
                // Rule 3: Absent Friday + Absent Monday => Sat & Sun Absent
                elseif (isset($absentDays[$friday]) && isset($absentDays[$monday])) {
                    $absentDays[$date] = true; // Saturday
                    $absentDays[$sunday] = true; // Sunday
                    unset($presentDays[$date], $presentDays[$sunday]);
                }
                // Rule 4: Present Friday + Present Monday => Sat & Sun Present
                elseif (isset($presentDays[$friday]) && isset($presentDays[$monday])) {
                    $presentDays[$date] = true; // Saturday
                    $presentDays[$sunday] = true; // Sunday
                    unset($absentDays[$date], $absentDays[$sunday]);
                }
            }
        }

        // Step 2.5: Handle month boundary weekends
        $startDate = Carbon::createFromDate($year, $month, 1)->toDateString();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        if (Carbon::parse($startDate)->isSunday()) {
            $monday = Carbon::parse($startDate)->copy()->addDay()->toDateString();
            if (isset($presentDays[$monday])) {
                $presentDays[$startDate] = true;
                unset($absentDays[$startDate]);
            }
        }

        if (Carbon::parse($endDate)->isSaturday()) {
            $friday = Carbon::parse($endDate)->copy()->subDay()->toDateString();
            if (isset($presentDays[$friday])) {
                $presentDays[$endDate] = true;
                unset($absentDays[$endDate]);
            }
        }

        // Step 3: Recalculate present days after weekend + holiday + leave rules
        $finalPresentCount = 0;
        foreach ($workdays as $date) {
            if (in_array($date, $holidays) || in_array($date, $leaveDates) || in_array($date, $specialDutyDates)) {
                $finalPresentCount++;
            } elseif (!isset($absentDays[$date])) {
                $finalPresentCount++;
            }
        }

        return $finalPresentCount;
    }

    public function getTotalLateAbsent($userid, $start_date, $end_date)
    {
        $lates = 0;
        $undertime = 0;
        $absences = 0;

        $holidays = $this->getHolidayDatesByRange($start_date, $end_date);
        $approvedLeaves = $this->getApprovedLeaveDatesByRange($userid, $start_date, $end_date);
        $approvedPassSlips = $this->getApprovedPassSlipsByRange($userid, $start_date, $end_date);

        $schedules = $this->mapSchedules($this->getEmployeePlottedSchedules($userid, $start_date, $end_date));
        $logMap = $this->getDailyLogMap($userid, $start_date, $end_date);

        foreach ($schedules as $date => $schedule) {
            // Skip weekends and holidays
            if (in_array($date, $holidays) || in_array(Carbon::parse($date)->format('l'), ['Saturday', 'Sunday'])) {
                continue;
            }

            // Skip approved leaves
            if (in_array($date, $approvedLeaves)) {
                continue;
            }

            // Get logs
            $firstIn = $logMap[$date]['in'] ?? null;
            $lastOut = $logMap[$date]['out'] ?? null;

            // Handle approved pass slips
            $passSlip = $approvedPassSlips->firstWhere('slip_date', $date);
            if ($passSlip) {
                $type = strtolower((string) $passSlip->type);
                if ($type === 'official') {
                    // Excused: no late or undertime
                    continue;
                } elseif ($type === 'personal' && !empty($passSlip->time_out) && !empty($passSlip->time_in)) {
                    // Personal pass slip hours are treated as undertime.
                    $timeOut = Carbon::parse($date . ' ' . $passSlip->time_out);
                    $timeIn = Carbon::parse($date . ' ' . $passSlip->time_in);
                    if ($timeIn->gt($timeOut)) {
                        $undertime += $timeOut->diffInMinutes($timeIn);
                    }
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
        [$presentDays, $absentDays, $days, $holidays, $leaveDates, $specialDutyDates] =
            $this->buildPresentAbsentByRange($userid, $start_date, $end_date);

        // Step 3: Recalculate present days after weekend + holiday + leave rules
        $finalPresentCount = 0;
        foreach ($days as $date) {
            if (in_array($date, $holidays) || in_array($date, $leaveDates) || in_array($date, $specialDutyDates)) {
                $finalPresentCount++;
            } elseif (!isset($absentDays[$date])) {
                $finalPresentCount++;
            }
        }

        return $finalPresentCount;
    }

    private function buildPresentAbsentByRange($userid, $start_date, $end_date)
    {
        $days = $this->getAllDaysByRange($start_date, $end_date); // includes weekends
        $holidays = $this->getHolidayDatesByRange($start_date, $end_date);
        $leaveDates = $this->getApprovedLeaveDatesByRange($userid, $start_date, $end_date);
        $specialDutyDates = $this->getApprovedSpecialDutyDatesByRange($userid, $start_date, $end_date);
        $logMap = $this->getDailyLogMap($userid, $start_date, $end_date);

        $presentDays = [];
        $absentDays = [];

        // Step 1: Determine initial present/absent days
        foreach ($days as $date) {
            if (in_array($date, $holidays) || in_array($date, $leaveDates) || in_array($date, $specialDutyDates)) {
                $presentDays[$date] = true;
                unset($absentDays[$date]);
                continue;
            }

            $firstIn = $logMap[$date]['in'] ?? null;
            $lastOut = $logMap[$date]['out'] ?? null;

            if ($firstIn && $lastOut) {
                $presentDays[$date] = true;
            } else {
                $absentDays[$date] = true;
            }
        }

        // Step 2: Apply weekend rules (same as monthly logic)
        foreach ($days as $date) {
            $dayOfWeek = Carbon::parse($date)->format('l');

            if (in_array($date, $holidays) || in_array($date, $leaveDates) || in_array($date, $specialDutyDates)) {
                continue;
            }

            if ($dayOfWeek === 'Saturday') {
                $friday = Carbon::parse($date)->copy()->subDay()->toDateString();
                $sunday = Carbon::parse($date)->copy()->addDay()->toDateString();
                $monday = Carbon::parse($date)->copy()->addDays(2)->toDateString();

                if (isset($absentDays[$friday]) && isset($presentDays[$monday])) {
                    $absentDays[$date] = true;
                    $absentDays[$sunday] = true;
                    unset($presentDays[$date], $presentDays[$sunday]);
                } elseif (isset($presentDays[$friday]) && isset($absentDays[$monday])) {
                    if (!isset($presentDays[$date])) {
                        $absentDays[$date] = true;
                        unset($presentDays[$date]);
                    }
                } elseif (isset($absentDays[$friday]) && isset($absentDays[$monday])) {
                    $absentDays[$date] = true;
                    $absentDays[$sunday] = true;
                    unset($presentDays[$date], $presentDays[$sunday]);
                } elseif (isset($presentDays[$friday]) && isset($presentDays[$monday])) {
                    $presentDays[$date] = true;
                    $presentDays[$sunday] = true;
                    unset($absentDays[$date], $absentDays[$sunday]);
                }
            }
        }

        // Step 2.5: Handle range boundary weekends
        $rangeStart = Carbon::parse($start_date)->toDateString();
        $rangeEnd = Carbon::parse($end_date)->toDateString();

        if (Carbon::parse($rangeStart)->isSunday()) {
            $monday = Carbon::parse($rangeStart)->copy()->addDay()->toDateString();
            if (isset($presentDays[$monday])) {
                $presentDays[$rangeStart] = true;
                unset($absentDays[$rangeStart]);
            }
        }

        if (Carbon::parse($rangeEnd)->isSaturday()) {
            $friday = Carbon::parse($rangeEnd)->copy()->subDay()->toDateString();
            if (isset($presentDays[$friday])) {
                $presentDays[$rangeEnd] = true;
                unset($absentDays[$rangeEnd]);
            }
        }

        return [$presentDays, $absentDays, $days, $holidays, $leaveDates, $specialDutyDates];
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
                'time_in' => $schedule->time_in,
                'time_out' => $schedule->time_out,
                'time_in_latest' => $schedule->time_in_latest,
                'time_out_latest' => $schedule->time_out_latest
            ];
        }
        return $scheduleMap;
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

        $actualIn = Carbon::parse($firstIn)->startOfMinute();

        // Handle late check-in
        if ($actualIn->gt($expectedInLatest)) {
            $lateMinutes = $actualIn->diffInMinutes($expectedInLatest);
        }

        $shiftDuration = $expectedOut->diffInMinutes($expectedIn);
        $requiredOut = $actualIn->copy()->addMinutes($shiftDuration);

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

    private function getApprovedPassSlipsByRange($userid, $startDate, $endDate)
    {
        return DB::table('hris.pass_slips')
            ->where('user_id', $userid)
            ->whereRaw('LOWER(status) = ?', ['approved'])
            ->whereBetween('slip_date', [$startDate, $endDate])
            ->get(['slip_date', 'type', 'time_out', 'time_in']);
    }

}

