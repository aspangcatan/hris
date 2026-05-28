<?php

namespace App\Http\Controllers;

use App\Models\LeaveApplication;
use App\Services\LeaveApplicationService;
use App\Services\LeaveApprovalService;
use App\Services\LeaveLogsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveApplicationController extends Controller
{
    protected LeaveLogsService $leaveLogService;
    protected LeaveApplicationService $leaveApplicationService;
    protected LeaveApprovalService $leaveApprovalService;

    public function __construct(LeaveLogsService $leaveLogsService, LeaveApplicationService $leaveApplicationService, LeaveApprovalService $leaveApprovalService)
    {
        $this->leaveLogService = $leaveLogsService;
        $this->leaveApplicationService = $leaveApplicationService;
        $this->leaveApprovalService = $leaveApprovalService;
    }

    public function index(Request $request)
    {
        try {
            $type = $request->search_type;

            $query = DB::table('leave_applications as la')
                ->join('leave_date_applied as lda', 'la.id', '=', 'lda.leave_application_id')
                ->join('leave_types as lt', 'lda.leave_type_id', '=', 'lt.id')
                ->where('la.user_id', Auth::id())
                ->when($type, fn($q) => $q->where('lt.description', 'LIKE', "%{$type}%"))
                ->orderBy('la.id', 'DESC')
                ->select('la.id', 'la.status')
                ->distinct()
                ->get();

            // Build one row per application with combined leave type descriptions
            $appIds = $query->pluck('id')->unique()->values();

            $rows = [];
            foreach ($appIds as $appId) {
                $typeDescriptions = DB::table('leave_date_applied as lda')
                    ->join('leave_types as lt', 'lda.leave_type_id', '=', 'lt.id')
                    ->where('lda.leave_application_id', $appId)
                    ->select('lt.description')
                    ->distinct()
                    ->get();

                $detailRow = DB::table('leave_details')
                    ->where('leave_application_id', $appId)
                    ->first(['detail', 'extra']);

                // Distinct dates only — hours differ per row but history just shows which days were taken
                $dateRows = DB::table('leave_date_applied')
                    ->where('leave_application_id', $appId)
                    ->orderBy('date_applied', 'ASC')
                    ->distinct()
                    ->get(['date_applied']);

                $appRow = $query->firstWhere('id', $appId);

                $shApproved = DB::table('leave_application_approvals')
                    ->where('leave_application_id', $appId)
                    ->where('role', 'SH')
                    ->whereRaw('LOWER(status) = ?', ['approved'])
                    ->exists();

                $rows[] = (object) [
                    'id'          => $appId,
                    'description' => implode(', ', $typeDescriptions->pluck('description')->unique()->toArray()),
                    'detail'      => $detailRow ? (string) ($detailRow->detail ?? '') : '',
                    'extra'       => $detailRow ? (string) ($detailRow->extra ?? '') : '',
                    'status'      => $appRow->status,
                    'dates_applied' => $dateRows,
                    'is_editable' => !$shApproved && strtolower($appRow->status) === 'pending',
                ];
            }

            $page       = max(1, (int) $request->input('page', 1));
            $perPage    = 10;
            $collection = collect($rows);
            $total      = $collection->count();
            $items      = $collection->forPage($page, $perPage)->values();
            $lastPage   = max(1, (int) ceil($total / $perPage));

            return response()->json([
                'results'    => $items,
                'pagination' => [
                    'current_page'   => $page,
                    'last_page'      => $lastPage,
                    'per_page'       => $perPage,
                    'total'          => $total,
                    'from'           => $total > 0 ? ($page - 1) * $perPage + 1 : null,
                    'to'             => $total > 0 ? min($page * $perPage, $total) : null,
                    'has_more_pages' => $page < $lastPage,
                ],
            ]);
        } catch (\Exception $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }
    }

    public function delete(Request $request)
    {
        try {
            $id = $request->id;
            $model = LeaveApplication::where(function ($query) use ($id) {
                $query->where('id', $id);
                $query->where('user_id', Auth::id());
            })->first();

            if (!$model) {
                return response()->json(['code' => 500, 'message' => 'Leave Application not found']);
            }

            if ($model->date_section_approved) {
                return response()->json(['code' => 500, 'message' => 'Cannot cancel/delete application, application already received']);
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

        $filter = in_array($request->input('filter'), ['pending', 'approved_by_stage', 'approved_all'], true)
            ? $request->input('filter')
            : 'pending';

        $leaves = $this->leaveApprovalService->getLeavesForApproval($request->search_employee, $filter);

        $total     = $leaves->count();
        $paginated = $leaves->forPage($page, $perPage)->values();

        return response()->json([
            'data' => $paginated,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => ceil($total / $perPage),
            ],
        ]);
    }

    public function updateApprovalStatus(Request $request)
    {
        try {
            $this->leaveApprovalService->updateApprovalStatus($request->id, null, $request->remarks, $request->status);
            return response()->json(['message' => 'Update status done']);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function approveLeave(Request $request)
    {
        try {
            $role = $this->leaveApprovalService->resolveCurrentApprovalRoleForLeave($request->id);
            $updated = 0;

            switch ($role) {
                case "SH":
                    $updated = $this->leaveLogService->updateStatus($request->id, '1', 'Approved');
                    $this->leaveLogService->insertLogs($request->id, Auth::user()->division, '2');
                    break;
                case "DH":
                    $updated = $this->leaveLogService->updateStatus($request->id, '2', 'Approved');
                    $this->leaveLogService->insertLogs($request->id, Auth::user()->division, '3');
                    break;
                case "HR":
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
            $logs = DB::table('hris.leave_application_logs')
                ->where('leave_application_id', '=', $request->id)
                ->select('type', 'remarks', 'updated_at as date_approved')
                ->orderBy('id', 'desc')
                ->get();

            return response()->json([
                'logs' => [
                    'section'  => $logs->where('type', 1)->first(),
                    'division' => $logs->where('type', 2)->first(),
                    'hr'       => $logs->where('type', 3)->first(),
                ],
            ]);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function insertLeaveApplication(Request $request)
    {
        try {
            $request->validate([
                'data'       => 'required|string',
                'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            ]);

            $params = json_decode($request->input('data'), true);
            if (!is_array($params)) {
                return response()->json(['success' => false, 'error' => 'Invalid request format.'], 422);
            }

            $validator = \Illuminate\Support\Facades\Validator::make($params, [
                'details_map'               => 'nullable|array',
                'details_map.*.detail'      => 'nullable|string',
                'details_map.*.extra'       => 'nullable|string',
                'commutation'               => 'nullable|string|in:Requested,Not Requested',
                'dates'                     => 'required|array|min:1',
                'dates.*.date_applied'      => 'required|date',
                'dates.*.hours'             => 'required|integer|in:4,8,10,12,16,24',
                'dates.*.leave_type_id'     => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
            }

            if ($request->hasFile('attachment')) {
                $params['attachment'] = $request->file('attachment')
                    ->store('leave_attachments', 'public');
            }

            $result = $this->leaveApplicationService->insertLeaveApplication($params);

            return response()->json([
                'success'              => true,
                'message'              => 'Leave application submitted successfully.',
                'leave_application_id' => $result['app_id'],
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'error'   => $exception->getMessage(),
            ], 422);
        }
    }

    public function getLeaveApplication(int $id)
    {
        $leave = $this->leaveApplicationService->findById($id);

        if (!$leave) {
            return response()->json(['error' => 'Leave application not found'], 404);
        }

        $detailsCollection = collect($leave->details ?? []);

        return response()->json([
            'name'            => trim($leave->fname . ' ' . ($leave->mname ? $leave->mname . ' ' : '') . $leave->lname),
            'type'            => $leave->leave_type,
            'details'         => $leave->detail,
            'extra'           => $leave->extra,
            'commutation'     => $leave->commutation,
            'created_at'      => $leave->created_at,
            'leave_ids'       => $detailsCollection->pluck('leave_id')->values(),
            'leave_types_map' => $detailsCollection->keyBy('leave_id')->map(fn($d) => $d->leave_type)->toArray(),
            'details_per_type' => $detailsCollection->map(fn($d) => [
                'leave_id' => $d->leave_id,
                'detail'   => $d->detail,
                'extra'    => $d->extra,
            ])->values(),
            'approvals'       => [
                'sh' => $leave->approvals['SH'] ?? ['status' => 'pending', 'remarks' => ''],
                'dh' => $leave->approvals['DH'] ?? ['status' => 'pending', 'remarks' => ''],
                'hr' => $leave->approvals['HR'] ?? ['status' => 'pending', 'remarks' => ''],
            ],
            'attachment_url'  => $leave->attachment ? route('leave-attachments.serve', ['filename' => basename($leave->attachment)]) : null,
            'can_undo'        => $this->leaveApprovalService->canUndoApproval($id),
            'dates'           => collect($leave->dates ?? [])->map(fn($d) => [
                'date'          => $d->date_applied,
                'duration'      => $d->hours,
                'leave_type_id' => $d->leave_type_id ?? null,
                'leave_type'    => $d->leave_type_name ?? '',
            ])->values(),
        ]);
    }

    public function serveAttachment(string $filename)
    {
        $path = storage_path('app/public/leave_attachments/' . $filename);

        if (!file_exists($path)) {
            abort(404);
        }

        return response()->file($path);
    }

    public function undoApproval(Request $request)
    {
        try {
            $this->leaveApprovalService->undoApproval((int) $request->id);
            return response()->json(['message' => 'Approval has been undone successfully.']);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 422);
        }
    }

    public function updateLeaveApplication(Request $request, int $id)
    {
        try {
            $validated = $request->validate([
                'details_map'                => 'nullable|array',
                'details_map.*.detail'       => 'nullable|string',
                'details_map.*.extra'        => 'nullable|string',
                'commutation'                => 'nullable|string|in:Requested,Not Requested',
                'dates'                      => 'required|array|min:1',
                'dates.*.date_applied'       => 'required|date',
                'dates.*.hours'              => 'required|integer|in:4,8,10,12,16,24',
                'dates.*.leave_type_id'      => 'required|integer',
            ]);

            $this->leaveApplicationService->updateLeaveApplication($id, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Leave application updated successfully.',
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'error'   => $exception->getMessage(),
            ], 422);
        }
    }
}
