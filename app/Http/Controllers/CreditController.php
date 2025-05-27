<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreditController extends Controller
{
    public function getCreditEarned(Request $request)
    {
        try {
            $logs = DB::table('hris.credit_earned_logs as l')
                ->join('tdh_user.users as u', 'u.id', '=', 'l.user_id')
                ->join('hris.leave_types as t', 't.id', '=', 'l.leave_type_id')
                ->where('l.year', $request->year)
                ->where('l.month', $request->month)
                ->get(['u.fname', 'u.mname', 'u.lname', 'u.suffix', 'l.month', 'l.year', 't.description', 'l.total_absent', 'l.total_late_minutes', 'l.total_undertime_minutes', 'l.earned_points', 'l.deducted_points']);
            return response()->json($logs);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function updateCreditBalance(Request $request)
    {
        try {
            $previousMonth = Carbon::now()->subMonth();
            $month = $previousMonth->month;
            $year = $previousMonth->year;

            // Get all workdays excluding holidays
            $workdays = $this->getWorkDays($year, $month);

            // Fetch all holidays for the month
            $holidays = DB::table('hris.calendar_holidays')
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->pluck('date')
                ->toArray();

            $employees = DB::table('hris.schedules')
                ->select('employee_id')
                ->whereMonth('schedule_date', $month)
                ->whereYear('schedule_date', $year)
                ->groupBy('employee_id')
                ->get();

            foreach ($employees as $employee) {
                $userId = $employee->employee_id;
                $totalLateMinutes = 0;
                $totalUndertimeMinutes = 0;
                $totalAbsentDays = 0;

                // Get all plotted schedules for this employee for the month
                $plottedSchedules = $this->getEmployeePlottedSchedules($userId, $month, $year);

                $scheduleMap = $this->mapSchedules($plottedSchedules);

                // Process each scheduled day
                foreach ($scheduleMap as $date => $shift) {
                    if (in_array($date, $holidays)) {
                        continue; // Skip holidays
                    }

                    $firstIn = $this->getFirstInLog($userId, $date);
                    $lastOut = $this->getLastOutLog($userId, $date);

                    // Check for absence if no IN and OUT logs
                    if (!$firstIn || !$lastOut) {
                        if (!in_array($date, $holidays)) {
                            $totalAbsentDays++;
                        }
                        continue; // Skip processing for missing IN/OUT logs
                    }

                    // Process the logs and compute lateness and undertime
                    list($lateMinutes, $undertimeMinutes) = $this->processLogs(
                        $firstIn, $lastOut, $shift, $date
                    );

                    $totalLateMinutes += $lateMinutes;
                    $totalUndertimeMinutes += $undertimeMinutes;
                }

                // Handle no-schedule days and absent
                $noScheduleDays = $this->getNoScheduleDays($scheduleMap, $workdays, $holidays);
                $totalAbsentDays += count($noScheduleDays);

                // Process leave types (earning & deductions)
                $this->processLeaveCredits($userId, $previousMonth, $totalLateMinutes, $totalUndertimeMinutes, $totalAbsentDays);
            }

            return response()->json(['message' => 'Leave credit balances updated successfully for ' . $previousMonth->format('F Y')]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function getEmployeePlottedSchedules($userId, $month, $year)
    {
        return DB::table('hris.schedules as s')
            ->join('hris.schedule_master_lists as sml', 's.schedule_id', '=', 'sml.id')
            ->where('s.employee_id', $userId)
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

    private function getFirstInLog($userId, $date)
    {
        return DB::table('hris.dtr_logs')
            ->where('userid', $userId)
            ->whereDate('created_at', $date)
            ->where('status', 'IN')
            ->orderBy('created_at', 'asc')
            ->value('created_at');
    }

    private function getLastOutLog($userId, $date)
    {
        return DB::table('hris.dtr_logs')
            ->where('userid', $userId)
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

    private function processLeaveCredits($userId, $previousMonth, $totalLateMinutes, $totalUndertimeMinutes, $totalAbsentDays)
    {
        //GET POINTS EARNED BASED ON LWOP
        $monthly_points_earned = DB::table('monthly_earned_points')
            ->where('lwop', '=', $totalAbsentDays)
            ->first();

        //GET ALL LEAVES THAT ARE ALLOWED TO EARNED POINTS
        $leaveTypes = DB::table('hris.leave_types')
            ->where('monthly_points_earned', '>', 0)
            ->get();

        // Total minutes to deduct
        $totalMinutes = $totalLateMinutes + $totalUndertimeMinutes;

        // Load minute_conversions only once
        $minuteConversions = DB::table('minute_conversions')
            ->pluck('points_deduction', 'minute');

        // Calculate minute-based deduction
        $fullHours = intdiv($totalMinutes, 60);
        $remainingMinutes = $totalMinutes % 60;

        $deductionFor60 = $minuteConversions[60] ?? 0;
        $deductionForRemaining = $remainingMinutes > 0 ? ($minuteConversions[$remainingMinutes] ?? 0) : 0;

        $lateUndertimeDeductions = ($deductionFor60 * $fullHours) + $deductionForRemaining;

        foreach ($leaveTypes as $leaveType) {
            $dayDeduction = $leaveType->day_deduction ?? 0;

            // Total deductions
            $absentDeductions = $totalAbsentDays * $dayDeduction;
            $totalDeduction = $lateUndertimeDeductions + $absentDeductions;

            // Earned credit after deduction
            $earnedCredit = max(0, $monthly_points_earned->points - $totalDeduction);

            // Check if already processed
            $alreadyProcessed = DB::table('hris.leave_credits')
                ->where('user_id', $userId)
                ->where('leave_id', $leaveType->id)
                ->where('last_updated_month', $previousMonth->format('Y-m'))
                ->exists();

            if (!$alreadyProcessed) {
                $credit = DB::table('hris.leave_credits')
                    ->where('user_id', $userId)
                    ->where('leave_id', $leaveType->id)
                    ->first();

                if ($credit) {
                    DB::table('hris.leave_credits')
                        ->where('id', $credit->id)
                        ->update([
                            'based_credit' => $credit->based_credit + $earnedCredit,
                            'last_updated_month' => $previousMonth->format('Y-m')
                        ]);
                } else {
                    DB::table('hris.leave_credits')->insert([
                        'user_id' => $userId,
                        'leave_id' => $leaveType->id,
                        'based_credit' => $earnedCredit,
                        'last_updated_month' => $previousMonth->format('Y-m')
                    ]);
                }

                DB::table('credit_earned_logs')->insert([
                    'user_id' => $userId,
                    'month' => $previousMonth->month,
                    'year' => $previousMonth->year,
                    'total_late_minutes' => $totalLateMinutes,
                    'total_undertime_minutes' => $totalUndertimeMinutes,
                    'leave_type_id' => $leaveType->id,
                    'earned_points' => $monthly_points_earned->points,
                    'deducted_points' => $totalDeduction,
                    'total_absent' => $totalAbsentDays,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
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

}
