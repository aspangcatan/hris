<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cutoff lifecycle: draft -> [processing -> for_review ->] finalized -> closed.
 * The bracketed steps apply when snapshots are on (config hris.cutoff_snapshots_enabled).
 * Draft cutoffs can be regenerated; Finalize runs a pre-check, locks every row
 * and records who did it; Reopen sends a finalized cutoff back to draft with a
 * reason, only while nothing has used it yet.
 */
class CutoffLifecycleService
{
    public const DRAFT = 'draft';
    public const PROCESSING = 'processing';
    public const FOR_REVIEW = 'for_review';
    public const FINALIZED = 'finalized';
    public const CLOSED = 'closed';

    /** A processing cutoff older than this was interrupted and can be generated again. */
    private const STALE_PROCESSING_MINUTES = 30;

    private PayrollServices $payrollServices;

    public function __construct(PayrollServices $payrollServices)
    {
        $this->payrollServices = $payrollServices;
    }

    public function find(int $cutoffId): ?object
    {
        return DB::table($this->payrollServices->getCutoffSummaryMasterTableName())->where('id', $cutoffId)->first();
    }

    public function statusOf(?object $master): string
    {
        return strtolower((string) ($master->status ?? self::DRAFT)) ?: self::DRAFT;
    }

    /**
     * What HR should look at before finalizing. Nothing here blocks Finalize -
     * after finalizing, unfiled absences become pay deductions and late leave
     * actions become next-cutoff adjustments - so HR confirms they've seen it.
     *
     * @return array{employees: int, issues: array<int, array>, counts: array<string, int>}
     */
    public function precheck(int $cutoffId): array
    {
        $master = $this->find($cutoffId);
        if (!$master) {
            throw ValidationException::withMessages(['cutoff_id' => 'Cutoff not found.']);
        }

        $summaryTable = $this->payrollServices->getCutoffSummaryTableName();
        $rows = DB::table($summaryTable . ' as cs')
            ->leftJoin('tdh_user.users as u', 'u.id', '=', 'cs.employee_id')
            ->where('cs.cutoff_id', $cutoffId)
            ->get(['cs.employee_id', 'cs.generated_at', 'cs.credit_without_pay_days', 'u.fname', 'u.mname', 'u.lname', 'u.suffix']);

        $names = [];
        foreach ($rows as $row) {
            $names[$row->employee_id] = $this->formatName($row);
        }
        $employeeIds = array_keys($names);

        $issues = [];
        $add = function (string $type, int $employeeId, string $detail) use (&$issues, $names) {
            $issues[] = [
                'type' => $type,
                'employee_id' => $employeeId,
                'employee' => $names[$employeeId] ?? ('#' . $employeeId),
                'detail' => $detail,
            ];
        };

        if (!empty($employeeIds)) {
            // 1. Absent work days with nothing filed (no logs, no leave, no pass
            //    slip/HO/holiday exemption) - these become pay deductions.
            $absentDays = DB::table('hris.cutoff_summary_days')
                ->where('cutoff_id', $cutoffId)
                ->where('is_absent', 1)
                ->whereNull('exemption_reason')
                ->orderBy('date')
                ->get(['employee_id', 'date'])
                ->groupBy('employee_id');
            foreach ($absentDays as $employeeId => $days) {
                $dates = $days->map(fn($d) => Carbon::parse($d->date)->format('M j'))->implode(', ');
                $add('unfiled_absence', (int) $employeeId, $days->count() . ' day(s) absent with nothing filed: ' . $dates);
            }

            // 2. Leave still pending for dates in the period.
            $pending = DB::table('hris.leave_applications as la')
                ->join('hris.leave_date_applied as lda', 'la.id', '=', 'lda.leave_application_id')
                ->whereIn('la.user_id', $employeeIds)
                ->whereRaw('LOWER(la.status) = ?', ['pending'])
                ->where('lda.status', 'active')
                ->whereBetween('lda.date_applied', [$master->period_start, $master->period_end])
                ->groupBy('la.user_id', 'la.id')
                ->selectRaw('la.user_id, la.id, COUNT(*) AS days')
                ->get();
            foreach ($pending as $leave) {
                $add('pending_leave', (int) $leave->user_id, "Leave application #{$leave->id} is still pending ({$leave->days} day(s) in this period)");
            }

            // 3. Leave approved/changed after this employee's row was generated -
            //    the cutoff may not reflect it yet; regenerate first.
            $generatedAt = $rows->pluck('generated_at', 'employee_id');
            $changed = DB::table('hris.leave_applications as la')
                ->join('hris.leave_date_applied as lda', 'la.id', '=', 'lda.leave_application_id')
                ->whereIn('la.user_id', $employeeIds)
                ->whereRaw('LOWER(la.status) <> ?', ['pending'])
                ->whereBetween('lda.date_applied', [$master->period_start, $master->period_end])
                ->groupBy('la.user_id', 'la.id', 'la.updated_at')
                ->get(['la.user_id', 'la.id', 'la.updated_at']);
            foreach ($changed as $leave) {
                $generated = $generatedAt[$leave->user_id] ?? null;
                if ($generated && $leave->updated_at && Carbon::parse($leave->updated_at)->gt(Carbon::parse($generated))) {
                    $add('changed_since_generated', (int) $leave->user_id, "Leave application #{$leave->id} changed after this cutoff was generated - regenerate before finalizing");
                }
            }

            // 4. Generated before the Table III LWOP column existed.
            foreach ($rows as $row) {
                if ($row->credit_without_pay_days === null) {
                    $add('old_formula', (int) $row->employee_id, 'Generated with the old credit formula - regenerate before finalizing');
                }
            }
        }

        $counts = [];
        foreach ($issues as $issue) {
            $counts[$issue['type']] = ($counts[$issue['type']] ?? 0) + 1;
        }

        usort($issues, fn($a, $b) => [$a['employee'], $a['type']] <=> [$b['employee'], $b['type']]);

        return ['employees' => count($employeeIds), 'issues' => $issues, 'counts' => $counts];
    }

