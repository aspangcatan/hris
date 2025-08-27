<?php

namespace App\Http\Controllers;

use App\Services\DTRServices;
use App\Services\LeaveCreditService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreditController extends Controller
{
    protected $dtrService, $leaveCreditService;

    public function __construct(DTRServices $dtrService, LeaveCreditService $leaveCreditService)
    {
        $this->dtrService = $dtrService;
        $this->leaveCreditService = $leaveCreditService;
    }

    public function updateCreditBalance(Request $request)
    {
        try {
            $employees = DB::table('hris.schedules as s')
                ->join('tdh_user.users as u', 'u.id', '=', 's.employee_id')
                ->select('s.employee_id')
                ->whereMonth('s.schedule_date', $request->month)
                ->whereYear('s.schedule_date', $request->year)
                ->where('u.division', $request->division)
                ->where('u.section', $request->section)
                ->groupBy('s.employee_id')
                ->get();


            foreach ($employees as $employee) {
                $userid = $employee->employee_id;
                $totalPresent = $this->dtrService->getTotalPresent($userid, $request->month, $request->year);

                [$totalLateUndertimeMinutes] = $this->dtrService->getTotalLateAbsent($userid, $request->month, $request->year);

                $data = $this->leaveCreditService->computeEarnedCredits($userid, $totalPresent, $totalLateUndertimeMinutes);

                $this->leaveCreditService->updateEarnedCredits($userid, $request->year, $request->month, $totalPresent, $totalLateUndertimeMinutes, $data['monthly_points_earned'], $data['total_deductions']);
            }

            return response()->json(['message' => "Success"]);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
       }
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
}
