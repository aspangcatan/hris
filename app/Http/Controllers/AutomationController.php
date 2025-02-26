<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AutomationController extends Controller
{
    public function updateCreditBalance()
    {
        try {
            $shift_start = "08:00:00";
            $shift_end = "17:00:00";
            $total_shift_minutes = 480; // 8 hours

            $currentMonth = Carbon::now()->format('Y-m'); // Format: YYYY-MM (e.g., 2025-02)

            // Get all employees with logs for the current month
            $employees = DB::table('hris.dtr_logs')
                ->select('userid', DB::raw('DATE(created_at) as work_date'))
                ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$currentMonth]) // Only process the current month
                ->groupBy('userid', 'work_date')
                ->get();

            foreach ($employees as $employee) {
                $work_date = $employee->work_date;

                // Fetch employee's first IN and last OUT for the work date
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

                $late_minutes = 0;
                $undertime_minutes = 0;

                if ($first_in_time) {
                    $expected_in_time = Carbon::parse($work_date . " " . $shift_start);
                    $late_minutes = max(0, $first_in_time->diffInMinutes($expected_in_time, false));
                }

                if ($last_out_time) {
                    $expected_out_time = Carbon::parse($work_date . " " . $shift_end);
                    $undertime_minutes = max(0, $expected_out_time->diffInMinutes($last_out_time, false));
                }

                $total_deducted_minutes = $late_minutes + $undertime_minutes;
                $deduction_percentage = $total_deducted_minutes / $total_shift_minutes;

                $leaveTypes = DB::table('hris.leave_types')->get();

                foreach ($leaveTypes as $leaveType) {
                    $earned_credit = $leaveType->daily_points_earned * (1 - $deduction_percentage);
                    $earned_credit = max(0, $earned_credit);

                    // Check if already updated for the current month
                    $existingCredit = DB::table('hris.leave_credits')
                        ->where('user_id', $employee->userid)
                        ->where('leave_id', $leaveType->id)
                        ->where('last_updated_month', $currentMonth) // Prevent reprocessing
                        ->first();

                    if (!$existingCredit) {
                        // Update or insert leave credits only for this month
                        $creditRecord = DB::table('hris.leave_credits')
                            ->where('user_id', $employee->userid)
                            ->where('leave_id', $leaveType->id)
                            ->first();

                        if ($creditRecord) {
                            DB::table('hris.leave_credits')
                                ->where('id', $creditRecord->id)
                                ->update([
                                    'based_credit' => $creditRecord->based_credit + $earned_credit,
                                    'last_updated_month' => $currentMonth
                                ]);
                        } else {
                            DB::table('hris.leave_credits')->insert([
                                'user_id' => $employee->userid,
                                'leave_id' => $leaveType->id,
                                'based_credit' => $earned_credit,
                                'used_credit' => 0,
                                'last_updated_month' => $currentMonth
                            ]);
                        }
                    }
                }
            }
            return response()->json(['message' => 'Leave credit balances updated successfully for ' . $currentMonth]);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }
}
