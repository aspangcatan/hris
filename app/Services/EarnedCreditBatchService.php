<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Earned VL/SL credits as a monthly batch (cutoff flow step 3, client
 * decisions 2026-11-11):
 *  - built from the month's FINALIZED (or closed) cutoffs only - drafts are
 *    left out and reported;
 *  - per employee, filed LWOP days of all their finalized cutoffs in the month
 *    are added together and read from CSC Table III;
 *  - VL = SL = the Table III value: late/undertime is charged to salary only,
 *    never to leave credits;
 *  - one posting per employee per month (credit_earned_logs), done by HR with
 *    one click after reviewing the batch;
 *  - while any selected employee has unfiled absences in those cutoffs, the
 *    whole post is refused and they are named, so HR resolves or unticks them
 *    (client decision 2026-11-11).
 *
 * A cutoff with a snapshot supplies eligibility, the name and Table III as they
 * were when it was generated. Older cutoffs without one read the live tables.
 */
class EarnedCreditBatchService
{
    private LeaveCreditService $leaveCreditService;
    private PayrollServices $payrollServices;

    public function __construct(LeaveCreditService $leaveCreditService, PayrollServices $payrollServices)
    {
        $this->leaveCreditService = $leaveCreditService;
        $this->payrollServices = $payrollServices;
    }

    /**
     * Months that have at least one finalized/closed cutoff, newest first, with
     * how many employees are ready / already credited / blocked.
     */
    public function months(int $limit = 24): Collection
    {
        $masterTable = $this->payrollServices->getCutoffSummaryMasterTableName();

        $months = DB::table($masterTable)
            ->whereIn('status', ['finalized', 'closed'])
            ->selectRaw('YEAR(period_start) AS y, MONTH(period_start) AS m')
            ->groupByRaw('YEAR(period_start), MONTH(period_start)')
            ->orderByRaw('YEAR(period_start) DESC, MONTH(period_start) DESC')
            ->limit($limit)
            ->get();

        return $months->map(function ($row) {
            $batch = $this->compute((int) $row->y, (int) $row->m);
            $employees = $batch['employees'];

            return [
                'year' => (int) $row->y,
                'month' => (int) $row->m,
                'key' => sprintf('%04d-%02d', $row->y, $row->m),
                'label' => $batch['period_label'],
                'cutoffs' => count($batch['cutoffs']),
                'draft_cutoffs' => count($batch['draft_cutoffs']),
                'employees' => $employees->count(),
                'ready' => $employees->where('status', 'ready')->count(),
                'credited' => $employees->where('status', 'credited')->count(),
                'blocked' => $employees->whereIn('status', ['blocked', 'unfiled'])->count(),
            ];
        })->values();
    }

    /**
     * The batch for one month.
     *
     * @return array{year:int, month:int, period_label:string, cutoffs:array, draft_cutoffs:array, employees:Collection}
     */
    public function compute(int $year, int $month, ?array $employeeIds = null): array
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->startOfDay();
        $masterTable = $this->payrollServices->getCutoffSummaryMasterTableName();
        $summaryTable = $this->payrollServices->getCutoffSummaryTableName();

