<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Append-only history of every leave balance change (leave_credit_ledger).
 * leave_credits stays the current balance; the ledger must reconcile with it.
 * Call this right after the balance is written, in the same transaction.
 *
 * Each entry has two dates: effective_date is the date it applies to (the leave
 * date, client decision 2026-11-11), transaction_date is when it was recorded.
 */
class LeaveLedgerService
{
    public const OPENING = 'opening';
    public const EARNED = 'earned';
    public const CONSUMED = 'consumed';
    public const RESTORED = 'restored';
    public const MONETIZED = 'monetized';
    public const ADJUSTMENT = 'adjustment';

    public const SOURCE_LEAVE_DATE = 'leave_date_applied';
    public const SOURCE_EARNED_POSTING = 'credit_earned_logs';

    public function balance(int $employeeId, int $leaveTypeId): float
    {
        $credit = DB::table('hris.leave_credits')
            ->where('user_id', $employeeId)->where('leave_id', $leaveTypeId)
            ->first(['based_credit', 'used_credit']);

        return $credit ? round((float) $credit->based_credit - (float) $credit->used_credit, 3) : 0.0;
    }

    /** One balance change that has already been written; $delta is signed. */
    public function record(int $employeeId, int $leaveTypeId, string $type, float $delta, string $effectiveDate, ?string $sourceType = null, ?int $sourceId = null, ?string $reference = null): void
    {
        if (abs($delta) < 0.0005) {
            return;
        }
        $ending = $this->balance($employeeId, $leaveTypeId);
        $this->insert($employeeId, $leaveTypeId, $type, $delta, round($ending - $delta, 3), $effectiveDate, $sourceType, $sourceId, $reference);
    }

    /**
     * One entry per leave date, per balance it touched (SL -> VL fallback included),
     * after the combined amount has been written. $type is CONSUMED or RESTORED.
     *
     * @param iterable<object> $dates leave_date_applied rows with LeaveDateCharges::COLUMNS
     * @param int[]|null $onlyTypes limit to these leave types
     */
    public function recordLeaveDates(int $employeeId, iterable $dates, string $type, ?array $onlyTypes = null): void
    {
        $sign = $type === self::CONSUMED ? -1 : 1;
        $byType = [];
        foreach ($dates as $date) {
            foreach (LeaveDateCharges::byType([$date]) as $typeId => $amount) {
                if ($onlyTypes === null || in_array($typeId, $onlyTypes, true)) {
                    $byType[$typeId][] = ['id' => (int) $date->id, 'date' => (string) $date->date_applied, 'delta' => $sign * $amount];
                }
            }
        }

        foreach ($byType as $typeId => $entries) {
            usort($entries, fn($a, $b) => [$a['date'], $a['id']] <=> [$b['date'], $b['id']]);
            $running = round($this->balance($employeeId, $typeId) - array_sum(array_column($entries, 'delta')), 3);
            foreach ($entries as $entry) {
                $this->insert($employeeId, $typeId, $type, $entry['delta'], $running, $entry['date'], self::SOURCE_LEAVE_DATE, $entry['id'], null);
                $running = round($running + $entry['delta'], 3);
            }
        }
    }

    private function insert(int $employeeId, int $leaveTypeId, string $type, float $delta, float $beginning, string $effectiveDate, ?string $sourceType, ?int $sourceId, ?string $reference): void
    {
        $isConsumption = in_array($type, [self::CONSUMED, self::MONETIZED], true);
        $now = now();

        DB::table('hris.leave_credit_ledger')->insert([
            'employee_id' => $employeeId,
            'leave_type_id' => $leaveTypeId,
            'transaction_type' => $type,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'beginning_balance' => $beginning,
            'earned_credits' => $type === self::EARNED ? $delta : 0,
            'consumed_credits' => $isConsumption ? -$delta : 0,
            'adjustments' => ($type !== self::EARNED && !$isConsumption) ? $delta : 0,
            'ending_balance' => round($beginning + $delta, 3),
            'computation_reference' => $reference,
            'effective_date' => $effectiveDate,
            'transaction_date' => $now,
            'finalized_by' => Auth::id(),
            'finalized_at' => $now,
            'created_at' => $now,
        ]);
    }
}
