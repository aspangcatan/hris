<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveCreditService
{
    protected MinuteConversionService $minuteConversionService;

    public function __construct(MinuteConversionService $minuteConversionService)
    {
        $this->minuteConversionService = $minuteConversionService;
    }

    public function computeEarnedCredits($totalPresent, $totalLateUndertime, $totalWorkingDays = null, $month = null, $year = null)
    {
        try {
            // Get monthly earned points for given present days
            if (!empty($totalWorkingDays)) {
                // Scale actual attendance to a 30-day equivalent so full
                // attendance always earns the full lookup-table maximum,
                // regardless of how many working days the real month had.
                $lookupPresent = (int) round(($totalPresent / $totalWorkingDays) * 30);
                $lookupPresent = min(30, $lookupPresent);
            } else {
                $lookupPresent = $totalPresent > 30 ? 30 : $totalPresent;
                if ((int) $month === 2 && !empty($year)) {
                    $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
                    if ($totalPresent >= $daysInMonth) {
                        $lookupPresent = 30;
                    }
                }
            }

            $monthly_points_earned = DB::table('monthly_earned_points')
                    ->where('present', '=', $lookupPresent)
                    ->value('points') ?? 0;

            $lateUndertimeDeductions = $this->minuteConversionService->computeDeduction($totalLateUndertime);

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

    /**
     * Employee ids (from $userIds) that already have earned credits posted for
     * this month/year - for any monthly-earned leave type.
     */
    public function alreadyCreditedUserIds(array $userIds, int $year, int $month): array
    {
        if (empty($userIds)) {
            return [];
        }

        return DB::table('credit_earned_logs')
            ->whereIn('user_id', $userIds)
            ->where('year', $year)
            ->where('month', $month)
            ->distinct()
            ->pluck('user_id')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    /**
     * Posts one month's earned credits to every monthly-earned leave type.
     * Returns how many leave types were actually credited (0 = the month was
     * already credited for this employee, nothing changed).
     */
    public function updateEarnedCredits($userid, $year, $month, $totalPresent, $totalLateUndertimeMinutes, $earnedPoints, $deductionPoints): int
    {
        $leaveTypes = DB::table('hris.leave_types')
            ->where('status', 'active')
            ->where('monthly_earned', 1)
            ->get();


        $date_updated = $year . '-' . $month;
        $posted = 0;

        foreach ($leaveTypes as $leaveType) {
            // Late/undertime is charged only to types flagged deduct_tardiness (VL);
            // the rest (SL) receive the full table amount.
            $typeDeduction = (int) ($leaveType->deduct_tardiness ?? 0) === 1 ? $deductionPoints : 0;
            $creditedPoints = round($earnedPoints - $typeDeduction, 3);

            DB::transaction(function () use ($userid, $leaveType, $creditedPoints, $date_updated, $month, $year, $totalLateUndertimeMinutes, $typeDeduction, $totalPresent, &$posted) {
                // Lock the employee's credit rows (the same rows a leave filing locks), then check
                // "already processed this month?" under that lock - so a double-clicked Apply
                // cannot credit the month twice.
                DB::table('hris.leave_credits')->where('user_id', $userid)->lockForUpdate()->get(['id']);

                // The posting log is the record of every month ever credited.
                // (leave_credits.last_updated_month only holds the LATEST month,
                // so re-applying an older month used to credit it a second time.)
                $alreadyCredited = DB::table('credit_earned_logs')
                    ->where('user_id', $userid)
                    ->where('year', $year)
                    ->where('month', $month)
                    ->where('leave_type_id', $leaveType->id)
                    ->exists();
                if ($alreadyCredited) {
                    return;
                }

                $credit = DB::table('hris.leave_credits')
                    ->where('user_id', $userid)
                    ->where('leave_id', $leaveType->id)
                    ->first(['id', 'last_updated_month']);

                if ($credit && $credit->last_updated_month === $date_updated) {
                    return; // older postings made before every month was logged
                }

                if ($credit) {
                    // increment, not "old balance + points": a balance read earlier would
                    // wipe out any leave deduction made in between.
                    DB::table('hris.leave_credits')
                        ->where('id', $credit->id)
                        ->increment('based_credit', $creditedPoints, ['last_updated_month' => $date_updated]);
                } else {
                    DB::table('hris.leave_credits')->insert([
                        'user_id' => $userid,
                        'leave_id' => $leaveType->id,
                        'based_credit' => $creditedPoints,
                        'last_updated_month' => $date_updated
                    ]);
                }

                DB::table('credit_earned_logs')->insert([
                    'user_id' => $userid,
                    'month' => $month,
                    'year' => $year,
                    'total_lateundertime_minutes' => $totalLateUndertimeMinutes,
                    'leave_type_id' => $leaveType->id,
                    'earned_points' => $creditedPoints,
                    'deducted_points' => $typeDeduction,
                    'total_present' => $totalPresent,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $posted++;
            });
        }

        return $posted;
    }
}