        $masters = DB::table($masterTable)
            ->whereBetween('period_start', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->orderBy('period_start')
            ->get(['id', 'title', 'period_start', 'period_end', 'status', 'snapshot_version']);
        $isUsable = fn($m) => in_array(strtolower((string) ($m->status ?? 'draft')), ['finalized', 'closed'], true);
        $usable = $masters->filter($isUsable)->values();
        $drafts = $masters->reject($isUsable)->values();

        $describe = fn($m) => [
            'id' => (int) $m->id,
            'title' => $m->title,
            'period_start' => $m->period_start,
            'period_end' => $m->period_end,
            'status' => strtolower((string) ($m->status ?? 'draft')),
        ];

        $result = [
            'year' => $year,
            'month' => $month,
            'period_label' => $monthStart->format('F Y'),
            'cutoffs' => $usable->map($describe)->all(),
            'draft_cutoffs' => $drafts->map($describe)->all(),
            'employees' => collect(),
        ];
        if ($usable->isEmpty()) {
            return $result;
        }

        $query = DB::table($summaryTable . ' as cs')
            ->join($masterTable . ' as cm', 'cm.id', '=', 'cs.cutoff_id')
            ->leftJoin('tdh_user.users as u', 'u.id', '=', 'cs.employee_id')
            ->leftJoin('hris.employees as e', 'e.user_id', '=', 'cs.employee_id')
            ->leftJoin('hris.cutoff_employee_snapshots as ces', function ($join) {
                $join->on('ces.cutoff_id', '=', 'cs.cutoff_id')->on('ces.employee_id', '=', 'cs.employee_id');
            })
            ->whereIn('cs.cutoff_id', $usable->pluck('id'))
            // Job Order employees don't earn VL/SL credits: the snapshot decided
            // this if there is one. Otherwise the current marker is
            // employees.employee_type; users.status = 'JO' is an older one.
            ->where(function ($eligible) {
                $eligible->where('ces.credit_eligible', 1)
                    ->orWhere(function ($legacy) {
                        $legacy->whereNull('ces.id')
                            ->where(function ($q) {
                                $q->whereNull('e.employee_type')->orWhere('e.employee_type', '<>', 'Job Order');
                            })
                            ->where(function ($q) {
                                $q->whereNull('u.status')->orWhere('u.status', '<>', 'JO');
                            });
                    });
            })
            ->orderBy('cm.period_start')
            ->select('cs.*', 'cm.period_start as cutoff_start', 'cm.period_end as cutoff_end', 'cm.title as cutoff_title',
                'u.fname', 'u.mname', 'u.lname', 'u.suffix', 'ces.employee_name as snapshot_name');
        if (!empty($employeeIds)) {
            $query->whereIn('cs.employee_id', $employeeIds);
        }
        $byEmployee = $query->get()->groupBy('employee_id');

        $credited = array_flip($this->leaveCreditService->alreadyCreditedUserIds(
            $byEmployee->keys()->map(fn($id) => (int) $id)->all(), $year, $month
        ));

        $usableIds = $usable->pluck('id');
        $pointsTables = DB::table('hris.cutoff_config_snapshots')
            ->whereIn('cutoff_id', $usableIds)->where('config_key', 'table_iii')
            ->pluck('payload', 'cutoff_id');
        $absences = DB::table('hris.cutoff_summary_days')
            ->whereIn('cutoff_id', $usableIds)
            ->whereIn('employee_id', $byEmployee->keys())
            ->where('is_absent', 1)
            ->whereNull('exemption_reason')
            ->orderBy('date')
            ->get(['employee_id', 'date'])
            ->groupBy('employee_id');

        $employees = $byEmployee->map(function (Collection $rows, $employeeId) use ($credited, $month, $year, $monthStart, $monthEnd, $pointsTables, $absences) {
            return $this->employeeRow((int) $employeeId, $rows, isset($credited[(int) $employeeId]), $month, $year, $monthStart, $monthEnd, $pointsTables, $absences->get($employeeId, collect()));
        })->sortBy('name')->values();

        $result['employees'] = $employees;

        return $result;
    }

    /** Post the ready rows of the month for the given employees. */
    public function post(int $year, int $month, array $employeeIds, ?int $postedBy): array
    {
        $batch = $this->compute($year, $month, $employeeIds);

        // One selected employee with unfiled absences stops the whole post.
        $unfiled = $batch['employees']->where('status', 'unfiled');
        if ($unfiled->isNotEmpty()) {
            return ['batch' => $batch, 'credited' => 0, 'skipped' => 0, 'blocked' => $unfiled->count(), 'refused' => true];
        }

        $credited = 0;
        $skipped = 0;
        $blocked = 0;

        foreach ($batch['employees'] as $employee) {
            if ($employee['status'] !== 'ready') {
                $employee['status'] === 'credited' ? $skipped++ : $blocked++;
                continue;
            }

            $posted = $this->leaveCreditService->updateEarnedCredits(
                $employee['employee_id'],
                $year,
                $month,
                $employee['total_present'],
                0,                           // late/undertime: salary only
                $employee['earned_points'],
                0,
                [
                    'cutoff_id' => $employee['cutoff_id'],
                    'lwop_days' => $employee['without_pay_days'],
                    'posted_by' => $postedBy,
                ]
            );
            // 0 = someone else credited this employee a moment ago.
            $posted > 0 ? $credited++ : $skipped++;
        }

        return ['batch' => $batch, 'credited' => $credited, 'skipped' => $skipped, 'blocked' => $blocked];
    }

    /** Label of the month a cutoff's credits belong to, e.g. "October 2026". */
    public function monthLabelFor(object $master): string
    {
        return Carbon::parse($master->period_start)->format('F Y');
    }

