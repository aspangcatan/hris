<?php

namespace App\Http\Controllers;

use App\Services\LeaveCreditService;
use App\Services\PayrollServices;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreditController extends Controller
{
    protected $leaveCreditService, $payrollServices;

    public function __construct(LeaveCreditService $leaveCreditService, PayrollServices $payrollServices)
    {
        $this->leaveCreditService = $leaveCreditService;
        $this->payrollServices = $payrollServices;
    }

    public function getCreditEarned(Request $request)
    {
        try {
            $logs = DB::table('hris.credit_earned_logs as l')
                ->join('tdh_user.users as u', 'u.id', '=', 'l.user_id')
                ->join('hris.leave_types as t', 't.id', '=', 'l.leave_type_id')
                ->where('l.year', $request->year)
                ->where('l.month', $request->month)
                ->where('u.division', $request->division)
                ->where('u.section', $request->section)
                ->get(['u.fname', 'u.mname', 'u.lname', 'u.suffix', 'l.month', 'l.year', 't.description', 'l.total_present', 'l.total_lateundertime_minutes', 'l.earned_points', 'l.deducted_points']);
            return response()->json($logs);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function previewCutoffLeaveCredits(Request $request)
    {
        try {
            $validated = $request->validate([
                'cutoff_id' => 'required|integer',
                'employees' => 'nullable|array',
                'employees.*' => 'integer',
            ]);

            $computed = $this->computeCreditsForCutoff((int) $validated['cutoff_id'], $validated['employees'] ?? null);

            if ($computed === null) {
                return response()->json(['success' => false, 'message' => 'Cutoff period not found.'], 422);
            }

            $toCredit = $computed['employees']->where('already_credited', false)->count();

            return response()->json([
                'success' => true,
                'data' => $computed['employees'],
                'period_label' => $computed['period_label'],
                'to_credit' => $toCredit,
                // Shown by the page when Save would post nothing.
                'message' => $this->nothingToCreditMessage($computed),
            ]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function applyCutoffLeaveCredits(Request $request)
    {
        try {
            $validated = $request->validate([
                'cutoff_id' => 'required|integer',
                'employees' => 'required|array|min:1',
                'employees.*' => 'integer',
            ]);

            $computed = $this->computeCreditsForCutoff((int) $validated['cutoff_id'], $validated['employees']);

            if ($computed === null) {
                return response()->json(['success' => false, 'message' => 'Cutoff period not found.'], 422);
            }

            $nothingMessage = $this->nothingToCreditMessage($computed);
            if ($nothingMessage !== null) {
                return response()->json(['success' => false, 'message' => $nothingMessage], 422);
            }

            $credited = 0;
            $skipped = 0;
            foreach ($computed['employees'] as $employee) {
                if ($employee['already_credited']) {
                    $skipped++;
                    continue;
                }

                $posted = $this->leaveCreditService->updateEarnedCredits(
                    $employee['employee_id'],
                    $computed['year'],
                    $computed['month'],
                    $employee['total_present'],
                    $employee['total_late_undertime'],
                    $employee['earned_points'],
                    $employee['deducted_points']
                );

                // 0 = someone else credited this employee a moment ago.
                $posted > 0 ? $credited++ : $skipped++;
            }

            $message = "Leave credits earned for {$computed['period_label']} were posted to {$credited} "
                . ($credited === 1 ? 'employee' : 'employees') . '.';
            if ($skipped > 0) {
                $message .= " {$skipped} already credited for {$computed['period_label']} "
                    . ($skipped === 1 ? 'was' : 'were') . ' skipped.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'credited' => $credited,
                'skipped' => $skipped,
            ]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    /**
     * Why Save would post nothing for this cutoff, or null if at least one
     * employee can be credited. Credits need at least one employee who isn't
     * credited for the month yet, and at least one of those with attendance -
     * so an empty cutoff (no logs yet) can't post zeros and lock the month.
     */
    private function nothingToCreditMessage(array $computed): ?string
    {
        $employees = $computed['employees'];
        $label = $computed['period_label'];

        if ($employees->isEmpty()) {
            return "There are no employees to credit in this cutoff for {$label}.";
        }

        $pending = $employees->where('already_credited', false);
        if ($pending->isEmpty()) {
            return "All employees in this cutoff were already credited for {$label}. Nothing was posted.";
        }

        if ($pending->where('total_present', '>', 0)->isEmpty()) {
            return "None of the employees still to be credited for {$label} has any attendance in this cutoff. "
                . 'Check that the time logs are complete, regenerate the cutoff, then try again.';
        }

        return null;
    }

    /**
     * Computes earned-credit figures directly from one already-generated
     * cutoff's cutoff_summaries rows - no cross-cutoff aggregation. Cutoffs
     * in this app are arbitrary admin-defined ranges (often a full month),
     * so whichever period the admin generated is the period credits are
     * earned for; the working-day scaling uses that cutoff's own
     * total_working_days.
     */
    private function computeCreditsForCutoff(int $cutoffId, ?array $employeeIds = null): ?array
    {
        $masterTable = $this->payrollServices->getCutoffSummaryMasterTableName();
        $master = DB::table($masterTable)->where('id', $cutoffId)->first();

        if (!$master) {
            return null;
        }

        $table = $this->payrollServices->getCutoffSummaryTableName();
        $query = DB::table($table . ' as cs')
            ->leftJoin('tdh_user.users as u', 'u.id', '=', 'cs.employee_id')
            ->leftJoin('hris.employees as e', 'e.user_id', '=', 'cs.employee_id')
            ->where('cs.cutoff_id', $cutoffId)
            // Job Order employees don't earn VL/SL credits. The current marker is
            // employees.employee_type; users.status = 'JO' is an older one, still
            // left on a few rows.
            ->where(function ($q) {
                $q->whereNull('e.employee_type')->orWhere('e.employee_type', '<>', 'Job Order');
            })
            ->where(function ($q) {
                $q->whereNull('u.status')->orWhere('u.status', '<>', 'JO');
            })
            ->select('cs.*', 'u.fname', 'u.mname', 'u.lname', 'u.suffix');

        if (!empty($employeeIds)) {
            $query->whereIn('cs.employee_id', $employeeIds);
        }

        $period = Carbon::parse($master->period_start);
        $month = (int) $period->format('m');
        $year = (int) $period->format('Y');

        $rows = $query->orderBy('u.lname')->get();
        $alreadyCredited = array_flip($this->leaveCreditService->alreadyCreditedUserIds(
            $rows->pluck('employee_id')->map(fn($id) => (int) $id)->all(),
            $year,
            $month
        ));

        $employees = $rows->map(function ($summary) use ($month, $year, $alreadyCredited) {
            $totalPresent = (int) ($summary->days_present ?? 0);
            // The employee's own scheduled work days (CWW/shift = fewer days),
            // so perfect attendance always earns the full 1.250. Cutoffs
            // generated before this column existed have 0 - fall back to the
            // plain weekday count until they are regenerated.
            $totalWorkingDays = (int) ($summary->scheduled_work_days ?? 0)
                ?: (int) ($summary->total_working_days ?? 0);
            // Personal pass slip minutes (in working hours) are charged to VL
            // here, together with late/undertime; payroll never reads them.
            $passSlipMinutes = (int) ($summary->approved_pass_slips_minutes ?? 0);
            $lateUndertimeOnly = (int) ($summary->late_minutes ?? 0) + (int) ($summary->undertime_minutes ?? 0);
            $totalLateUndertime = $lateUndertimeOnly + $passSlipMinutes;

            // Days without pay (LWOP + unexcused absences, CSC Sec. 33 rest
            // days included): credits use 30 - this. NULL = cutoff generated
            // before the column existed -> old present / work days formula
            // until the cutoff is regenerated.
            $withoutPayDays = isset($summary->credit_without_pay_days)
                ? (float) $summary->credit_without_pay_days
                : null;

            $result = $this->leaveCreditService->computeEarnedCredits(
                $totalPresent,
                $totalLateUndertime,
                $totalWorkingDays,
                $month,
                $year,
                $withoutPayDays
            );

            $middleInitial = !empty($summary->mname) ? strtoupper(substr($summary->mname, 0, 1)) . '.' : '';
            $suffix = $summary->suffix ? ' ' . $summary->suffix : '';
            $fullName = trim($summary->lname . ', ' . $summary->fname . ' ' . $middleInitial . $suffix);

            return [
                'employee_id' => $summary->employee_id,
                'name' => $fullName,
                'total_present' => $result['total_present'],
                'work_days' => $totalWorkingDays,
                'without_pay_days' => $withoutPayDays,
                'total_late_undertime' => $result['total_late_undertime'],
                'late_undertime_minutes' => $lateUndertimeOnly,
                'pass_slip_minutes' => $passSlipMinutes,
                'earned_points' => $result['monthly_points_earned'],
                'deducted_points' => $result['total_deductions'],
                'net_points' => $result['net_points'],
                'already_credited' => isset($alreadyCredited[(int) $summary->employee_id]),
            ];
        })->values();

        return [
            'master' => $master,
            'month' => $month,
            'year' => $year,
            'period_label' => $period->format('F Y'),
            'employees' => $employees,
        ];
    }
}
