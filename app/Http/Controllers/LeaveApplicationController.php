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
            $type    = $request->search_type;
            $isLwop  = $request->boolean('is_lwop');

            $query = DB::table('leave_applications as la')
                ->join('leave_date_applied as lda', 'la.id', '=', 'lda.leave_application_id')
                ->join('leave_types as lt', 'lda.leave_type_id', '=', 'lt.id')
                ->where('la.user_id', Auth::id())
                ->where('la.is_lwop', $isLwop)
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

                // Same rule as LeaveApplicationService::updateLeaveApplication
                $anyStepActed = DB::table('leave_application_approvals')
                    ->where('leave_application_id', $appId)
                    ->whereRaw('LOWER(status) <> ?', ['pending'])
                    ->exists();

                $rows[] = (object) [
                    'id'          => $appId,
                    'description' => implode(', ', $typeDescriptions->pluck('description')->unique()->toArray()),
                    'detail'      => $detailRow ? (string) ($detailRow->detail ?? '') : '',
                    'extra'       => $detailRow ? (string) ($detailRow->extra ?? '') : '',
                    'status'      => $appRow->status,
                    'dates_applied' => $dateRows,
                    'is_editable'  => !$anyStepActed &&strtolower($appRow->status) === 'pending',
                    'is_deletable' => $this->leaveApplicationService->canDelete($appId),
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

            $filePaths = DB::transaction(function () use ($model) {
                // Re-check under lock: only before any approver acts, or once completely rejected.
                DB::table('leave_applications')->where('id', $model->id)->lockForUpdate()->value('id');
                if (!$this->leaveApplicationService->canDelete((int) $model->id)) {
                    throw new \DomainException('This leave application is being processed by an approver and can only be deleted once it has been fully rejected.');
                }
                $model->refresh();

                // Restore credits only if not already restored (rejection restores them at rejection time)
                // DH-disapproved and cancelled dates already had their credits given back.
                if (strtolower($model->status) !== 'rejected') {
                    $dates = DB::table('leave_date_applied')
                        ->where('leave_application_id', $model->id)
                        ->where('final_disapproved', 0)
                        ->where('status', '!=', 'cancelled')
                        ->get(\App\Services\LeaveDateCharges::COLUMNS);

                    // Each part goes back to the balance it came from (SL -> VL fallback included).
                    foreach (\App\Services\LeaveDateCharges::byType($dates) as $typeId => $amount) {
                        DB::table('leave_credits')
                            ->where('user_id', $model->user_id)
                            ->where('leave_id', $typeId)
                            ->increment('based_credit', $amount);
                    }
                }

                // Remove everything that belongs to the application, not just its row.
                $filePaths = $this->leaveApplicationService->detachAttachments($model->id);
                DB::table('leave_details')->where('leave_application_id', $model->id)->delete();
                DB::table('leave_date_applied')->where('leave_application_id', $model->id)->delete();
                DB::table('leave_application_approvals')->where('leave_application_id', $model->id)->delete();

                $model->forceDelete();

                return $filePaths;
            });

            // Files go only after the database delete has committed.
            $this->leaveApplicationService->deleteAttachmentFiles($filePaths);

            return response()->json(['code' => 200, 'message' => 'Leave application successfully deleted.']);
        } catch (\DomainException $exception) {
            return response()->json(['code' => 422, 'message' => $exception->getMessage()], 422);
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
                'cert_as_of'                           => 'nullable|date',
                'approved_days_with_pay'               => 'nullable|numeric|min:0',
                'approved_days_without_pay'            => 'nullable|numeric|min:0',
                'approved_others'                      => 'nullable|string|max:255',
            ]);

            // Resolve before acting - once acknowledged there is no current step left
            $isHrStep = $this->leaveApprovalService->resolveCurrentApprovalRoleForLeave((int) $request->id) === 'HR';

            $this->leaveApprovalService->updateApprovalStatus(
                $request->id,
                null,
                $request->remarks,
                $request->status,
                $validated['disapproved_dates'] ?? [],
                $validated['signature'] ?? null,
                $request->boolean('overturn_rejection')
            );

            // Section 7.A / 7.C of the leave form are HR's to fill in
            if ($isHrStep) {
                DB::table('leave_applications')->where('id', $request->id)->update([
                    'cert_as_of'                => $validated['cert_as_of'] ?? null,
                    'approved_days_with_pay'    => $validated['approved_days_with_pay'] ?? null,
                    'approved_days_without_pay' => $validated['approved_days_without_pay'] ?? null,
                    'approved_others'           => $validated['approved_others'] ?? null,
                    'updated_at'                => now(),
                ]);
            }

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
                'dates.*.date_applied'      => 'required|date_format:Y-m-d',
                'dates.*.hours'             => 'required|integer|in:4,8,10,12,16,24',
                'dates.*.leave_type_id'     => 'required|integer',
                'applicant_signature'       => 'required|string',
                'is_lwop'                   => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
            }

            // Maternity/Paternity: the supporting document is part of the filing itself.
            $typeIds = collect($params['dates'])->pluck('leave_type_id')->filter()->unique()->values()->all();
            if ($this->leaveApplicationService->requiresAttachment($typeIds) && !$request->hasFile('attachments')) {
                return response()->json(['success' => false, 'error' => 'At least one attachment is required for Maternity/Paternity Leave.'], 422);
            }

            // The filing, its attachments and the compliance step are saved together: if any
            // part fails, nothing is kept - no half-filed leave, no stray files.
            $storedFiles = [];
            try {
                $appId = DB::transaction(function () use ($params, $request, &$storedFiles) {
                    $appId = $this->leaveApplicationService->insertLeaveApplication($params)['app_id'];

                    foreach ($request->file('attachments') ?? [] as $file) {
                        $path = $file->store('leave_attachments', 'public');
                        if (!$path) {
                            throw new \RuntimeException('An attachment could not be saved. Please try again.');
                        }
                        $storedFiles[] = $path;
                        DB::table('leave_application_attachments')->insert([
                            'leave_application_id' => $appId,
                            'file_path'            => $path,
                            'original_name'        => $file->getClientOriginalName(),
                            'created_at'           => now(),
                            'updated_at'           => now(),
                        ]);
                    }

                    $this->leaveApplicationService->syncComplianceStep($appId);

                    return $appId;
                });
            } catch (\Throwable $e) {
                $this->leaveApplicationService->deleteAttachmentFiles($storedFiles);
                throw $e;
            }

            return response()->json([
                'success'              => true,
                'message'              => 'Leave application submitted successfully.',
                'leave_application_id' => $appId,
            ]);
        } catch (\Illuminate\Database\QueryException $exception) {
            report($exception);
            return response()->json([
                'success' => false,
                'error'   => 'Your leave application could not be saved. Nothing was filed - please try again.',
            ], 422);
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

            // Chains for heads may have no SH step, so lock on any approver having acted.
            $anyStepActed = DB::table('leave_application_approvals')
                ->where('leave_application_id', $id)
                ->whereRaw('LOWER(status) <> ?', ['pending'])
                ->exists();

            if ($anyStepActed) {
                return response()->json(['error' => 'Cannot modify attachments after an approver has acted.'], 422);
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

            $this->leaveApplicationService->syncComplianceStep($id);

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
                ->first(['leave_application_attachments.id', 'leave_application_attachments.file_path', 'leave_application_attachments.leave_application_id']);

            if (!$row) {
                return response()->json(['error' => 'Attachment not found.'], 404);
            }

            \Illuminate\Support\Facades\Storage::disk('public')->delete($row->file_path);
            DB::table('leave_application_attachments')->where('id', $attachmentId)->delete();

            $this->leaveApplicationService->syncComplianceStep($row->leave_application_id);

            return response()->json(['message' => 'Attachment deleted.']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getLeaveApplication(int $id)
    {
        if (!$this->leaveApprovalService->canViewLeave($id)) {
            return response()->json(['error' => 'Leave application not found'], 404);
        }

        $leave = $this->leaveApplicationService->findById($id);

        if (!$leave) {
            return response()->json(['error' => 'Leave application not found'], 404);
        }

        $detailsCollection = collect($leave->details ?? []);

        // The employee's plotted schedule on each leave date, so viewers can check that the
        // hours filed match the schedule (a 10-hour day should be filed as 10 hours).
        $schedules = $this->workSchedulesFor(
            (int) $leave->user_id,
            collect($leave->dates ?? [])->pluck('date_applied')->map(fn($d) => \Carbon\Carbon::parse($d)->toDateString())->all()
        );

        return response()->json([
            'name'            => trim($leave->fname . ' ' . ($leave->mname ? $leave->mname . ' ' : '') . $leave->lname),
            'type'            => $leave->leave_type,
            'details'         => $leave->detail,
            'extra'           => $leave->extra,
            'commutation'     => $leave->commutation,
            'is_lwop'         => (bool) $leave->is_lwop,
            'status'          => $leave->status,
            'hr_action'       => DB::table('leave_applications')->where('id', $id)
                ->first(['cert_as_of', 'approved_days_with_pay', 'approved_days_without_pay', 'approved_others']),
            'created_at'      => $leave->created_at,
            // Includes VL when part of a sick leave was charged to VL (SL ran out).
            'credit_balances' => array_values($this->leaveApplicationService->getCreditCertification(
                $leave,
                collect($leave->dates ?? [])->pluck('leave_type_id')
                    ->merge(collect($leave->dates ?? [])->pluck('fallback_leave_type_id'))
                    ->filter()->unique()->values()->all()
            )),
            'leave_ids'       => $detailsCollection->pluck('leave_id')->values(),
            'leave_types_map' => $detailsCollection->keyBy('leave_id')->map(fn($d) => $d->leave_type)->toArray(),
            'details_per_type' => $detailsCollection->map(fn($d) => [
                'leave_id' => $d->leave_id,
                'detail'   => $d->detail,
                'extra'    => $d->extra,
            ])->values(),
            'approvals'       => array_filter([
                'hr_compliance' => $leave->approvals['HR_COMPLIANCE'] ?? null,
                // A head's own leave has no SH (or, for the Medical Center Chief, no DH) step
                'sh'            => $leave->approvals['SH'] ?? ['status' => 'skipped', 'remarks' => ''],
                'dh'            => $leave->approvals['DH'] ?? ['status' => 'skipped', 'remarks' => ''],
                'hr'            => $leave->approvals['HR'] ?? ['status' => 'pending', 'remarks' => ''],
            ]),
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
                // 'days' = filed per day (8 = whole day, 4 = half day), not by hours
                'deduction_unit'        => $d->deduction_unit ?? 'hours',
                'work_schedule'         => $schedules[\Carbon\Carbon::parse($d->date_applied)->toDateString()] ?? null,
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

    /**
     * Plotted schedule per date: code, time range and working hours (span minus the
     * scheduled break). OFF dates are rest days. Dates with nothing plotted are left out.
     *
     * @param  string[] $dates Y-m-d
     * @return array<string, array{code: ?string, label: ?string, time_in: ?string, time_out: ?string, hours: float, is_off: bool}>
     */
    private function workSchedulesFor(int $userId, array $dates): array
    {
        if (empty($dates)) {
            return [];
        }

        $rows = DB::table('hris.schedules as s')
            ->join('hris.schedule_master_lists as sml', 's.schedule_id', '=', 'sml.id')
            ->where('s.employee_id', $userId)
            ->whereIn('s.schedule_date', $dates)
            ->get(['s.schedule_date', 'sml.code', 'sml.label', 'sml.time_in', 'sml.time_out', 'sml.break_out', 'sml.break_in']);

        $result = [];
        foreach ($rows as $row) {
            $date = \Carbon\Carbon::parse($row->schedule_date)->toDateString();
            $isOff = strtoupper(trim((string) $row->code)) === 'OFF';
            $hours = 0.0;

            if (!$isOff && $row->time_in && $row->time_out) {
                $in = \Carbon\Carbon::parse("$date {$row->time_in}");
                $out = \Carbon\Carbon::parse("$date {$row->time_out}");
                if ($out->lte($in)) {
                    $out->addDay(); // overnight shift
                }
                $minutes = $in->diffInMinutes($out);

                if ($row->break_out && $row->break_in) {
                    $breakOut = \Carbon\Carbon::parse("$date {$row->break_out}");
                    $breakIn = \Carbon\Carbon::parse("$date {$row->break_in}");
                    if ($breakOut->lt($in)) {
                        $breakOut->addDay();
                    }
                    if ($breakIn->lte($breakOut)) {
                        $breakIn->addDay();
                    }
                    $overlapStart = $breakOut->copy()->max($in);
                    $overlapEnd = $breakIn->copy()->min($out);
                    if ($overlapEnd->gt($overlapStart)) {
                        $minutes -= $overlapStart->diffInMinutes($overlapEnd);
                    }
                }
                $hours = round($minutes / 60, 2);
            }

            $result[$date] = [
                'code'     => $row->code,
                'label'    => $row->label,
                'time_in'  => $isOff ? null : \Carbon\Carbon::parse($row->time_in)->format('g:i A'),
                'time_out' => $isOff ? null : \Carbon\Carbon::parse($row->time_out)->format('g:i A'),
                'hours'    => $hours,
                'is_off'   => $isOff,
            ];
        }

        return $result;
    }

    public function serveAttachment(string $filename)
    {
        // Only a plain file name; a backslash/".." could otherwise climb out of the folder on Windows.
        $filename = basename(str_replace('\\', '/', $filename));
        $stored   = 'leave_attachments/' . $filename;

        // The file must belong to a leave application the viewer is allowed to see.
        $appId = DB::table('leave_application_attachments')->where('file_path', $stored)->value('leave_application_id')
            ?? DB::table('leave_applications')->whereIn('attachment', [$stored, $filename, 'public/' . $stored])->value('id');

        if (!$appId || !$this->leaveApprovalService->canViewLeave((int) $appId)) {
            abort(404);
        }

        $path = storage_path('app/public/' . $stored);
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
                'dates.*.date_applied'       => 'required|date_format:Y-m-d',
                'dates.*.hours'              => 'required|integer|in:4,8,10,12,16,24',
                'dates.*.leave_type_id'      => 'required|integer',
                'applicant_signature'        => 'required|string',
                'is_lwop'                    => 'sometimes|boolean',
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
