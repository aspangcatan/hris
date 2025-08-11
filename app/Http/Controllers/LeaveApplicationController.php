<?php

namespace App\Http\Controllers;

use App\Models\LeaveApplication;

use App\Services\LeaveApplicationService;
use App\Services\LeaveLogsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveApplicationController extends Controller
{
    protected $leaveLogService, $leaveApplicationService;

    public function __construct(LeaveLogsService $leaveLogsService, LeaveApplicationService $leaveApplicationService)
    {
        $this->leaveLogService = $leaveLogsService;
        $this->leaveApplicationService = $leaveApplicationService;
    }

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

            //TODO: INSERT LEAVE LOGS SEND TO SECTION HEAD
            DB::table("hris.leave_application_logs")->insert([
                "leave_application_id" => $leave_id,
                "origin" => Auth::id(),
                "destination" => Auth::user()->section,
                "type" => 1,//1 for Section Head
                "remarks" => "For approval",
                "created_at" => Carbon::now()
            ]);

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
            $accessRight = session('access_rights'); // Retrieve access rights from session
            $leaveType = null;

            // Determine leaveType based on access right (except admin)
            switch ($accessRight) {
                case 'SH':
                    $leaveType = 1;
                    break;
                case 'DH':
                    $leaveType = 2;
                    break;
                case 'HRH':
                    $leaveType = 3;
                    break;
                case 'admin':
                    $leaveType = null; // Admin sees all
                    break;
            }

            $query = DB::table('hris.leave_application_logs as l')
                ->join('hris.leave_applications as a', 'a.id', '=', 'l.leave_application_id')
                ->join('tdh_user.users as u', 'l.origin', '=', 'u.id')
                ->leftJoin('hris.leave_date_applied as d', 'a.id', '=', 'd.leave_application_id')
                ->leftJoin('hris.leave_types as t', 'a.leave_id', '=', 't.id')
                ->where('l.destination', Auth::user()->section)

                // Ensure only latest record per leave application is considered
                ->whereRaw('l.id = (SELECT id FROM hris.leave_application_logs WHERE leave_application_id = l.leave_application_id ORDER BY id DESC LIMIT 1)')

                // Exclude already approved leaves
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('hris.leave_application_logs as sub_l')
                        ->whereRaw('sub_l.leave_application_id = l.leave_application_id')
                        ->where('sub_l.remarks', 'Approved');
                });

            // Apply leaveType filter only if not admin
            if ($leaveType !== null) {
                $query->where('l.type', $leaveType);
            }

            $results = $query->groupBy(
                'l.id', 'a.id', 'u.id', 'l.leave_application_id', 'l.origin',
                'l.destination', 'l.type', 'a.status', 't.description',
                'u.fname', 'u.mname', 'u.lname'
            )
                ->select(
                    'l.id',
                    'u.fname',
                    'u.mname',
                    'u.lname',
                    't.description',
                    'a.status',
                    DB::raw("GROUP_CONCAT(DISTINCT d.date_applied ORDER BY d.date_applied ASC SEPARATOR ', ') as date_applied")
                )
                ->skip($request->page * 10)
                ->take(11)
                ->get();

            return response()->json($results);

        } catch (\Exception $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }
    }


    public function approveLeave(Request $request)
    {
        try {
            $role = session('access_rights');
            $updated = 0;

            switch ($role) {
                case "SH":
                case "admin": // SPECIAL CASE SINCE JIMMY IS OUR SECTION HEAD AND ONLY HIM IS ADMIN
                    $updated = $this->leaveLogService->updateStatus($request->id, '1', 'Approved');
                    break;
                case "DH":
                    $updated = $this->leaveLogService->updateStatus($request->id, '2', 'Approved');
                    break;
                case "HRH":
                    $updated = $this->leaveLogService->updateStatus($request->id, '3', 'Approved');
                    $this->leaveApplicationService->updateStatus($request->id, 'Approved');
                    break;
            }

            if ($updated === 0) {
                return response()->json(['message' => 'No matching leave record found.'], 404);
            }

            return response()->json(['message' => 'Leave successfully approved']);

        } catch (\Exception $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }
    }


    //TODO: GET LEAVE APPLICATION LOGS WiTH THIS OUTPUT IN MIND
    //SECTION    STATUS   REMARKS   DATE APPROVED
    //DIVISION   STATUS   REMARKS   DATE APPROVED
    //HR         STATUS   REMARKS   DATE APPROVED

    public function getApplicationLogs(Request $request)
    {
        try {
            // Fetch logs separately and categorize by type
            $logs = DB::table('hris.leave_application_logs')
                ->where('leave_application_id', '=', $request->id)
                ->select('type', 'remarks', 'remarks', 'updated_at as date_approved')
                ->orderBy('id', 'desc') // Ensure latest logs come first
                ->get();

            // Categorize logs
            $section = $logs->where('type', 1)->first();
            $division = $logs->where('type', 2)->first();
            $hr = $logs->where('type', 3)->first();

            return response()->json([
                'logs' => [
                    'section' => $section,
                    'division' => $division,
                    'hr' => $hr
                ]
            ]);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }
}
