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

            return response()->json(['success' => true, 'data' => $computed['employees']]);
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

            foreach ($computed['employees'] as $employee) {
                $this->leaveCreditService->updateEarnedCredits(
                    $employee['employee_id'],
                    $computed['year'],
                    $computed['month'],
                    $employee['total_present'],
                    $employee['total_late_undertime'],
                    $employee['net_points'],
                    $employee['deducted_points']
                );
            }

            return response()->json(['success' => true, 'message' => 'Leave credits earned updated.']);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
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
            ->where('cs.cutoff_id', $cutoffId)
            ->select('cs.*', 'u.fname', 'u.mname', 'u.lname', 'u.suffix');

        if (!empty($employeeIds)) {
            $query->whereIn('cs.employee_id', $employeeIds);
        }

        $period = Carbon::parse($master->period_start);
        $month = (int) $period->format('m');
        $year = (int) $period->format('Y');

        $employees = $query->orderBy('u.lname')->get()->map(function ($summary) use ($month, $year) {
            $totalPresent = (int) ($summary->days_present ?? 0);
            $totalWorkingDays = (int) ($summary->total_working_days ?? 0);
            $totalLateUndertime = (int) ($summary->late_minutes ?? 0) + (int) ($summary->undertime_minutes ?? 0);

            $result = $this->leaveCreditService->computeEarnedCredits(
                $totalPresent,
                $totalLateUndertime,
                $totalWorkingDays,
                $month,
                $year
            );

            $middleInitial = !empty($summary->mname) ? strtoupper(substr($summary->mname, 0, 1)) . '.' : '';
            $suffix = $summary->suffix ? ' ' . $summary->suffix : '';
            $fullName = trim($summary->lname . ', ' . $summary->fname . ' ' . $middleInitial . $suffix);

            return [
                'employee_id' => $summary->employee_id,
                'name' => $fullName,
                'total_present' => $result['total_present'],
                'total_late_undertime' => $result['total_late_undertime'],
                'earned_points' => $result['monthly_points_earned'],
                'deducted_points' => $result['total_deductions'],
                'net_points' => $result['net_points'],
            ];
        })->values();

        return ['master' => $master, 'month' => $month, 'year' => $year, 'employees' => $employees];
    }
}
