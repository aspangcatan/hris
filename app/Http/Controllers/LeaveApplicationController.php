<?php

namespace App\Http\Controllers;

use App\Models\LeaveApplication;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveApplicationController extends Controller
{
    public function store(Request $request)
    {
        try {
            $model = new LeaveApplication();
            $model->user_id = Auth::id();
            $model->leave_id = $request->leave_id;
            $model->detail = $request->detail;
            $model->extra = $request->extra;
            $model->status = 'FOR APPROVAL';
            $model->commutation = $request->commutation;
            $model->save();

            $leave_id = $model->id;
            $date_applied = $request->date_applied;
            for ($i = 0; $i < count($date_applied); $i++) {
                DB::table('leave_date_applied')->insert([
                    'leave_application_id' => $leave_id,
                    'date_applied' => $date_applied[$i]["date"],
                    'hours' => $date_applied[$i]["hours"]
                ]);
            }

            return response()->json(['code' => 200, 'message' => 'Application filed successfully']);
        } catch (\Exception $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $type = $request->search_type;
            $models = DB::table('leave_applications as l')
                ->join('leave_types as t', 'l.leave_id', '=', 't.id')
                ->where(function ($query) use ($type) {
                    $query->where('user_id', '=', Auth::id());
                    if ($type != "") {
                        $query->where('leave_type', 'LIKE', '%' . $type . '%');
                    }
                })
                ->skip($request->page * 10)
                ->take(11)
                ->orderBy('id', 'DESC')
                ->get(['l.id', 't.description', 'l.detail', 'l.extra', 'l.status']);


            foreach ($models as $model) {
                $dates_applied = DB::table('leave_date_applied')->where('leave_application_id', $model->id)->orderBy('date_applied', 'ASC')->get(['date_applied']);
                $model->dates_applied = $dates_applied;
            }

            return response()->json($models);
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
                return response()->json(['code' => 500, 'message' => 'Leave Application not found']);
            }

            #CHECK IF APPLICATION ARE NOT ALREADY RECEIVED
            if ($model->date_section_approved) {
                return response()->json(['code' => 500, 'message' => 'Cannot cancel/delete application, application already received'],);
            }

            $model->forceDelete();
            return response()->json(['code' => 200, 'message' => 'Leave successfully cancelled']);
        } catch (\Exception $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }
    }

    public function getLeaveForApproval(Request $request)
    {
        try {
            $model = DB::table('hris.leave_applications as l')
                ->join('tdh_user.users as u', 'l.user_id', '=', 'u.id')
                ->where(function ($query) {
                    $role = session('access_rights');
                    switch ($role) {
                        case "SH":
                            $query->where('section_id', Auth::user()->section);
                            $query->whereNull('date_section_approved');
                            break;
                        case "DH":
                            $query->where('division_id', Auth::user()->division);
                            $query->whereNotNull('date_section_approved');
                            $query->whereNull('date_division_approved');
                            break;
                        case "HRH":
                            $query->whereNotNull('date_section_approved');
                            $query->whereNotNull('date_division_approved');
                            $query->whereNull('date_hr_approved');
                            break;
                    }
                })
                ->skip($request->page * 10)
                ->take(11)
                ->get(['l.id', 'u.fname', 'u.mname', 'u.lname', 'l.leave_type', 'l.date_applied', 'l.status', 'l.date_section_approved', 'l.date_division_approved', 'l.date_hr_approved']);
            return response()->json($model);
        } catch (\Exception $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }
    }

    public function approveLeave(Request $request)
    {
        try {
            $role = session('access_rights');
            $model = LeaveApplication::find($request->id);
            switch ($role) {
                case "SH":
                    $model->date_section_approved = Carbon::now();
                    break;
                case "DH":
                    $model->date_division_approved = Carbon::now();
                    break;
                case "HRH":
                    $model->date_hr_approved = Carbon::now();
                    $model->status = "APPROVED";
                    break;
            }
            $model->save();
            return response()->json(['message' => "Leave successfully approved"]);
        } catch (\Exception $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }
    }
}
