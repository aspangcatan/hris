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
                    'based_credit' => $request->based_credit
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
            // Get the user ID from request or use the authenticated user
            $userid = $request->has('id') ? $request->id : Auth::id();

            // Fetch all leave types
            $leave_types = DB::table('leave_types')->get(['id', 'description', 'code', 'monthly_points_earned']);

            // Initialize leave credits array
            $leave_credits = [];

            foreach ($leave_types as $type) {
                $leave_id = $type->id;
                $based_credit = 0.00;
                $used_credit = 0.00;

                // Get the leave credit balance for the user
                $credit = DB::table('leave_credits')
                    ->where('user_id', '=', $userid)
                    ->where('leave_id', '=', $leave_id)
                    ->first();

                if ($credit) {
                    $based_credit = $credit->based_credit;
                    $used_credit = $credit->used_credit;
                }

                // Compute balance directly from database values
                $computed_balance = $based_credit - $used_credit;

                $leave_credits[] = [
                    'id' => $type->id,
                    'description' => $type->description,
                    'based_credit' => number_format($based_credit, 2),
                    'used_credit' => number_format($used_credit, 2),
                    'code' => $type->code,
                    'balance' => number_format($computed_balance, 2)
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
                'monthly_points_earned' => $request->monthly_points_earned,
                'minute_deduction' => $request->minute_deduction,
                'day_deduction' => $request->day_deduction,
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
