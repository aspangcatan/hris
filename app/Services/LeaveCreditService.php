<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class LeaveCreditService
{
    public function computeEarnedCredits($totalPresent, $totalLateUndertime)
    {
        try {
            // Get monthly earned points for given present days
            $lookupPresent = $totalPresent > 30 ? 30 : $totalPresent;

            $monthly_points_earned = DB::table('monthly_earned_points')
                    ->where('present', '=', $lookupPresent)
                    ->value('points') ?? 0;


            // Get conversion rates (minute => deduction)
            $minuteConversions = DB::table('minute_conversions')
                ->pluck('points_deduction', 'minute');

            // Calculate minute-based deduction
            $fullHours = intdiv($totalLateUndertime, 60);
            $remainingMinutes = $totalLateUndertime % 60;

            $deductionFor60 = $minuteConversions[60] ?? 0;
            $deductionForRemaining = $remainingMinutes > 0
                ? ($minuteConversions[$remainingMinutes] ?? 0)
                : 0;

            $lateUndertimeDeductions = ($deductionFor60 * $fullHours) + $deductionForRemaining;

            // Calculate net points after deduction
            $netPoints = $monthly_points_earned - $lateUndertimeDeductions;

            // Format with 3 decimal places
            return [
                'monthly_points_earned' => round($monthly_points_earned, 3),
                'total_deductions' => round($lateUndertimeDeductions, 3),
                'net_points' => round($monthly_points_earned - $lateUndertimeDeductions, 3),
                'total_present' => $totalPresent,
                'total_late_undertime' => $totalLateUndertime
            ];

        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function updateEarnedCredits($userid, $year, $month, $totalPresent, $totalLateUndertimeMinutes, $earnedPoints, $deductionPoints)
    {
        $leaveTypes = DB::table('hris.leave_types')
            ->where('monthly_points_earned', '>', 0)
            ->get();


        $date_updated = $year . '-' . $month;

        foreach ($leaveTypes as $leaveType) {

            $alreadyProcessed = DB::table('hris.leave_credits')
                ->where('user_id', $userid)
                ->where('leave_id', $leaveType->id)
                ->where('last_updated_month', $date_updated)
                ->exists();

            if (!$alreadyProcessed) {
                $credit = DB::table('hris.leave_credits')
                    ->where('user_id', $userid)
                    ->where('leave_id', $leaveType->id)
                    ->first();


                if ($credit) {
                    DB::table('hris.leave_credits')
                        ->where('id', $credit->id)
                        ->update([
                            'based_credit' => $credit->based_credit + $earnedPoints,
                            'last_updated_month' => $date_updated
                        ]);
                } else {
                    DB::table('hris.leave_credits')->insert([
                        'user_id' => $userid,
                        'leave_id' => $leaveType->id,
                        'based_credit' => $earnedPoints,
                        'last_updated_month' => $date_updated
                    ]);
                }

                DB::table('credit_earned_logs')->insert([
                    'user_id' => $userid,
                    'month' => $month,
                    'year' => $year,
                    'total_lateundertime_minutes' => $totalLateUndertimeMinutes,
                    'leave_type_id' => $leaveType->id,
                    'earned_points' => $earnedPoints,
                    'deducted_points' => $deductionPoints,
                    'total_present' => $totalPresent,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
