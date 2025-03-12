<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
                ->get(['u.fname', 'u.mname', 'u.lname', 'u.suffix', 'l.month', 'l.year', 't.description','l.total_absent', 'l.total_late_minutes', 'l.total_undertime_minutes', 'l.earned_points','l.deducted_points']);
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

            // Get all workdays (you can add holidays filter here if needed)
            $workdays = $this->getWorkDays($year, $month);

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
                $plottedSchedules = DB::table('hris.schedules as s')
                    ->join('hris.schedule_master_lists as sml', 's.schedule_id', '=', 'sml.id')
                    ->where('s.employee_id', $userId)
                    ->whereMonth('s.schedule_date', $month)
                    ->whereYear('s.schedule_date', $year)
                    ->select('s.schedule_date', 'sml.time_in', 'sml.time_out')
                    ->get();

                $scheduleMap = [];
                foreach ($plottedSchedules as $schedule) {
                    $scheduleMap[$schedule->schedule_date] = [
                        'time_in' => $schedule->time_in,
                        'time_out' => $schedule->time_out
                    ];
                }

                // Check each plotted schedule date — was it attended?
                foreach ($scheduleMap as $date => $shift) {
                    $firstIn = DB::table('hris.dtr_logs')
                        ->where('userid', $userId)
                        ->whereDate('created_at', $date)
                        ->where('status', 'IN')
                        ->orderBy('created_at', 'asc')
                        ->value('created_at');

                    $lastOut = DB::table('hris.dtr_logs')
                        ->where('userid', $userId)
                        ->whereDate('created_at', $date)
                        ->where('status', 'OUT')
                        ->orderBy('created_at', 'desc')
                        ->value('created_at');

                    if (!$firstIn && !$lastOut) {
                        // No logs at all for a scheduled day — ABSENT
                        $totalAbsentDays++;
                        continue;
                    }

                    $expectedIn = Carbon::parse("$date {$shift['time_in']}")->startOfMinute();
                    $expectedOut = Carbon::parse("$date {$shift['time_out']}")->startOfMinute();

                    if ($firstIn) {
                        $actualIn = Carbon::parse($firstIn)->startOfMinute();

                        if ($actualIn->greaterThan($expectedIn)) {
                            $lateMinutes = $expectedIn->diffInMinutes($actualIn); // Minutes only, no seconds
                            $totalLateMinutes += $lateMinutes;
                        }
                    }

                    if ($lastOut) {
                        $actualOut = Carbon::parse($lastOut)->startOfMinute();

                        if ($actualOut->lessThan($expectedOut)) {
                            $undertimeMinutes = $actualOut->diffInMinutes($expectedOut); // Minutes only, no seconds
                            $totalUndertimeMinutes += $undertimeMinutes;
                        }
                    }
                }

                // No-schedule days are auto-absent (workdays without plotted schedules)
                $plottedDays = array_keys($scheduleMap);
                $noScheduleDays = array_diff($workdays, $plottedDays);
                $totalAbsentDays += count($noScheduleDays); // Automatically absent for days with no plotted schedule

                // Process leave types (earning & deductions)
                $leaveTypes = DB::table('hris.leave_types')
                    ->where('monthly_points_earned', '>', 0)
                    ->get();

                foreach ($leaveTypes as $leaveType) {
                    $minuteDeduction = $leaveType->minute_deduction ?? 0;
                    $dayDeduction = $leaveType->day_deduction ?? 0;

                    // Total deductions
                    $lateUndertimeDeductions = ($totalLateMinutes + $totalUndertimeMinutes) * $minuteDeduction;
                    $absentDeductions = $totalAbsentDays * $dayDeduction;
                    $totalDeduction = $lateUndertimeDeductions + $absentDeductions;

                    // Earned credit after deduction
                    $earnedCredit = max(0, $leaveType->monthly_points_earned - $totalDeduction);

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
                                'used_credit' => 0,
                                'last_updated_month' => $previousMonth->format('Y-m')
                            ]);
                        }

                        DB::table('credit_earned_logs')->insert([
                            'user_id' => $userId,
                            'month' => $month,
                            'year' => $year,
                            'total_late_minutes' => $totalLateMinutes,
                            'total_undertime_minutes' => $totalUndertimeMinutes,
                            'leave_type_id' => $leaveType->id,
                            'earned_points' => $leaveType->monthly_points_earned,
                            'deducted_points' => $totalDeduction,
                            'total_absent' => $totalAbsentDays,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            return response()->json(['message' => 'Leave credit balances updated successfully for ' . $previousMonth->format('F Y')]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Get all workdays (weekdays) in a given month and year.
     */
    function getWorkDays($year, $month)
    {
        $startDate = Carbon::createFromDate($year, $month, 1);
        $endDate = $startDate->copy()->endOfMonth();
        $workdays = [];

        while ($startDate <= $endDate) {
            if ($startDate->isWeekday()) { // Monday to Friday
                $workdays[] = $startDate->toDateString();
            }
            $startDate->addDay();
        }

        return $workdays;
    }

}