    private function employeeRow(int $employeeId, Collection $rows, bool $alreadyCredited, int $month, int $year, Carbon $monthStart, Carbon $monthEnd, Collection $pointsTables, Collection $absences): array
    {
        $first = $rows->first();
        $middleInitial = !empty($first->mname) ? strtoupper(substr($first->mname, 0, 1)) . '.' : '';
        $suffix = !empty($first->suffix) ? ' ' . $first->suffix : '';
        $latest = $rows->sortBy('cutoff_end')->last();
        $name = $latest->snapshot_name
            ?? trim(($first->lname ?? '') . ', ' . ($first->fname ?? '') . ' ' . $middleInitial . $suffix);

        $totalPresent = (int) $rows->sum(fn($r) => (int) ($r->days_present ?? 0));
        // The employee's own scheduled work days (CWW/shift = fewer days); older
        // cutoffs have 0 there - fall back to the plain weekday count.
        $workDays = (int) $rows->sum(fn($r) => (int) ($r->scheduled_work_days ?? 0) ?: (int) ($r->total_working_days ?? 0));

        $blockReason = null;

        // Two finalized cutoffs covering the same days would count LWOP twice.
        $previousEnd = null;
        foreach ($rows as $row) {
            if ($previousEnd !== null && Carbon::parse($row->cutoff_start)->lte($previousEnd)) {
                $blockReason = 'In two finalized cutoffs with overlapping dates - reopen one of them and remove the employee.';
                break;
            }
            $previousEnd = Carbon::parse($row->cutoff_end);
        }

        // Filed LWOP days. NULL = row generated before the Table III column:
        // a single such row keeps the old present / work days formula; mixed
        // with other rows it can't be added up.
        $hasOld = $rows->contains(fn($r) => $r->credit_without_pay_days === null);
        $withoutPayDays = $hasOld ? null : round((float) $rows->sum(fn($r) => (float) $r->credit_without_pay_days), 3);
        if ($hasOld && $rows->count() > 1 && !$blockReason) {
            $blockReason = 'One of the cutoffs was generated with the old formula - reopen and regenerate it.';
        }

        // Unfiled absences have to be resolved before this employee can be credited.
        $unfiledDetail = null;
        if ($absences->isNotEmpty()) {
            $dates = $absences->map(fn($d) => Carbon::parse($d->date)->format('M j'))->implode(', ');
            $unfiledDetail = $absences->count() . ' day(s) absent with nothing filed: ' . $dates;
        }

        // Table III as saved with the employee's latest cutoff of the month, if it has a snapshot.
        $payload = $pointsTables->get($latest->cutoff_id);
        $computed = $this->leaveCreditService->computeEarnedCredits(
            $totalPresent, 0, $workDays, $month, $year, $withoutPayDays, $payload ? json_decode($payload, true) : null
        );
        $points = (float) $computed['monthly_points_earned'];

        // Days of the month covered by the employee's finalized cutoffs.
        $covered = 0;
        foreach ($rows as $row) {
            $from = Carbon::parse($row->cutoff_start)->max($monthStart);
            $to = Carbon::parse($row->cutoff_end)->min($monthEnd);
            $covered += $to->gte($from) ? $from->diffInDays($to) + 1 : 0;
        }
        $fullMonth = $covered >= $monthStart->daysInMonth;

        if (!$blockReason && !$unfiledDetail && !$alreadyCredited && $totalPresent <= 0) {
            $blockReason = 'No attendance in the finalized cutoffs - check the time logs.';
        }

        $status = $alreadyCredited ? 'credited' : ($blockReason ? 'blocked' : ($unfiledDetail ? 'unfiled' : 'ready'));

        return [
            'employee_id' => $employeeId,
            'name' => $name,
            'cutoff_id' => (int) $rows->sortBy('cutoff_end')->last()->cutoff_id,
            'cutoffs' => $rows->map(fn($r) => Carbon::parse($r->cutoff_start)->format('M j') . '-' . Carbon::parse($r->cutoff_end)->format('j'))->values()->all(),
            'full_month' => $fullMonth,
            'total_present' => $totalPresent,
            'work_days' => $workDays,
            'without_pay_days' => $withoutPayDays,
            'earned_points' => round($points, 3),
            'vl_points' => round($points, 3),
            'sl_points' => round($points, 3),
            'already_credited' => $alreadyCredited,
            'status' => $status,
            'reason' => $alreadyCredited ? null : ($blockReason ?? $unfiledDetail),
            'unfiled_absences' => $alreadyCredited ? null : $unfiledDetail,
        ];
    }
}
