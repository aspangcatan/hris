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

    /**
     * @param float|null $lwopDays filed and approved LWOP days in the month
     *        (cutoff_summaries.credit_without_pay_days). When given, the credits are
     *        read straight from CSC Table III (monthly_earned_points.lwop) by that
     *        number. Otherwise (cutoffs generated before the column existed) the older
     *        present / work days x 30 scaling is used, read as 30 - present LWOP days.
     */
    public function computeEarnedCredits($totalPresent, $totalLateUndertime, $totalWorkingDays = null, $month = null, $year = null, $lwopDays = null, ?array $pointsTable = null)
    {
        try {
            if ($lwopDays !== null) {
                $lookupLwop = max(0.0, (float) $lwopDays);
            } else {
                // Older cutoffs: days present scaled to a 30-day month, read as LWOP = 30 - present.
                if (!empty($totalWorkingDays)) {
                    $lookupPresent = min(30, (int) round(($totalPresent / $totalWorkingDays) * 30));
                } else {
                    $lookupPresent = $totalPresent > 30 ? 30 : $totalPresent;
                    if ((int) $month === 2 && !empty($year)) {
                        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
                        if ($totalPresent >= $daysInMonth) {
                            $lookupPresent = 30;
                        }
                    }
                }
                $lookupLwop = (float) (30 - $lookupPresent);
            }

            $monthly_points_earned = $this->earnedPointsForLwop($lookupLwop, $pointsTable);
            $lateUndertimeDeductions = $this->minuteConversionService->computeDeduction($totalLateUndertime);

            return [
                'monthly_points_earned' => round($monthly_points_earned, 3),
                'total_deductions' => round($lateUndertimeDeductions, 3),
                'net_points' => round($monthly_points_earned - $lateUndertimeDeductions, 3),
                'total_present' => $totalPresent,
                'total_late_undertime' => $totalLateUndertime,
                'lookup_lwop' => round($lookupLwop, 3),
            ];
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    /**
     * CSC Table III (monthly_earned_points): leave credits earned for a number of
     * days on leave without pay - 0 = 1.250, 0.5 = 1.229 ... 30 = 0.000. A value
     * between two rows (e.g. 0.4 day from 4 hours of a 10-hour shift) is
     * interpolated between them; 30 or more days earn the 30-day row (0.000).
     *
     * @param array|null $pointsTable rows of ['lwop' =>, 'points' =>] saved with a cutoff
     *        snapshot; when null the live table is read (cutoffs without a snapshot).
     */
    public function earnedPointsForLwop(float $lwopDays, ?array $pointsTable = null): float
    {
        $rows = $pointsTable !== null
            ? array_map(fn($r) => [(float) $r['lwop'], (float) $r['points']], $pointsTable)
            : DB::table('monthly_earned_points')->orderBy('lwop')->get(['lwop', 'points'])
                ->map(fn($r) => [(float) $r->lwop, (float) $r->points])
                ->all();
        if (empty($rows)) {
            return 0.0;
        }

        $lwopDays = max(0.0, $lwopDays);
        $previous = null;
        foreach ($rows as [$lwop, $points]) {
            if (abs($lwop - $lwopDays) < 0.0005) {
                return round($points, 3);
            }
            if ($lwop > $lwopDays) {
                if ($previous === null) {
                    return round($points, 3);
                }
                [$lowLwop, $lowPoints] = $previous;

                return round($lowPoints + ($lwopDays - $lowLwop) / ($lwop - $lowLwop) * ($points - $lowPoints), 3);
            }
            $previous = [$lwop, $points];
        }

        return round($previous[1], 3);
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
    /**
     * $meta (optional): ['cutoff_id' => int, 'lwop_days' => float, 'posted_by' => int],
     * stored on each credit_earned_logs row.
     */
    public function updateEarnedCredits($userid, $year, $month, $totalPresent, $totalLateUndertimeMinutes, $earnedPoints, $deductionPoints, array $meta = []): int
    {
        $logMeta = array_intersect_key($meta, array_flip(['cutoff_id', 'lwop_days', 'posted_by']));
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

            DB::transaction(function () use ($userid, $leaveType, $creditedPoints, $date_updated, $month, $year, $totalLateUndertimeMinutes, $typeDeduction, $totalPresent, $logMeta, &$posted) {
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
                    ->first(['id', 'last_updated_month', 'based_credit', 'used_credit']);

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

                $logId = DB::table('credit_earned_logs')->insertGetId(array_merge([
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
                ], $logMeta));

                // The permanent record of this posting; leave_credits must reconcile against it.
                $beginning = $credit ? round((float) $credit->based_credit - (float) $credit->used_credit, 3) : 0.0;
                DB::table('hris.leave_credit_ledger')->insert([
                    'employee_id' => $userid,
                    'leave_type_id' => $leaveType->id,
                    'cutoff_id' => $logMeta['cutoff_id'] ?? null,
                    'transaction_type' => 'earned',
                    'source_type' => LeaveLedgerService::SOURCE_EARNED_POSTING,
                    'source_id' => $logId,
                    'beginning_balance' => $beginning,
                    'earned_credits' => $creditedPoints,
                    'ending_balance' => round($beginning + $creditedPoints, 3),
                    'computation_reference' => isset($logMeta['lwop_days']) ? 'Table III: ' . (float) $logMeta['lwop_days'] . ' LWOP day(s)' : null,
                    'effective_date' => Carbon::create($year, $month, 1)->endOfMonth()->toDateString(),
                    'transaction_date' => now(),
                    'finalized_by' => $logMeta['posted_by'] ?? null,
                    'finalized_at' => now(),
                    'created_at' => now(),
                ]);

                $posted++;
            });
        }

        return $posted;
    }
}
