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
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'employees' => 'nullable|array',
                'employees.*' => 'integer',
            ]);

            $table = $this->payrollServices->getCutoffSummaryTableName();
            $masterTable = $this->payrollServices->getCutoffSummaryMasterTableName();
            $query = DB::table($table . ' as cs')
                ->leftJoin($masterTable . ' as cm', 'cm.id', '=', 'cs.cutoff_id')
                ->leftJoin('tdh_user.users as u', 'u.id', '=', 'cs.employee_id')
                ->select(
                    'cs.*',
                    'cm.period_start',
                    'cm.period_end',
                    'u.fname',
                    'u.mname',
                    'u.lname',
                    'u.suffix'
                )
                ->where('cm.period_start', $validated['start_date'])
                ->where('cm.period_end', $validated['end_date']);

            if (!empty($validated['employees'])) {
                $query->whereIn('cs.employee_id', $validated['employees']);
            }

            $summaries = $query->orderBy('u.lname')->get();
            $period = Carbon::parse($validated['start_date']);
            $month = (int) $period->format('m');
            $year = (int) $period->format('Y');
            $results = [];

            foreach ($summaries as $summary) {
                $totalPresent = (int) ($summary->days_present ?? 0);
                $totalLateUndertime = (int) ($summary->late_minutes ?? 0) + (int) ($summary->undertime_minutes ?? 0);
                $computed = $this->leaveCreditService->computeEarnedCredits(
                    $totalPresent,
                    $totalLateUndertime,
                    $month,
                    $year
                );

                $middleInitial = !empty($summary->mname) ? strtoupper(substr($summary->mname, 0, 1)) . '.' : '';
                $suffix = $summary->suffix ? ' ' . $summary->suffix : '';
                $fullName = trim($summary->lname . ', ' . $summary->fname . ' ' . $middleInitial . $suffix);

                $results[] = [
                    'employee_id' => $summary->employee_id,
                    'name' => $fullName,
                    'total_present' => $computed['total_present'],
                    'total_late_undertime' => $computed['total_late_undertime'],
                    'earned_points' => $computed['monthly_points_earned'],
                    'deducted_points' => $computed['total_deductions'],
                    'net_points' => $computed['net_points'],
                ];
            }

            return response()->json(['success' => true, 'data' => $results]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function applyCutoffLeaveCredits(Request $request)
    {
        try {
            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'employees' => 'nullable|array',
                'employees.*' => 'integer',
            ]);

            $table = $this->payrollServices->getCutoffSummaryTableName();
            $masterTable = $this->payrollServices->getCutoffSummaryMasterTableName();
            $query = DB::table($table . ' as cs')
                ->leftJoin($masterTable . ' as cm', 'cm.id', '=', 'cs.cutoff_id')
                ->select('cs.*')
                ->where('cm.period_start', $validated['start_date'])
                ->where('cm.period_end', $validated['end_date']);

            if (!empty($validated['employees'])) {
                $query->whereIn('cs.employee_id', $validated['employees']);
            }

            $summaries = $query->get();
            $period = Carbon::parse($validated['start_date']);
            $month = (int) $period->format('m');
            $year = (int) $period->format('Y');

            foreach ($summaries as $summary) {
                $totalPresent = (int) ($summary->days_present ?? 0);
                $totalLateUndertime = (int) ($summary->late_minutes ?? 0) + (int) ($summary->undertime_minutes ?? 0);
                $computed = $this->leaveCreditService->computeEarnedCredits(
                    $totalPresent,
                    $totalLateUndertime,
                    $month,
                    $year
                );

                $this->leaveCreditService->updateEarnedCredits(
                    $summary->employee_id,
                    $year,
                    $month,
                    $totalPresent,
                    $totalLateUndertime,
                    $computed['monthly_points_earned'],
                    $computed['total_deductions']
                );
            }

            return response()->json(['success' => true, 'message' => 'Leave credits earned updated.']);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }
}
