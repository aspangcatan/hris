<?php

namespace App\Http\Controllers;

use App\Models\LeaveApplication;
use App\Services\LeaveApplicationService;
use App\Services\LeaveApprovalService;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveApplicationController extends Controller
{
    protected LeaveApplicationService $leaveApplicationService;
    protected LeaveApprovalService $leaveApprovalService;
    protected PermissionService $permissions;

    public function __construct(LeaveApplicationService $leaveApplicationService, LeaveApprovalService $leaveApprovalService, PermissionService $permissions)
    {
        $this->leaveApplicationService = $leaveApplicationService;
        $this->leaveApprovalService = $leaveApprovalService;
        $this->permissions = $permissions;
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
                    'is_editable'  => !$shApproved && strtolower($appRow->status) === 'pending',
                    'is_deletable' => strtolower($appRow->status) !== 'approved',
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

    public function delete(Request $request, $id = null)
    {
        try {
            $id = $id ?? $request->id;

            $model = LeaveApplication::where('id', $id)
                ->where('user_id', Auth::id())
                ->first();

            if (!$model) {
                return response()->json(['code' => 404, 'message' => 'Leave application not found.'], 404);
            }

            if (strtolower($model->status) === 'approved') {
                return response()->json(['code' => 422, 'message' => 'Cannot delete an approved application. Use the leave cancellation form instead.'], 422);
            }

            DB::transaction(function () use ($model) {
                // Restore credits only if not already restored (rejection restores them at rejection time)
                if (strtolower($model->status) !== 'rejected') {
                    $dates = DB::table('leave_date_applied')
                        ->where('leave_application_id', $model->id)
                        ->get(['leave_type_id', 'credits_deducted']);

                    $creditsByType = $dates->groupBy('leave_type_id');
                    foreach ($creditsByType as $typeId => $rows) {
                        if (!$typeId) continue;
                        DB::table('leave_credits')
                            ->where('user_id', $model->user_id)
                            ->where('leave_id', $typeId)
                            ->increment('based_credit', $rows->sum('credits_deducted'));
                    }
                }

                $model->forceDelete();
            });

            return response()->json(['code' => 200, 'message' => 'Leave application successfully deleted.']);
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
            $validated = $request->validate([
                'disapproved_dates'                   => 'nullable|array',
                'disapproved_dates.*.date_applied_id' => 'required|integer',
                'disapproved_dates.*.remark'          => 'nullable|string|max:255',
                'signature'                            => 'nullable|string',
            ]);

            $this->leaveApprovalService->updateApprovalStatus(
                $request->id,
                null,
                $request->remarks,
                $request->status,
                $validated['disapproved_dates'] ?? [],
                $validated['signature'] ?? null
            );
            return response()->json(['message' => 'Update status done']);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function insertLeaveApplication(Request $request)
    {
        try {
            $request->validate([
                'data'          => 'required|string',
                'attachments'   => 'nullable|array',
                'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png|max:5120',
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
                'applicant_signature'       => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
            }

            $result = $this->leaveApplicationService->insertLeaveApplication($params);
            $appId  = $result['app_id'];

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('leave_attachments', 'public');
                    DB::table('leave_application_attachments')->insert([
                        'leave_application_id' => $appId,
                        'file_path'            => $path,
                        'original_name'        => $file->getClientOriginalName(),
                        'created_at'           => now(),
                        'updated_at'           => now(),
                    ]);
                }
            }

            return response()->json([
                'success'              => true,
                'message'              => 'Leave application submitted successfully.',
                'leave_application_id' => $appId,
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'error'   => $exception->getMessage(),
            ], 422);
        }
    }

    public function addAttachments(Request $request, int $id)
    {
        try {
            $request->validate([
                'attachments'   => 'required|array|min:1',
                'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png|max:5120',
            ]);

            $app = DB::table('leave_applications')
                ->where('id', $id)
                ->where('user_id', \Illuminate\Support\Facades\Auth::id())
                ->first(['id', 'status']);

            if (!$app) {
                return response()->json(['error' => 'Leave application not found.'], 404);
            }

            $shApproved = DB::table('leave_application_approvals')
                ->where('leave_application_id', $id)
                ->where('role', 'SH')
                ->whereRaw('LOWER(status) = ?', ['approved'])
                ->exists();

            if ($shApproved) {
                return response()->json(['error' => 'Cannot modify attachments after supervisor approval.'], 422);
            }

            $stored = [];
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('leave_attachments', 'public');
                $rowId = DB::table('leave_application_attachments')->insertGetId([
                    'leave_application_id' => $id,
                    'file_path'            => $path,
                    'original_name'        => $file->getClientOriginalName(),
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
                $stored[] = [
                    'id'            => $rowId,
                    'url'           => route('leave-attachments.serve', ['filename' => basename($path)]),
                    'original_name' => $file->getClientOriginalName(),
                ];
            }

            return response()->json(['message' => 'Attachments uploaded.', 'attachments' => $stored]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function deleteAttachment(Request $request, int $attachmentId)
    {
        try {
            $row = DB::table('leave_application_attachments')
                ->join('leave_applications', 'leave_applications.id', '=', 'leave_application_attachments.leave_application_id')
                ->where('leave_application_attachments.id', $attachmentId)
                ->where('leave_applications.user_id', \Illuminate\Support\Facades\Auth::id())
                ->first(['leave_application_attachments.id', 'leave_application_attachments.file_path']);

            if (!$row) {
                return response()->json(['error' => 'Attachment not found.'], 404);
            }

            \Illuminate\Support\Facades\Storage::disk('public')->delete($row->file_path);
            DB::table('leave_application_attachments')->where('id', $attachmentId)->delete();

            return response()->json(['message' => 'Attachment deleted.']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
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
            'attachments'     => DB::table('leave_application_attachments')
                ->where('leave_application_id', $id)
                ->get()
                ->map(fn($a) => [
                    'id'            => $a->id,
                    'url'           => route('leave-attachments.serve', ['filename' => basename($a->file_path)]),
                    'original_name' => $a->original_name ?? basename($a->file_path),
                ])->values(),
            'can_undo'              => $this->leaveApprovalService->canUndoApproval($id),
            'current_approval_role' => $this->leaveApprovalService->resolveCurrentApprovalRoleForLeave($id),
            'dates'                 => collect($leave->dates ?? [])->map(fn($d) => [
                'id'                    => $d->id,
                'date'                  => $d->date_applied,
                'duration'              => $d->hours,
                'leave_type_id'         => $d->leave_type_id ?? null,
                'leave_type'            => $d->leave_type_name ?? '',
                'is_cancelled'          => ($d->status ?? '') === 'cancelled',
                'sh_flagged_disapprove' => (bool) ($d->sh_flagged_disapprove ?? false),
                'sh_disapproval_remark' => $d->sh_disapproval_remark ?? null,
                'final_disapproved'     => (bool) ($d->final_disapproved ?? false),
                'dh_disapproval_remark' => $d->dh_disapproval_remark ?? null,
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
                'applicant_signature'        => 'required|string',
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