    public function snapshotsEnabled(): bool
    {
        return (bool) config('hris.cutoff_snapshots_enabled');
    }

    /**
     * Draft -> processing, committed at once so nobody else can act on the
     * cutoff while its snapshot is built. The conditional update means only
     * one of two simultaneous requests can win.
     */
    public function beginProcessing(int $cutoffId, int $userId): void
    {
        $masterTable = $this->payrollServices->getCutoffSummaryMasterTableName();
        $now = now();
        $claimed = DB::table($masterTable)
            ->where('id', $cutoffId)
            ->where(function ($query) use ($now) {
                $query->where('status', self::DRAFT)
                    ->orWhere(function ($stale) use ($now) {
                        $stale->where('status', self::PROCESSING)
                            ->where('processing_started_at', '<', $now->copy()->subMinutes(self::STALE_PROCESSING_MINUTES));
                    });
            })
            ->update(['status' => self::PROCESSING, 'processing_started_at' => $now, 'updated_at' => $now]);

        if (!$claimed) {
            $status = $this->statusOf($this->find($cutoffId));
            throw ValidationException::withMessages(['cutoff_id' => $status === self::PROCESSING
                ? 'This cutoff is already being processed.'
                : 'Only a draft cutoff can be generated. This one is ' . str_replace('_', ' ', $status) . '.']);
        }
        $this->log($cutoffId, self::DRAFT, self::PROCESSING, null, $userId);
    }

    /** Processing -> for review. Runs inside the snapshot transaction. */
    public function finishProcessing(int $cutoffId, int $userId, int $snapshotVersion): void
    {
        DB::table($this->payrollServices->getCutoffSummaryMasterTableName())->where('id', $cutoffId)->update([
            'status' => self::FOR_REVIEW,
            'snapshot_version' => $snapshotVersion,
            'processing_started_at' => null,
            'updated_at' => now(),
        ]);
        $this->log($cutoffId, self::PROCESSING, self::FOR_REVIEW, null, $userId);
    }

    /** Processing -> draft after a failed generation, with the error on record. */
    public function failProcessing(int $cutoffId, int $userId, string $reason): void
    {
        DB::table($this->payrollServices->getCutoffSummaryMasterTableName())
            ->where('id', $cutoffId)->where('status', self::PROCESSING)
            ->update(['status' => self::DRAFT, 'processing_started_at' => null, 'updated_at' => now()]);
        $this->log($cutoffId, self::PROCESSING, self::DRAFT, mb_substr($reason, 0, 1000), $userId);
    }

    /** For review -> draft, so source records can be corrected. */
    public function returnToDraft(int $cutoffId, int $userId): void
    {
        DB::transaction(function () use ($cutoffId, $userId) {
            $masterTable = $this->payrollServices->getCutoffSummaryMasterTableName();
            $master = DB::table($masterTable)->where('id', $cutoffId)->lockForUpdate()->first();
            if (!$master || $this->statusOf($master) !== self::FOR_REVIEW) {
                throw ValidationException::withMessages(['cutoff_id' => 'Only a cutoff under review can be sent back to draft.']);
            }
            DB::table($masterTable)->where('id', $cutoffId)->update(['status' => self::DRAFT, 'updated_at' => now()]);
            $this->log($cutoffId, self::FOR_REVIEW, self::DRAFT, 'Sent back for corrections', $userId);
        });
    }

