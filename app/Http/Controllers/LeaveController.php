<?php

namespace App\Http\Controllers;

use App\Models\LeaveFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
}
