<?php

namespace App\Http\Controllers;

use App\Models\LeaveFile;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveController extends Controller
{
    public function getLeaves(Request $request)
    {
        try {
            $leaves = LeaveFile::where('user_id', Auth::id())
                ->skip($request->page * 10)
                ->take(11)
                ->get();
            return response(['code' => 200, 'results' => $leaves]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function getLeaveStatus(Request $request)
    {
        try {
            $leave = LeaveFile::find($request->id);
            return response()->json(['code' => 200, 'results' => $leave]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function insertLeaves(Request $request)
    {
        try {
            $leave = new LeaveFile;
            $leave->user_id = Auth::id();
            $leave->dates_applied = $request->dates_applied;
            $leave->leave_type = $request->leave_type;
            $leave->leave_details = $request->leave_details;
            $leave->leave_extra = $request->leave_extra;
            $leave->commutation = $request->commutation;
            $leave->remarks = $request->remarks;
            $leave->save();
            return response(['code' => 200, 'message' => 'Leave applied successfully']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function insertUpdateBaseCredit(Request $request)
    {
        try {
            DB::table('leave_credits')->updateOrInsert(
                [
                    'user_id' => $request->employee_id,
                    'leave_id' => $request->leave_id,
                ],
                [
                    'base_credit' => $request->base_credit,
                    'user_leave' => $request->employee_id . '-' . $request->leave_id
                ]
            );

            return response()->json(['message' => "Base Credit is updated"]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function insertUpdateUsedCredit(Request $request)
    {
        try {
            DB::table('leave_credits')->updateOrInsert(
                [
                    'user_id' => $request->employee_id,
                    'leave_id' => $request->leave_id,
                ],
                [
                    'used_credit' => $request->used_credit,
                    'user_leave' => $request->employee_id . '-' . $request->leave_id
                ]
            );

            return response()->json(['message' => "Used Credit is updated"]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function getCreditBalances(Request $request)
    {
        try {
            #SHOW CREDIT BALANCE
            #GET ALL LEAVE TYPES AND RETURN THEIR CREDIT BALANCES BY EMPLOYEE

            $userid = ($request->has('id')) ? $request->id : Auth::id();
            $leave_types = DB::table('leave_types')->get(['id', 'description', 'code', 'monthly_increment']);
            $leave_credits = [];
            $user_info = DB::table('hris.employees')->where('user_id', '=', $userid)->get(['date_hired'])->first();
            if (!$user_info->date_hired) {
                return response()->json(['error' => 'Date hired is not set, please ask system administrator'], 500);
            }

            $date_hired = Carbon::parse($user_info->date_hired);
            $lastDayOfMonth = Carbon::now()->endOfMonth();

            foreach ($leave_types as $type) {
                $leave_id = $type->id;
                $base_credit = 0.00;
                $used_credit = 0.00;
                $credit = DB::table('leave_credits')
                    ->where(function ($query) use ($leave_id, $userid) {
                        $query->where('user_id', '=', $userid);
                        $query->where('leave_id', '=', $leave_id);
                    })
                    ->first();

                if ($credit) {
                    $base_credit = $credit->base_credit;
                    $used_credit = $credit->used_credit;
                }

                $MONTH_DIFF = Carbon::now()->diffInMonths($date_hired);

                #ONLY ADD INCREMENT WHEN CURRENT DATE IS LAST DAY OF THE MONTH
                if (Carbon::now()->day !== $lastDayOfMonth->day) {
                    $MONTH_DIFF--;
                }

                $computed_balance = (($MONTH_DIFF * $type->monthly_increment) + ($base_credit - $used_credit));
                $leave_credits[] = [
                    'id' => $type->id,
                    'description' => $type->description,
                    'base_credit' => $base_credit !== null ? number_format($base_credit, 2) : '0.00',
                    'used_credit' => $used_credit !== null ? number_format($used_credit, 2) : '0.00',
                    'code' => $type->code,
                    'balance' => $computed_balance !== null ? number_format($computed_balance, 2) : '0.00'
                ];
            }

            return response()->json($leave_credits);
        } catch (\Exception $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }
    }

    public function insertUpdateLeaveType(Request $request)
    {
        try {
            DB::table('leave_types')->updateOrInsert([
                'description' => $request->description,
            ], [
                'code' => $request->code,
                'monthly_increment' => $request->monthly_increment,
                'credit_deduction' => $request->credit_deduction,
            ]);
            return response()->json(['message' => 'Leave type successfully stored']);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function getLeaveTypes(Request $request)
    {
        try {
            $leaves = DB::table('leave_types')
                ->get();;
            return response()->json($leaves);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function deleteLeaveType(Request $request)
    {
        try {
            DB::table('leave_types')
                ->where('id', '=', $request->id)
                ->delete();
            return response()->json(['message' => "Leave type successfully deleted"]);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }
}
