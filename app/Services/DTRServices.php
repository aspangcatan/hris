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
        $presentDays = [];
        $absentDays = [];

        // Step 1: Determine initial present/absent days
        foreach ($workdays as $date) {
            // If date is a holiday, mark as present directly
            if (in_array($date, $holidays)) {
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

            // Skip weekend rule processing if holiday
            if (in_array($date, $holidays)) {
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
                } // Rule 2: Present Friday + Absent Monday => Sat Absent (if no entry)
                elseif (isset($presentDays[$friday]) && isset($absentDays[$monday])) {
                    if (!isset($presentDays[$date])) {
                        $absentDays[$date] = true; // Saturday only
                        unset($presentDays[$date]);
                    }
                } // Rule 3: Absent Friday + Absent Monday => Sat & Sun Absent
                elseif (isset($absentDays[$friday]) && isset($absentDays[$monday])) {
                    $absentDays[$date] = true; // Saturday
                    $absentDays[$sunday] = true; // Sunday
                    unset($presentDays[$date], $presentDays[$sunday]);
                } // Rule 4: Present Friday + Present Monday => Sat & Sun Present
                elseif (isset($presentDays[$friday]) && isset($presentDays[$monday])) {
                    $presentDays[$date] = true; // Saturday
                    $presentDays[$sunday] = true; // Sunday
                    unset($absentDays[$date], $absentDays[$sunday]);
                }
            }
        }

        // Step 3: Recalculate present days after weekend + holiday rules
        $finalPresentCount = 0;
        foreach ($workdays as $date) {
            // If holiday, always count as present
            if (in_array($date, $holidays)) {
                $finalPresentCount++;
            } elseif (!isset($absentDays[$date])) {
                $finalPresentCount++;
            }
        }

        return $finalPresentCount;
    }


    public function getTotalLateAbsent($userid, $month, $year)
    {
        $totalLateMinutes = 0;
        $totalUndertimeMinutes = 0;
        $totalAbsentDays = 0;

        $plottedSchedules = $this->getEmployeePlottedSchedules($userid, $month, $year);
        $scheduleMap = $this->mapSchedules($plottedSchedules);
        $holidays = $this->getHolidays($month, $year);
        $workdays = $this->getWorkDays($year, $month);
        $leaveDates = $this->getApprovedLeaveDates($userid, $month, $year);

        foreach ($scheduleMap as $date => $shift) {
            // Skip holidays and approved leaves
            if (in_array($date, $holidays) || in_array($date, $leaveDates)) {
                continue;
            }

            $firstIn = $this->getFirstInLog($userid, $date);
            $lastOut = $this->getLastOutLog($userid, $date);

            // Check for absence if no IN and OUT logs
            if (!$firstIn || !$lastOut) {
                $totalAbsentDays++;
                continue;
            }

            // Process the logs and compute lateness and undertime
            list($lateMinutes, $undertimeMinutes) = $this->processLogs(
                $firstIn, $lastOut, $shift, $date
            );

            $totalLateMinutes += $lateMinutes;
            $totalUndertimeMinutes += $undertimeMinutes;
        }

        // Handle no-schedule days
        $noScheduleDays = $this->getNoScheduleDays($scheduleMap, $workdays, $holidays);
        foreach ($noScheduleDays as $day) {
            if (!in_array($day, $leaveDates)) {
                $totalAbsentDays++;
            }
        }
        
        return [$totalLateMinutes + $totalUndertimeMinutes, $totalAbsentDays];
    }

    public function getHolidays($month, $year)
    {
        return DB::table('hris.calendar_holidays')
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->pluck('date')
            ->toArray();

    }

    private function getEmployeePlottedSchedules($userid, $month, $year)
    {
        return DB::table('hris.schedules as s')
            ->join('hris.schedule_master_lists as sml', 's.schedule_id', '=', 'sml.id')
            ->where('s.employee_id', $userid)
            ->whereMonth('s.schedule_date', $month)
            ->whereYear('s.schedule_date', $year)
            ->select('s.schedule_date', 'sml.time_in', 'sml.time_out', 'sml.time_in_latest', 'sml.time_out_latest')
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

    private function getApprovedLeaveDates($userid, $month, $year)
    {
        return DB::table('hris.leave_applications as la')
            ->join('hris.leave_date_applied as lda', 'la.id', '=', 'lda.leave_application_id')
            ->where('la.user_id', $userid)
            ->where('la.status', 'Approved') // only approved leaves
            ->whereYear('lda.date_applied', $year)
            ->whereMonth('lda.date_applied', $month)
            ->pluck('lda.date_applied')
            ->toArray();
    }

}
