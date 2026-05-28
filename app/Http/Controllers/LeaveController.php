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
            $paginator = LeaveFile::where('user_id', Auth::id())
                ->orderBy('id', 'DESC')
                ->paginate(10);
            return response([
                'code' => 200,
                'results' => $paginator->items(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                    'has_more_pages' => $paginator->hasMorePages(),
                ]
            ]);
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
            $request->validate([
                'based_credit' => 'required|numeric|min:0',
            ]);

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
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => 'Credit value cannot be negative.'], 422);
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

            // Fetch all active leave types
            $leave_types = DB::table('leave_types')->where('status', 'active')->get(['id', 'description', 'code', 'days_prior', 'commutation']);

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
                    'id'           => $type->id,
                    'description'  => $type->description,
                    'based_credit' => number_format($based_credit, 3),
                    'used_credit'  => number_format($used_credit, 3),
                    'code'         => $type->code,
                    'days_prior'   => (int) ($type->days_prior ?? 0),
                    'commutation'  => (int) ($type->commutation ?? 0),
                    'balance'      => number_format($computed_balance, 3),
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
                'status' => $request->status ?? 'active',
                'monthly_earned' => $request->monthly_earned ?? 1,
            ]);
            return response()->json(['message' => 'Leave type successfully stored']);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function getLeaveTypes(Request $request)
    {
        try {
            $paginator = DB::table('leave_types')
                ->orderBy('description', 'ASC')
                ->paginate(10);
            return response()->json([
                'results' => $paginator->items(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                    'has_more_pages' => $paginator->hasMorePages(),
                ]
            ]);
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
