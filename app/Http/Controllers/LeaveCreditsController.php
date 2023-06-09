<?php

namespace App\Http\Controllers;

use App\Models\LeaveCreditLog;
use App\Models\LeaveCredits;
use Illuminate\Http\Request;

class LeaveCreditsController extends Controller
{
    public function insertUpdateCredits(Request $request)
    {
        try {
            $leave_credits = LeaveCredits::where('user_id', $request->user_id)->first();
            if (!$leave_credits) {
                $leave_credits = new LeaveCredits;
                $leave_credits->user_id = $request->user_id;
            }
            $leave_credits->vl_bal = $request->vl_bal;
            $leave_credits->sl_bal = $request->sl_bal;
            $leave_credits->cto_bal = $request->cto_bal;
            $leave_credits->fl_bal = $request->fl_bal;
            $leave_credits->spl_bal = $request->spl_bal;
            $leave_credits->save();
            #ADDING CREDIT LOGS
            $logs = new LeaveCreditLog;
            $logs->leave_credit_id = 1;
            $logs->updated_by = 1;
            $logs->status = 1;
            $logs->remarks = 'vl_bal=' . $request->vl_bal . ';sl_bal=' . $request->sl_bal . ';cto_bal=' . $request->cto_bal . ';fl_bal=' . $request->fl_bal . ';spl_bal=' . $request->spl_bal;
            $logs->save();
            return response(['code' => 200, 'message' => 'Credits applied']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function deleteCredits(Request $request)
    {
        try {
            return response(['code' => 200, 'message' => 'Credits deleted']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function showLogs(Request $request)
    {
        try {

            return response(['code' => 200, 'results' => []]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }
}
