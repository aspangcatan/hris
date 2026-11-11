<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Late changes (client decision 2026-11-11): leave approved, cancelled or
 * disapproved after a cutoff was finalized never edits that cutoff. The day is
 * worked out again from the live records, compared with what the cutoff saved,
 * and the difference becomes an adjustment carried into the employee's next
 * cutoff: a pay line in its snapshot, and a leave credit entry when it is
 * finalized.
 *
 * Status: pending (found) -> included (in a later cutoff's snapshot) ->
 * applied (that cutoff is finalized).
 */
class CutoffAdjustmentService
{
    public const PAY = 'pay';
    public const CREDITS = 'credits';
    public const REFUND_CODE = 'PRIOR_PERIOD_REFUND';
    public const CHARGE_CODE = 'PRIOR_PERIOD_CHARGE';

    private DTRServices $dtrServices;
    private PayrollServices $payrollServices;
    private LeaveCreditService $leaveCreditService;
    private LeaveLedgerService $ledger;

    public function __construct(DTRServices $dtrServices, PayrollServices $payrollServices, LeaveCreditService $leaveCreditService, LeaveLedgerService $ledger)
    {
        $this->dtrServices = $dtrServices;
        $this->payrollServices = $payrollServices;
        $this->leaveCreditService = $leaveCreditService;
        $this->ledger = $ledger;
    }

    /** Finds late changes in a finalized or closed cutoff and records what is owed. Safe to run any number of times. */
    public function detect(int $sourceCutoffId, ?array $onlyEmployees = null): void
    {
        $master = DB::table('hris.cutoff_summaries_master')->where('id', $sourceCutoffId)->first();
        if (!$master || !in_array($master->status, [CutoffLifecycleService::FINALIZED, CutoffLifecycleService::CLOSED], true)) {
            return;
        }
        $since = (string) ($master->finalized_at ?? $master->updated_at);
        $start = (string) $master->period_start;
        $end = (string) $master->period_end;

        $employees = DB::table('hris.cutoff_summaries')->where('cutoff_id', $sourceCutoffId)->pluck('employee_id')->map(fn($id) => (int) $id);
        if ($onlyEmployees !== null) {
            $employees = $employees->intersect($onlyEmployees);
        }
        if ($employees->isEmpty()) {
            return;
        }

        $candidates = $this->changedDates($employees->all(), $start, $end, $since);
        if ($candidates->isEmpty()) {
            return;
        }

        $snapshots = DB::table('hris.cutoff_employee_snapshots')->where('cutoff_id', $sourceCutoffId)->get()->keyBy('employee_id');
        $saved = DB::table('hris.cutoff_summary_days')->where('cutoff_id', $sourceCutoffId)
            ->whereIn('employee_id', $candidates->pluck('user_id')->unique())
            ->get()->keyBy(fn($d) => $d->employee_id . '|' . $d->date);
        $pointsTable = json_decode((string) DB::table('hris.cutoff_config_snapshots')
            ->where(['cutoff_id' => $sourceCutoffId, 'config_key' => 'table_iii'])->value('payload'), true) ?: null;

        foreach ($candidates->groupBy('user_id') as $employeeId => $dates) {
            $employeeId = (int) $employeeId;
            $snapshot = $snapshots->get($employeeId);
            $lwopDelta = 0.0;

            foreach ($dates->pluck('date')->unique() as $date) {
                $before = $saved->get($employeeId . '|' . $date);
                if (!$before) {
                    continue;
                }
                $now = $this->dtrServices->getDailyBreakdown($employeeId, $date, $date)->get($date);
                $nowLwop = (float) ($this->dtrServices->getLwopDaysByRange($employeeId, $date, $date)[$date]['days'] ?? 0);
                if (!$now) {
                    continue;
                }

                $required = (int) $before->required_minutes ?: (int) round((float) $this->payrollServices->defaultComputation()->standard_hours_per_day * 60);
                $rate = $this->minuteRate($before, $snapshot, $employeeId);
                $was = $this->chargedMinutes((bool) $before->is_absent, (float) $before->lwop_fraction, $required,
                    (int) $before->late_minutes + (int) $before->undertime_minutes + (int) $before->pass_slip_minutes);
                $is = $this->chargedMinutes((bool) $now['is_absent'], $nowLwop, $required,
                    (int) $now['late_minutes'] + (int) $now['undertime_minutes'] + (int) ($now['pass_slip_minutes'] ?? 0));

                $this->owe($sourceCutoffId, $employeeId, self::PAY, $date, 'amount', round(($is - $was) * $rate, 2), [
                    'days' => round($nowLwop - (float) $before->lwop_fraction, 3),
                    'reason' => Carbon::parse($date)->format('M j, Y') . ': ' . $this->describe((bool) $before->is_absent, (float) $before->lwop_fraction)
                        . ' → ' . $this->describe((bool) $now['is_absent'], $nowLwop) . ' (changed after finalizing)',
                ]);
                $lwopDelta += $nowLwop - (float) $before->lwop_fraction;
            }

            if ($this->earnsCredits($snapshot, $employeeId)) {
                $monthEnd = Carbon::parse($start)->endOfMonth()->toDateString();
                $monthLwop = (float) DB::table('hris.cutoff_summaries as cs')
                    ->join('hris.cutoff_summaries_master as m', 'm.id', '=', 'cs.cutoff_id')
                    ->where('cs.employee_id', $employeeId)
                    ->whereIn('m.status', [CutoffLifecycleService::FINALIZED, CutoffLifecycleService::CLOSED])
                    ->whereBetween('m.period_start', [Carbon::parse($start)->startOfMonth()->toDateString(), $monthEnd])
                    ->sum('cs.credit_without_pay_days');
                $credits = $this->leaveCreditService->earnedPointsForLwop($monthLwop + $lwopDelta, $pointsTable)
                    - $this->leaveCreditService->earnedPointsForLwop($monthLwop, $pointsTable);

                $this->owe($sourceCutoffId, $employeeId, self::CREDITS, $monthEnd, 'credits', round($credits, 3), [
                    'days' => round($lwopDelta, 3),
                    'reason' => 'Earned VL and SL for ' . Carbon::parse($start)->format('F Y') . ' after LWOP changed by ' . round($lwopDelta, 3) . ' day(s)',
                ]);
            }
        }
    }

    /**
     * While a later cutoff is generated: finds this employee's late changes in
     * earlier cutoffs and includes them as pay lines. Runs inside the snapshot
     * transaction, so a failed generation leaves them pending.
     */
    public function includeIn(int $targetCutoffId, int $employeeId, string $targetStart): array
    {
        $earlier = DB::table('hris.cutoff_summaries as cs')
            ->join('hris.cutoff_summaries_master as m', 'm.id', '=', 'cs.cutoff_id')
            ->where('cs.employee_id', $employeeId)
            ->whereIn('m.status', [CutoffLifecycleService::FINALIZED, CutoffLifecycleService::CLOSED])
            ->where('m.period_end', '<', $targetStart)
            ->pluck('m.id');
        foreach ($earlier as $sourceId) {
            $this->detect((int) $sourceId, [$employeeId]);
        }

        $pending = DB::table('hris.cutoff_adjustments')
            ->where('employee_id', $employeeId)->where('status', 'pending')->whereIn('source_cutoff_id', $earlier);
        (clone $pending)->update(['target_cutoff_id' => $targetCutoffId, 'status' => 'included', 'updated_at' => now()]);

        $included = DB::table('hris.cutoff_adjustments')
            ->where(['target_cutoff_id' => $targetCutoffId, 'employee_id' => $employeeId])
            ->get();
        $pay = $included->where('adjustment_type', self::PAY);
        $refund = -$pay->where('amount', '<', 0)->sum('amount');
        $charge = $pay->where('amount', '>', 0)->sum('amount');

        $now = now();
        foreach ([[self::REFUND_CODE, 'Prior-period refund', 'earning', $refund], [self::CHARGE_CODE, 'Prior-period charge', 'deduction', $charge]] as [$code, $name, $type, $amount]) {
            if ($amount > 0.004) {
                // Taxable / before tax: it corrects basic pay that was taxed (or not) at the time.
                DB::table('hris.cutoff_payroll_components')->insert([
                    'cutoff_id' => $targetCutoffId, 'employee_id' => $employeeId, 'code' => $code, 'name' => $name,
                    'type' => $type, 'taxable' => 1, 'amount' => round($amount, 2),
                    'computation_basis' => $pay->count() . ' late change(s) from earlier cutoffs',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        return $included->all();
    }

    /** Before a cutoff is generated again: what it had included goes back to pending, and its own unclaimed findings are dropped. */
    public function release(int $cutoffId): void
    {
        DB::table('hris.cutoff_adjustments')->where('target_cutoff_id', $cutoffId)->where('status', 'included')
            ->update(['target_cutoff_id' => null, 'status' => 'pending', 'updated_at' => now()]);
        DB::table('hris.cutoff_adjustments')->where('source_cutoff_id', $cutoffId)->where('status', 'pending')->delete();
    }

    /** When the cutoff that includes them is finalized: credits reach the balance and the ledger. */
    public function apply(int $targetCutoffId, int $userId): void
    {
        $rows = DB::table('hris.cutoff_adjustments')->where('target_cutoff_id', $targetCutoffId)->where('status', 'included')->get();
        foreach ($rows->where('adjustment_type', self::CREDITS) as $row) {
            $this->moveCredits($row, (float) $row->credits, 'Late change: ' . $row->reason);
        }
        DB::table('hris.cutoff_adjustments')->whereIn('id', $rows->pluck('id'))
            ->update(['status' => 'applied', 'applied_by' => $userId, 'applied_at' => now(), 'updated_at' => now()]);
    }

    /** When that cutoff is reopened: credits are taken back and the adjustments wait in it again. */
    public function unapply(int $targetCutoffId): void
    {
        $rows = DB::table('hris.cutoff_adjustments')->where('target_cutoff_id', $targetCutoffId)->where('status', 'applied')->get();
        foreach ($rows->where('adjustment_type', self::CREDITS) as $row) {
            $this->moveCredits($row, -(float) $row->credits, 'Reversed: cutoff reopened');
        }
        DB::table('hris.cutoff_adjustments')->whereIn('id', $rows->pluck('id'))
            ->update(['status' => 'included', 'applied_by' => null, 'applied_at' => null, 'updated_at' => now()]);
    }

    /** Late changes found in a cutoff, and those carried into it, with names. */
    public function forCutoff(int $cutoffId): array
    {
        $select = fn(string $column) => DB::table('hris.cutoff_adjustments as a')
            ->leftJoin('tdh_user.users as u', 'u.id', '=', 'a.employee_id')
            ->leftJoin('hris.cutoff_summaries_master as m', 'm.id', '=', $column === 'source_cutoff_id' ? 'a.target_cutoff_id' : 'a.source_cutoff_id')
            ->where("a.{$column}", $cutoffId)
            ->orderBy('u.lname')->orderBy('a.adjustment_date')
            ->get(['a.*', 'u.fname', 'u.lname', 'm.title as other_cutoff']);

        return ['found' => $select('source_cutoff_id'), 'included' => $select('target_cutoff_id')];
    }

    private function changedDates(array $employeeIds, string $start, string $end, string $since): Collection
    {
        $base = fn() => DB::table('hris.leave_date_applied as lda')
            ->join('hris.leave_applications as la', 'la.id', '=', 'lda.leave_application_id')
            ->whereIn('la.user_id', $employeeIds)
            ->whereBetween('lda.date_applied', [$start, $end]);

        $approvals = $base()->where('la.updated_at', '>', $since)->get(['la.user_id', 'lda.date_applied as date']);
        $cancellations = $base()
            ->whereIn('lda.id', DB::table('hris.leave_cancellation_dates as lcd')
                ->join('hris.leave_cancellations as lc', 'lc.id', '=', 'lcd.leave_cancellation_id')
                ->where('lc.updated_at', '>', $since)->select('lcd.leave_date_applied_id'))
            ->get(['la.user_id', 'lda.date_applied as date']);
        $ledger = $base()
            ->whereIn('lda.id', DB::table('hris.leave_credit_ledger')
                ->where('source_type', LeaveLedgerService::SOURCE_LEAVE_DATE)
                ->where('transaction_date', '>', $since)->select('source_id'))
            ->get(['la.user_id', 'lda.date_applied as date']);

        return $approvals->merge($cancellations)->merge($ledger)
            ->map(fn($row) => (object) ['user_id' => (int) $row->user_id, 'date' => Carbon::parse($row->date)->toDateString()])
            ->unique(fn($row) => $row->user_id . '|' . $row->date)
            ->values();
    }

    /** Records what is still owed for this key beyond what was already carried forward. */
    private function owe(int $sourceCutoffId, int $employeeId, string $type, string $date, string $field, float $needed, array $details): void
    {
        $key = ['source_cutoff_id' => $sourceCutoffId, 'employee_id' => $employeeId, 'adjustment_type' => $type, 'adjustment_date' => $date];
        $carried = (float) DB::table('hris.cutoff_adjustments')->where($key)->where('status', '<>', 'pending')->sum($field);
        $remaining = round($needed - $carried, $field === 'credits' ? 3 : 2);
        $pending = DB::table('hris.cutoff_adjustments')->where($key)->where('status', 'pending');

        if (abs($remaining) < ($field === 'credits' ? 0.0005 : 0.005)) {
            $pending->delete();
            return;
        }
        $values = $details + [$field => $remaining, 'source_type' => 'late_change', 'updated_at' => now()];
        if ((clone $pending)->exists()) {
            $pending->update($values);
        } else {
            DB::table('hris.cutoff_adjustments')->insert($key + $values + ['status' => 'pending', 'created_at' => now()]);
        }
    }

    private function moveCredits(object $row, float $amount, string $reference): void
    {
        if (abs($amount) < 0.0005) {
            return;
        }
        $types = DB::table('hris.leave_types')->where('status', 'active')->where('monthly_earned', 1)->pluck('id');
        foreach ($types as $typeId) {
            $updated = DB::table('hris.leave_credits')->where('user_id', $row->employee_id)->where('leave_id', $typeId)->increment('based_credit', $amount);
            if (!$updated) {
                DB::table('hris.leave_credits')->insert(['user_id' => $row->employee_id, 'leave_id' => $typeId, 'based_credit' => $amount, 'used_credit' => 0]);
            }
            $this->ledger->record((int) $row->employee_id, (int) $typeId, LeaveLedgerService::ADJUSTMENT, $amount,
                (string) $row->adjustment_date, 'cutoff_adjustments', (int) $row->id, $reference);
        }
    }

    private function chargedMinutes(bool $absent, float $lwopFraction, int $required, int $otherMinutes): float
    {
        return ($absent ? $required : 0) + round($lwopFraction * $required) + $otherMinutes;
    }

    private function minuteRate(object $day, ?object $snapshot, int $employeeId): float
    {
        if ($day->minute_rate !== null) {
            return (float) $day->minute_rate;
        }
        if ($snapshot && $snapshot->minute_rate !== null) {
            return (float) $snapshot->minute_rate;
        }
        $basic = (float) DB::table('hris.payroll_settings')->where('userid', $employeeId)->value('basic_salary');

        return $this->payrollServices->payRates($basic)['minute'];
    }

    private function earnsCredits(?object $snapshot, int $employeeId): bool
    {
        if ($snapshot) {
            return (int) $snapshot->credit_eligible === 1;
        }
        $type = DB::table('hris.employees')->where('user_id', $employeeId)->value('employee_type');

        return $type !== 'Job Order' && DB::table('tdh_user.users')->where('id', $employeeId)->value('status') !== 'JO';
    }

    private function describe(bool $absent, float $lwopFraction): string
    {
        if ($lwopFraction >= 1) {
            return 'LWOP';
        }
        if ($lwopFraction > 0) {
            return 'part LWOP';
        }

        return $absent ? 'absent' : 'present or on paid leave';
    }
}
