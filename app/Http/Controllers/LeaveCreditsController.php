<?php

namespace App\Http\Controllers;

use App\Models\LeaveCreditLog;
use App\Models\LeaveCredits;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
            $logs->updated_by = Auth::id();
            $logs->status = "Added";
            $logs->remarks = 'vl_bal=' . $request->vl_bal . ';sl_bal=' . $request->sl_bal . ';cto_bal=' . $request->cto_bal . ';fl_bal=' . $request->fl_bal . ';spl_bal=' . $request->spl_bal;
            $logs->save();
            return response(['code' => 200, 'message' => 'Credits applied']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function bulkUpdateCredits(Request $request)
    {
        try {
            $credit_id = 0;
            $employee = User::get(['id']);
            #LOOP ALL EMPLOYEES AND UPDATE CREDIT BALANCE
            for ($i = 0; $i < count($employee); $i++) {
                #GET CREDIT RECORD AND UPDATE
                $credit = LeaveCredits::where('user_id', $employee[$i]->id)->first();
                if (!$credit) {
                    $credit = new LeaveCredits;
                    $credit->user_id = $employee[$i]->id;
                    $credit->vl_bal = $request->vl_bal;
                    $credit->sl_bal = $request->sl_bal;
                    $credit->cto_bal = $request->cto_bal;
                    $credit->fl_bal = $request->fl_bal;
                    $credit->spl_bal = $request->spl_bal;
                } else {
                    $credit_id = $credit->id;
                    $credit->vl_bal = $credit->vl_bal + $request->vl_bal;
                    $credit->sl_bal = $credit->sl_bal + $request->sl_bal;
                    $credit->cto_bal = $credit->cto_bal + $request->cto_bal;
                    $credit->fl_bal = $credit->fl_bal + $request->fl_bal;
                    $credit->spl_bal = $credit->spl_bal + $request->spl_bal;
                }
                $credit->save();

                if ($credit_id == 0) $credit_id = $credit->id;

                #ADDING CREDIT LOGS
                $logs = new LeaveCreditLog;
                $logs->leave_credit_id = $credit_id;
                $logs->updated_by = Auth::id();
                $logs->status = "Added";
                $logs->remarks = 'vl_bal=' . $request->vl_bal . ';sl_bal=' . $request->sl_bal . ';cto_bal=' . $request->cto_bal . ';fl_bal=' . $request->fl_bal . ';spl_bal=' . $request->spl_bal;
                $logs->save();
            }
            return response(['code' => 200, 'message' => count($employee) . ' total item successfully updated']);
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

    public function getEmployeeCredits(Request $request)
    {
        try {
            $fname = $request->search_fname;
            $lname = $request->search_lname;
            $employee = DB::table('tdh_user.users as t')
                ->leftJoin('hris.leave_credits as l', 't.id', '=', 'l.user_id')
                ->where(function ($query) use ($fname, $lname) {
                    if ($fname != "")
                        $query->where('t.fname', 'LIKE', '%' . $fname . '%');
                    if ($lname != "")
                        $query->where('t.lname', 'LIKE', '%' . $lname . '%');
                })
                ->skip($request->page * 10)
                ->take(11)
                ->get(['t.id', 'fname', 'mname', 'lname', 'suffix', 'vl_bal', 'sl_bal', 'cto_bal', 'fl_bal', 'spl_bal']);
            return response(['code' => 200, 'results' => $employee]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function getAllBalances(Request $request)
    {
        try {
            $fname = $request->search_fname;
            $lname = $request->search_lname;
            $employee = DB::table('tdh_user.users as t')
                ->leftJoin('hris.leave_credits as l', 't.id', '=', 'l.user_id')
                ->where(function ($query) use ($fname, $lname) {
                    if ($fname != "")
                        $query->where('t.fname', 'LIKE', '%' . $fname . '%');
                    if ($lname != "")
                        $query->where('t.lname', 'LIKE', '%' . $lname . '%');
                })
                ->get(['t.id', 'fname', 'mname', 'lname', 'suffix', 'vl_bal', 'sl_bal', 'cto_bal', 'fl_bal', 'spl_bal']);
            return response(['code' => 200, 'results' => $employee]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }
}
