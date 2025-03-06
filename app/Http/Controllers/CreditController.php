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
            $shift_start = "08:00:00";
            $shift_end = "17:00:00";
            $previousMonth = Carbon::now()->subMonth();
            $month = $previousMonth->month;
            $year = $previousMonth->year;

            // Get all workdays for the month (weekdays only)
            $workdays = $this->getWorkDays($year, $month);
            $holidays = []; // You can enhance this to fetch from DB if needed
            $workdays = array_diff($workdays, $holidays);

            // Get all employees with logs for the month
            $employees = DB::table('hris.dtr_logs')
                ->select('userid')
                ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$previousMonth->format('Y-m')])
                ->groupBy('userid')
                ->get();

            foreach ($employees as $employee) {
                $totalLateMinutes = 0;
                $totalUndertimeMinutes = 0;

                // All days with logs (actual workdays for this employee)
                $loggedDays = DB::table('hris.dtr_logs')
                    ->where('userid', $employee->userid)
                    ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$previousMonth->format('Y-m')])
                    ->select(DB::raw('DATE(created_at) as work_date'))
                    ->distinct()
                    ->pluck('work_date')
                    ->toArray();

                $hoDays = []; // Add logic for holidays/official business if needed
                $presentOrExcusedDays = array_unique(array_merge($loggedDays, $hoDays));
                $absentDays = array_diff($workdays, $presentOrExcusedDays);
                $totalAbsents = count($absentDays);

                // Calculate total late and undertime minutes
                foreach ($loggedDays as $work_date) {
                    $first_in = DB::table('hris.dtr_logs')
                        ->where('userid', $employee->userid)
                        ->whereRaw('DATE(created_at) = ?', [$work_date])
                        ->where('status', 'IN')
                        ->orderBy('created_at', 'asc')
                        ->value('created_at');

                    $last_out = DB::table('hris.dtr_logs')
                        ->where('userid', $employee->userid)
                        ->whereRaw('DATE(created_at) = ?', [$work_date])
                        ->where('status', 'OUT')
                        ->orderBy('created_at', 'desc')
                        ->value('created_at');

                    $first_in_time = $first_in ? Carbon::parse($first_in) : null;
                    $last_out_time = $last_out ? Carbon::parse($last_out) : null;

                    $expected_in_time = Carbon::parse("$work_date $shift_start");
                    if ($first_in_time->greaterThan($expected_in_time)) {
                        $late_minutes = $expected_in_time->diffInMinutes($first_in_time);
                        $totalLateMinutes += $late_minutes;
                    }


                    $expected_out_time = Carbon::parse("$work_date $shift_end");
                    if ($last_out_time->lessThan($expected_out_time)) {
                        $undertime_minutes = $last_out_time->diffInMinutes($expected_out_time);
                        $totalUndertimeMinutes += $undertime_minutes;
                    }
                }

                // Process leave types
                $leaveTypes = DB::table('hris.leave_types')
                    ->where('monthly_points_earned', '>', 0)
                    ->get();

                foreach ($leaveTypes as $leaveType) {
                    $minute_deduction = $leaveType->minute_deduction ?? 0;
                    $day_deduction = $leaveType->day_deduction ?? 0; // Get day_deduction from hris.leave_types

                    // Late and undertime deductions (use normal multiplication here)
                    $totalLateAndUndertimeDeduction = ($totalLateMinutes + $totalUndertimeMinutes) * $minute_deduction;

                    // Absence deductions (use bcmul for precision)
                    $totalAbsenceDeductions = bcmul($totalAbsents, $day_deduction, 6);

                    // Total deductions
                    $totalDeductedPoints = $totalLateAndUndertimeDeduction + $totalAbsenceDeductions;

                    // Final earned credit (ensure minimum 0)
                    $earned_credit = max(0, $leaveType->monthly_points_earned - $totalDeductedPoints);

                    // Check if already processed
                    $alreadyProcessed = DB::table('hris.leave_credits')
                        ->where('user_id', $employee->userid)
                        ->where('leave_id', $leaveType->id)
                        ->where('last_updated_month', $previousMonth->format('Y-m'))
                        ->exists();

                    if (!$alreadyProcessed) {
                        // Update or insert leave credits
                        $creditRecord = DB::table('hris.leave_credits')
                            ->where('user_id', $employee->userid)
                            ->where('leave_id', $leaveType->id)
                            ->first();

                        if ($creditRecord) {
                            DB::table('hris.leave_credits')
                                ->where('id', $creditRecord->id)
                                ->update([
                                    'based_credit' => $creditRecord->based_credit + $earned_credit,
                                    'last_updated_month' => $previousMonth->format('Y-m')
                                ]);
                        } else {
                            DB::table('hris.leave_credits')->insert([
                                'user_id' => $employee->userid,
                                'leave_id' => $leaveType->id,
                                'based_credit' => $earned_credit,
                                'used_credit' => 0,
                                'last_updated_month' => $previousMonth->format('Y-m')
                            ]);
                        }

                        // Log earned points and deducted points
                        $alreadyLogged = DB::table('credit_earned_logs')
                            ->where('user_id', $employee->userid)
                            ->where('leave_type_id', $leaveType->id)
                            ->where('month', $month)
                            ->where('year', $year)
                            ->exists();

                        if (!$alreadyLogged) {
                            DB::table('credit_earned_logs')->insert([
                                'user_id' => $employee->userid,
                                'month' => $month,
                                'year' => $year,
                                'total_late_minutes' => $totalLateMinutes,
                                'total_undertime_minutes' => $totalUndertimeMinutes,
                                'leave_type_id' => $leaveType->id,
                                'earned_points' => $leaveType->monthly_points_earned,
                                'deducted_points' => $totalDeductedPoints,
                                'total_absent' => $totalAbsents,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
                Log::info("Employee {$employee->userid} - $work_date - First In: " . ($first_in_time ?? 'None') . " - Last Out: " . ($last_out_time ?? 'None'));
            }

            return response()->json(['message' => 'Leave credit balances updated successfully for ' . $previousMonth->format('F Y')]);

        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
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
