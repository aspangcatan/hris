<?php

namespace App\Http\Controllers;

use App\Models\LeaveApplication;

use App\Services\LeaveApplicationService;
use App\Services\LeaveApprovalService;
use App\Services\LeaveLogsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveApplicationController extends Controller
{
    protected $leaveLogService, $leaveApplicationService, $leaveApprovalService;

    public function __construct(LeaveLogsService $leaveLogsService, LeaveApplicationService $leaveApplicationService, LeaveApprovalService $leaveApprovalService)
    {
        $this->leaveLogService = $leaveLogsService;
        $this->leaveApplicationService = $leaveApplicationService;
        $this->leaveApprovalService = $leaveApprovalService;
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
        $perPage = 10;
        $page = $request->input('page', 1);

        $leaves = $this->leaveApprovalService->getLeavesForApproval();

        // Paginate manually
        $total = $leaves->count();
        $paginated = $leaves->forPage($page, $perPage)->values();

        return response()->json([
            'data' => $paginated,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage)
            ]
        ]);
    }

    public function updateApprovalStatus(Request $request)
    {
        try {
            $this->leaveApprovalService->updateApprovalStatus($request->id, session('access_rights'), $request->remarks,$request->status);
            return response()->json(['message' => 'Update status done']);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
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
                    $this->leaveLogService->insertLogs($request->id, Auth::user()->division, '2');
                    break;
                case "DH":
                    $updated = $this->leaveLogService->updateStatus($request->id, '2', 'Approved');
                    $this->leaveLogService->insertLogs($request->id, Auth::user()->division, '3');
                    break;
                case "HR":
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

    /////////////////
    public function insertLeaveApplication(Request $request)
    {
        try {
            $validated = $request->validate([
                'leave_id' => 'required|integer|exists:leave_types,id',
                'detail' => 'nullable|string',
                'extra' => 'nullable|string',
                'commutation' => 'nullable|string|in:Requested,Not Requested',
                'dates' => 'required|array|min:1',
                'dates.*.date_applied' => 'required|date',
                'dates.*.hours' => 'required|numeric|min:0.5',
            ]);

            $leaveApplicationId = $this->leaveApplicationService->insertLeaveApplication($validated);
            return response()->json([
                'success' => true,
                'message' => 'Leave application submitted successfully.',
                'leave_application_id' => $leaveApplicationId
            ]);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function getLeaveApplication($id)
    {
        $leave = $this->leaveApplicationService->findById($id);

        if (!$leave) {
            return response()->json(['error' => 'Leave application not found'], 404);
        }

        return response()->json([
            'name' => trim($leave->fname . ' ' . ($leave->mname ? $leave->mname . ' ' : '') . $leave->lname),
            'type' => $leave->leave_type,
            'details' => $leave->detail,
            'commutation' => $leave->commutation,
            'created_at' => $leave->created_at,
            'approvals' => [
                'sh' => $leave->approvals['SH'] ?? ['status' => 'pending', 'remarks' => ''],
                'dh' => $leave->approvals['DH'] ?? ['status' => 'pending', 'remarks' => ''],
                'hr' => $leave->approvals['HR'] ?? ['status' => 'pending', 'remarks' => ''],
            ],
            'dates' => collect($leave->dates)->map(function ($d) {
                return [
                    'date' => $d->date_applied,
                    'duration' => $d->hours
                ];
            })->values()
        ]);
    }

}
