<?php

namespace App\Http\Controllers;

use App\Models\LeaveApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeaveApplicationController extends Controller
{
    public function store(Request $request)
    {
        try {
            $id = $request->id;
            $model = LeaveApplication::where(function ($query) use ($id) {
                $query->where('id', $id);
                $query->where('user_id', Auth::id());
            })->first();

            if (!$model) {
                $model = new LeaveApplication();
                $model->user_id = Auth::id();
            }

            $model->leave_type = $request->leave_type;
            $model->detail = $request->detail;
            $model->extra = $request->extra;
            $model->date_applied = $request->date_applied;
            $model->commutation = $request->commutation;
            $model->status = "FOR APPROVAL";
            $model->section_id = Auth::user()->section;
            $model->division_id = Auth::user()->division;
            $model->hr_id = 15;
            $model->save();
            return response()->json(['message' => 'Application filed successfully']);
        } catch (\Exception $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $type = $request->search_type;
            $model = LeaveApplication::where(function ($query) use ($type) {
                if ($type != "") {
                    $query->where('leave_type', 'LIKE', '%' . $type . '%');
                }
            })
                ->skip($request->page * 10)
                ->take(11)
                ->orderBy('id', 'DESC')
                ->get();

            return response()->json($model);
        } catch (\Exception $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }
    }

    public function delete(Request $request)
    {
        try {
            #BEFORE DELETING/CANCELING, CHECK IF LEAVE EXISTS
            $id = $request->id;
            $model = LeaveApplication::where(function ($query) use ($id) {
                $query->where('id', $id);
                $query->where('user_id', Auth::id());
            })->first();

            if (!model) {
                return response()->json(['code' => 401, 'message' => 'Leave Application not found']);
            }

            #CHECK IF APPLICATION ARE NOT ALREADY RECEIVED
            if ($model->date_section_approved) {
                return response()->json(['code' => 500, 'message' => 'Cannot cancel/delete application, application already received'],);
            }

            $model->forceDelete();
            return response()->json(['message' => 'Leave successfully cancelled']);
        } catch (\Exception $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }
    }

    public function getLeaveForApproval(Request $request)
    {
        try {
            $model = LeaveApplication::where(function ($query) {
                #CHECK USER ROLE IF SECTION HEAD,DIVISION HEAD OR HR
                #ROLE- 1(SECTION),2(DIVISION), 3(HR)
                $role = "1";
                switch ($role) {
                    case "1":
                        $query->where('section_id', Auth::user()->section);
                        $query->whereNull('date_section_approved');
                        break;
                    case "2":
                        $query->where('division_id', Auth::user()->division);
                        $query->whereNull('date_division_approved');
                        break;
                    case "3":
                        $query->where('hr_id', Auth::user()->section);
                        $query->whereNull('date_hr_approved');
                        break;
                }
            })
                ->skip($request->page * 10)
                ->take(11)
                ->get();
            return response()->json($model);
        } catch (\Exception $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }
    }
}