    /** Fingerprint of everything the cutoff's snapshot holds, ignoring row ids and lock fields. */
    public function snapshotHash(int $cutoffId): string
    {
        $tables = [
            $this->payrollServices->getCutoffSummaryTableName() => 'employee_id',
            'hris.cutoff_summary_days' => 'employee_id, date',
            'hris.cutoff_employee_snapshots' => 'employee_id',
            'hris.cutoff_leave_snapshots' => 'leave_date_applied_id',
            'hris.cutoff_leave_balance_snapshots' => 'employee_id, leave_type_id',
            'hris.cutoff_payroll_components' => 'employee_id, code',
            'hris.cutoff_config_snapshots' => 'config_key',
        ];
        $ignored = array_flip(['id', 'is_locked', 'locked_by', 'locked_at', 'created_at', 'updated_at']);

        $context = hash_init('sha256');
        foreach ($tables as $table => $order) {
            foreach (DB::table($table)->where('cutoff_id', $cutoffId)->orderByRaw($order)->get() as $row) {
                hash_update($context, json_encode(array_diff_key((array) $row, $ignored)));
            }
        }

        return hash_final($context);
    }

    /** False when a finalized snapshot no longer matches its fingerprint; null if it has none. */
    public function snapshotIntact(object $master): ?bool
    {
        return empty($master->snapshot_hash) ? null : hash_equals($master->snapshot_hash, $this->snapshotHash((int) $master->id));
    }

    /** For review (or draft, when snapshots are off) -> finalized: lock every row of the cutoff. */
    public function finalize(int $cutoffId, int $userId): void
    {
        DB::transaction(function () use ($cutoffId, $userId) {
            $masterTable = $this->payrollServices->getCutoffSummaryMasterTableName();
            $master = DB::table($masterTable)->where('id', $cutoffId)->lockForUpdate()->first();
            if (!$master) {
                throw ValidationException::withMessages(['cutoff_id' => 'Cutoff not found.']);
            }
            $status = $this->statusOf($master);
            if ($status === self::DRAFT && $this->snapshotsEnabled()) {
                throw ValidationException::withMessages(['cutoff_id' => 'Generate the snapshot and review it before finalizing.']);
            }
            if (!in_array($status, [self::DRAFT, self::FOR_REVIEW], true)) {
                throw ValidationException::withMessages(['cutoff_id' => 'Only a draft or reviewed cutoff can be finalized. This one is ' . str_replace('_', ' ', $status) . '.']);
            }

            $summaryTable = $this->payrollServices->getCutoffSummaryTableName();
            if (!DB::table($summaryTable)->where('cutoff_id', $cutoffId)->exists()) {
                throw ValidationException::withMessages(['cutoff_id' => 'This cutoff has no employees yet. Generate it first.']);
            }

            $now = now();
            DB::table($summaryTable)->where('cutoff_id', $cutoffId)
                ->update(['is_locked' => 1, 'locked_by' => $userId, 'locked_at' => $now]);
            $hasSnapshot = (int) ($master->snapshot_version ?? 0) > 0 && $status === self::FOR_REVIEW;
            DB::table($masterTable)->where('id', $cutoffId)->update([
                'status' => self::FINALIZED,
                'finalized_by' => $userId,
                'finalized_at' => $now,
                'reviewed_at' => $hasSnapshot ? $now : null,
                'snapshot_hash' => $hasSnapshot ? $this->snapshotHash($cutoffId) : null,
                'updated_at' => $now,
            ]);
            app(CutoffAdjustmentService::class)->apply($cutoffId, $userId);
            $this->log($cutoffId, $status, self::FINALIZED, null, $userId);
        });
    }

