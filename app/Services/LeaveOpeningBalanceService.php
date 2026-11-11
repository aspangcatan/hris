<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Certified opening balances: the starting point of the leave ledger.
 *
 * Credits are charged when leave is filed, but the ledger counts leave by its
 * leave date. A balance read today has already paid for leave dated after the
 * as-of date, so those charges are added back to get the true balance as of
 * that date, then recorded on their own leave dates.
 */
class LeaveOpeningBalanceService
{
    private LeaveLedgerService $ledger;

    public function __construct(LeaveLedgerService $ledger)
    {
        $this->ledger = $ledger;
    }

    /** Every employee with a leave balance, and what certifying them on $asOf would record. */
    public function preview(string $asOf, ?array $employeeIds = null): Collection
    {
        $query = DB::table('hris.leave_credits as lc')
            ->join('tdh_user.users as u', 'u.id', '=', 'lc.user_id')
            ->leftJoin('hris.leave_types as lt', 'lt.id', '=', 'lc.leave_id')
            ->orderBy('u.lname')->orderBy('u.fname')
            ->select('lc.user_id', 'lc.leave_id', 'lc.based_credit', 'lc.used_credit', 'lt.code', 'u.fname', 'u.mname', 'u.lname', 'u.suffix');
        if ($employeeIds !== null) {
            $query->whereIn('lc.user_id', $employeeIds);
        }
        $credits = $query->get()->groupBy('user_id');
        $ids = $credits->keys()->all();

        $opened = DB::table('hris.leave_credit_ledger')
            ->whereIn('employee_id', $ids)->where('transaction_type', LeaveLedgerService::OPENING)
            ->get(['employee_id', 'leave_type_id', 'effective_date'])
            ->groupBy('employee_id');
        $filedAhead = $this->datesAfter($ids, $asOf)->groupBy('user_id');
        $lastPosted = DB::table('hris.credit_earned_logs')
            ->whereIn('user_id', $ids)
            ->groupBy('user_id')
            ->selectRaw('user_id, MAX(year * 100 + month) AS period')
            ->pluck('period', 'user_id');

        return $credits->map(function (Collection $rows, $employeeId) use ($opened, $filedAhead, $lastPosted, $asOf) {
            $openedTypes = $opened->get($employeeId, collect())->pluck('leave_type_id')->map(fn($id) => (int) $id)->all();
            $ahead = LeaveDateCharges::byType($filedAhead->get($employeeId, collect()));

            $balances = $rows->map(function ($row) use ($ahead, $openedTypes) {
                $current = round((float) $row->based_credit - (float) $row->used_credit, 3);
                $filed = (float) ($ahead[(int) $row->leave_id] ?? 0);

                return [
                    'leave_type_id' => (int) $row->leave_id,
                    'code' => $row->code,
                    'current' => $current,
                    'filed_ahead' => $filed,
                    'opening' => round($current + $filed, 3),
                    'certified' => in_array((int) $row->leave_id, $openedTypes, true),
                ];
            })->values();

            $status = 'ready';
            $reason = null;
            if ($balances->every(fn($b) => $b['certified'])) {
                $status = 'certified';
            } elseif ($lastPosted->has($employeeId)) {
                $period = (int) $lastPosted[$employeeId];
                $monthEnd = Carbon::create(intdiv($period, 100), $period % 100, 1)->endOfMonth();
                if ($monthEnd->toDateString() > $asOf) {
                    $status = 'blocked';
                    $reason = 'Credits for ' . $monthEnd->format('F Y') . ' are already posted. Use an as-of date on or after ' . $monthEnd->format('M j, Y') . '.';
                }
            }

            $first = $rows->first();

            return [
                'employee_id' => (int) $employeeId,
                'name' => $this->formatName($first),
                'status' => $status,
                'reason' => $reason,
                'certified_on' => $opened->get($employeeId, collect())->max('effective_date'),
                'balances' => $balances->all(),
            ];
        })->values();
    }

    /** @return array{certified: int, skipped: int} */
    public function certify(string $asOf, array $employeeIds, int $certifiedBy): array
    {
        return DB::transaction(function () use ($asOf, $employeeIds, $certifiedBy) {
            DB::table('hris.leave_credits')->whereIn('user_id', $employeeIds)->lockForUpdate()->get(['id']);

            $rows = $this->preview($asOf, $employeeIds);
            $filedAhead = $this->datesAfter($employeeIds, $asOf)->groupBy('user_id');
            $certified = 0;
            $skipped = 0;

            foreach ($rows as $row) {
                if ($row['status'] !== 'ready') {
                    $skipped++;
                    continue;
                }

                $types = [];
                foreach ($row['balances'] as $balance) {
                    if ($balance['certified']) {
                        continue;
                    }
                    $types[] = $balance['leave_type_id'];
                    $this->insertOpening($row['employee_id'], $balance, $asOf, $certifiedBy);
                }
                $this->ledger->recordLeaveDates($row['employee_id'], $filedAhead->get($row['employee_id'], collect()), LeaveLedgerService::CONSUMED, $types);
                $certified++;
            }

            return ['certified' => $certified, 'skipped' => $skipped];
        });
    }

