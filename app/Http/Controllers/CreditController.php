<?php

namespace App\Http\Controllers;

use App\Services\LeaveCreditService;
use App\Services\PayrollServices;
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

    public function previewMonthlyLeaveCredits(Request $request)
    {
        try {
            $validated = $request->validate([
                'year' => 'required|integer|min:2000',
                'month' => 'required|integer|between:1,12',
                'employees' => 'nullable|array',
                'employees.*' => 'integer',
            ]);

            $year = (int) $validated['year'];
            $month = (int) $validated['month'];

            $aggregate = $this->aggregateMonthlyEmployeeCutoffs($year, $month, $validated['employees'] ?? null);

            $results = $aggregate['employees']->map(function ($employee) use ($year, $month) {
                if ($employee['status'] !== 'complete') {
                    return $employee;
                }

                $computed = $this->leaveCreditService->computeEarnedCredits(
                    $employee['total_present'],
                    $employee['total_late_undertime'],
                    $employee['total_working_days'],
                    $month,
                    $year
                );

                return $employee + [
                    'earned_points' => $computed['monthly_points_earned'],
                    'deducted_points' => $computed['total_deductions'],
                    'net_points' => $computed['net_points'],
                ];
            })->values();

            return response()->json(['success' => true, 'data' => $results]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function applyMonthlyLeaveCredits(Request $request)
    {
        try {
            $validated = $request->validate([
                'year' => 'required|integer|min:2000',
                'month' => 'required|integer|between:1,12',
                'employees' => 'required|array|min:1',
                'employees.*' => 'integer',
            ]);

            $year = (int) $validated['year'];
            $month = (int) $validated['month'];

            // Re-aggregate server-side rather than trust client-sent totals -
            // this also re-checks completeness in case anything changed
            // between preview and save.
            $aggregate = $this->aggregateMonthlyEmployeeCutoffs($year, $month, $validated['employees']);

            $applied = [];
            $skipped = [];

            foreach ($aggregate['employees'] as $employee) {
                if ($employee['status'] !== 'complete') {
                    $skipped[] = $employee['employee_id'];
                    continue;
                }

                $computed = $this->leaveCreditService->computeEarnedCredits(
                    $employee['total_present'],
                    $employee['total_late_undertime'],
                    $employee['total_working_days'],
                    $month,
                    $year
                );

                $this->leaveCreditService->updateEarnedCredits(
                    $employee['employee_id'],
                    $year,
                    $month,
                    $employee['total_present'],
                    $employee['total_late_undertime'],
                    $computed['net_points'],
                    $computed['total_deductions']
                );

                $applied[] = $employee['employee_id'];
            }

            return response()->json([
                'success' => true,
                'message' => 'Leave credits earned updated.',
                'applied_employee_ids' => $applied,
                'skipped_employee_ids' => $skipped,
            ]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    /**
     * Resolves a calendar month to its cutoff_summaries_master rows, then
     * aggregates each employee's cutoff_summaries rows across all of them.
     * An employee is "complete" only if they have a row for every cutoff in
     * that month; otherwise the missing period(s) are reported instead.
     */
    private function aggregateMonthlyEmployeeCutoffs(int $year, int $month, ?array $employeeIds = null): array
    {
        $masterTable = $this->payrollServices->getCutoffSummaryMasterTableName();
        $monthMasters = DB::table($masterTable)
            ->whereYear('period_start', $year)
            ->whereMonth('period_start', $month)
            ->orderBy('period_start')
            ->get(['id', 'period_start', 'period_end', 'title']);

        $cutoffIds = $monthMasters->pluck('id')->all();

        if (empty($cutoffIds)) {
            return ['masters' => $monthMasters, 'employees' => collect()];
        }

        $table = $this->payrollServices->getCutoffSummaryTableName();
        $query = DB::table($table . ' as cs')
            ->leftJoin('tdh_user.users as u', 'u.id', '=', 'cs.employee_id')
            ->whereIn('cs.cutoff_id', $cutoffIds)
            ->select('cs.*', 'u.fname', 'u.mname', 'u.lname', 'u.suffix');

        if (!empty($employeeIds)) {
            $query->whereIn('cs.employee_id', $employeeIds);
        }

        $employees = $query->orderBy('u.lname')->get()
            ->groupBy('employee_id')
            ->map(function ($group) use ($cutoffIds, $monthMasters) {
                $first = $group->first();
                $coveredCutoffIds = $group->pluck('cutoff_id')->unique()->all();
                $missingIds = array_diff($cutoffIds, $coveredCutoffIds);
                $isComplete = empty($missingIds);

                $middleInitial = !empty($first->mname) ? strtoupper(substr($first->mname, 0, 1)) . '.' : '';
                $suffix = $first->suffix ? ' ' . $first->suffix : '';
                $fullName = trim($first->lname . ', ' . $first->fname . ' ' . $middleInitial . $suffix);

                $result = [
                    'employee_id' => $first->employee_id,
                    'name' => $fullName,
                    'status' => $isComplete ? 'complete' : 'incomplete',
                ];

                if ($isComplete) {
                    $result['total_present'] = (int) $group->sum('days_present');
                    $result['total_working_days'] = (int) $group->sum('total_working_days');
                    $result['total_late_undertime'] = (int) $group->sum('late_minutes') + (int) $group->sum('undertime_minutes');
                } else {
                    $result['missing_periods'] = $monthMasters
                        ->whereIn('id', $missingIds)
                        ->map(fn($m) => ($m->title ?: 'Cutoff') . ' (' . $m->period_start . ' to ' . $m->period_end . ')')
                        ->values()
                        ->all();
                }

                return $result;
            })
            ->values();

        return ['masters' => $monthMasters, 'employees' => $employees];
    }
}