    /**
     * Why a finalized cutoff can't go back to draft, or null if it can.
     */
    public function reopenBlocker(object $master): ?string
    {
        $status = $this->statusOf($master);
        if ($status !== self::FINALIZED) {
            return $status === self::CLOSED
                ? 'This cutoff is closed (its payroll was released). Late changes go into the next cutoff as adjustments.'
                : 'Only a finalized cutoff can be reopened.';
        }

        $payroll = DB::table('hris.payrolls')->where('cutoff_id', $master->id)->first(['id', 'title']);
        if ($payroll) {
            return "Payroll \"{$payroll->title}\" already uses this cutoff. Remove it from the payroll first, or put late changes in the next cutoff.";
        }

        // Earned credits are posted once per employee per month.
        $start = Carbon::parse($master->period_start);
        $employeeIds = DB::table($this->payrollServices->getCutoffSummaryTableName())
            ->where('cutoff_id', $master->id)->pluck('employee_id');
        $credited = DB::table('hris.credit_earned_logs')
            ->whereIn('user_id', $employeeIds)
            ->where('month', $start->month)
            ->where('year', $start->year)
            ->exists();
        if ($credited) {
            return 'Leave credits for ' . $start->format('F Y') . ' were already posted for employees in this cutoff. Put changes in the next cutoff as adjustments.';
        }

        return null;
    }

    /** Finalized -> draft, with a reason. Unlocks the rows so they can be regenerated. */
    public function reopen(int $cutoffId, int $userId, string $reason): void
    {
        DB::transaction(function () use ($cutoffId, $userId, $reason) {
            $masterTable = $this->payrollServices->getCutoffSummaryMasterTableName();
            $master = DB::table($masterTable)->where('id', $cutoffId)->lockForUpdate()->first();
            if (!$master) {
                throw ValidationException::withMessages(['cutoff_id' => 'Cutoff not found.']);
            }
            $blocker = $this->reopenBlocker($master);
            if ($blocker) {
                throw ValidationException::withMessages(['cutoff_id' => $blocker]);
            }

            $now = now();
            DB::table($this->payrollServices->getCutoffSummaryTableName())->where('cutoff_id', $cutoffId)
                ->update(['is_locked' => 0, 'locked_by' => null, 'locked_at' => null]);
            DB::table($masterTable)->where('id', $cutoffId)
                ->update(['status' => self::DRAFT, 'finalized_by' => null, 'finalized_at' => null, 'reviewed_at' => null, 'snapshot_hash' => null, 'updated_at' => $now]);
            app(CutoffAdjustmentService::class)->unapply($cutoffId);
            $this->log($cutoffId, self::FINALIZED, self::DRAFT, $reason, $userId);
        });
    }

    /**
     * Finalized -> closed once every payroll using the cutoff is released.
     * Returns true when it closed the cutoff.
     */
    public function closeIfAllPayrollsReleased(int $cutoffId, ?int $userId): bool
    {
        $masterTable = $this->payrollServices->getCutoffSummaryMasterTableName();
        $master = DB::table($masterTable)->where('id', $cutoffId)->lockForUpdate()->first();
        if (!$master || $this->statusOf($master) !== self::FINALIZED) {
            return false;
        }

        $statuses = DB::table('hris.payrolls')->where('cutoff_id', $cutoffId)->pluck('status')
            ->map(fn($s) => strtolower((string) $s));
        if ($statuses->isEmpty() || $statuses->contains(fn($s) => $s !== 'released')) {
            return false;
        }

        $now = now();
        DB::table($masterTable)->where('id', $cutoffId)
            ->update(['status' => self::CLOSED, 'closed_at' => $now, 'updated_at' => $now]);
        $this->log($cutoffId, self::FINALIZED, self::CLOSED, 'All payrolls using this cutoff were released', $userId);

        return true;
    }

    /** @return \Illuminate\Support\Collection */
    public function history(int $cutoffId)
    {
        return DB::table('hris.cutoff_status_logs as l')
            ->leftJoin('tdh_user.users as u', 'u.id', '=', 'l.acted_by')
            ->where('l.cutoff_id', $cutoffId)
            ->orderByDesc('l.id')
            ->get(['l.from_status', 'l.to_status', 'l.reason', 'l.created_at', 'u.fname', 'u.lname']);
    }

    private function log(int $cutoffId, ?string $from, string $to, ?string $reason, ?int $userId): void
    {
        DB::table('hris.cutoff_status_logs')->insert([
            'cutoff_id' => $cutoffId,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'acted_by' => $userId,
            'created_at' => now(),
        ]);
    }

    private function formatName(object $row): string
    {
        $middle = !empty($row->mname) ? ' ' . mb_substr($row->mname, 0, 1) . '.' : '';
        $suffix = !empty($row->suffix) ? ' ' . $row->suffix : '';

        return strtoupper(trim(($row->lname ?? '') . ', ' . ($row->fname ?? '') . $middle . $suffix));
    }
}