    /**
     * Checks each certified balance: opening + later entries must equal leave_credits.
     *
     * @return array{checked: int, matching: int, differences: array}
     */
    public function reconcile(): array
    {
        $openings = DB::table('hris.leave_credit_ledger as l')
            ->join('tdh_user.users as u', 'u.id', '=', 'l.employee_id')
            ->leftJoin('hris.leave_types as lt', 'lt.id', '=', 'l.leave_type_id')
            ->where('l.transaction_type', LeaveLedgerService::OPENING)
            ->get(['l.id', 'l.employee_id', 'l.leave_type_id', 'l.ending_balance', 'lt.code', 'u.fname', 'u.mname', 'u.lname', 'u.suffix']);
        if ($openings->isEmpty()) {
            return ['checked' => 0, 'matching' => 0, 'differences' => []];
        }

        $entries = DB::table('hris.leave_credit_ledger')
            ->where('id', '>', $openings->min('id'))
            ->where('transaction_type', '<>', LeaveLedgerService::OPENING)
            ->get(['id', 'employee_id', 'leave_type_id', 'earned_credits', 'consumed_credits', 'adjustments'])
            ->groupBy(fn($e) => $e->employee_id . ':' . $e->leave_type_id);

        $differences = [];
        foreach ($openings as $opening) {
            $later = $entries->get($opening->employee_id . ':' . $opening->leave_type_id, collect())
                ->filter(fn($e) => $e->id > $opening->id);
            $expected = round((float) $opening->ending_balance
                + $later->sum(fn($e) => (float) $e->earned_credits - (float) $e->consumed_credits + (float) $e->adjustments), 3);
            $actual = $this->ledger->balance((int) $opening->employee_id, (int) $opening->leave_type_id);

            if (abs($expected - $actual) > 0.0005) {
                $differences[] = [
                    'employee' => $this->formatName($opening),
                    'code' => $opening->code,
                    'ledger' => $expected,
                    'balance' => $actual,
                    'difference' => round($actual - $expected, 3),
                ];
            }
        }

        return ['checked' => $openings->count(), 'matching' => $openings->count() - count($differences), 'differences' => $differences];
    }

    /** Filed leave dated after $asOf that still holds a charge. */
    private function datesAfter(array $employeeIds, string $asOf): Collection
    {
        return DB::table('hris.leave_date_applied as lda')
            ->join('hris.leave_applications as la', 'la.id', '=', 'lda.leave_application_id')
            ->whereIn('la.user_id', $employeeIds)
            ->whereRaw('LOWER(la.status) <> ?', ['rejected'])
            ->where('lda.status', 'active')
            ->where('lda.final_disapproved', 0)
            ->where('lda.date_applied', '>', $asOf)
            ->get(array_merge(['la.user_id'], array_map(fn($column) => 'lda.' . $column, LeaveDateCharges::COLUMNS)));
    }

    private function insertOpening(int $employeeId, array $balance, string $asOf, int $certifiedBy): void
    {
        $now = now();
        DB::table('hris.leave_credit_ledger')->insert([
            'employee_id' => $employeeId,
            'leave_type_id' => $balance['leave_type_id'],
            'transaction_type' => LeaveLedgerService::OPENING,
            'source_type' => 'opening_certification',
            'beginning_balance' => $balance['opening'],
            'ending_balance' => $balance['opening'],
            'computation_reference' => "Balance {$balance['current']} + filed ahead {$balance['filed_ahead']}",
            'effective_date' => $asOf,
            'transaction_date' => $now,
            'finalized_by' => $certifiedBy,
            'finalized_at' => $now,
            'created_at' => $now,
        ]);
    }

    private function formatName(object $row): string
    {
        $middle = !empty($row->mname) ? ' ' . mb_substr($row->mname, 0, 1) . '.' : '';
        $suffix = !empty($row->suffix) ? ' ' . $row->suffix : '';

        return strtoupper(trim(($row->lname ?? '') . ', ' . ($row->fname ?? '') . $middle . $suffix));
    }
}
