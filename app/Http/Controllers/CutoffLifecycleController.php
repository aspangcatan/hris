<?php

namespace App\Http\Controllers;

use App\Services\CutoffLifecycleService;
use App\Services\CutoffSnapshotService;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Finalize / reopen a cutoff (Cutoff Summary page). See CutoffLifecycleService.
 */
class CutoffLifecycleController extends Controller
{
    private CutoffLifecycleService $lifecycle;
    private CutoffSnapshotService $snapshots;
    private PermissionService $permissions;

    public function __construct(CutoffLifecycleService $lifecycle, CutoffSnapshotService $snapshots, PermissionService $permissions)
    {
        $this->lifecycle = $lifecycle;
        $this->snapshots = $snapshots;
        $this->permissions = $permissions;
    }

    /** Status, pre-check issues (draft only), whether it can be reopened, and history. */
    public function status($cutoffId)
    {
        try {
            $master = $this->lifecycle->find((int) $cutoffId);
            if (!$master) {
                return response()->json(['success' => false, 'message' => 'Cutoff not found.'], 404);
            }
            $status = $this->lifecycle->statusOf($master);
            $adjustments = app(\App\Services\CutoffAdjustmentService::class);
            if (in_array($status, [CutoffLifecycleService::FINALIZED, CutoffLifecycleService::CLOSED], true)) {
                $adjustments->detect((int) $cutoffId); // flags leave changed after finalizing
            }
            $beforeFinal = in_array($status, [CutoffLifecycleService::DRAFT, CutoffLifecycleService::PROCESSING, CutoffLifecycleService::FOR_REVIEW], true);
            $userId = (int) Auth::id();

            return response()->json([
                'success' => true,
                'status' => $status,
                'finalized_at' => $master->finalized_at ?? null,
                'payroll_date' => $master->payroll_date ?? null,
                'remarks' => $master->remarks ?? null,
                'cutoff_type' => $master->cutoff_type ?? null,
                'precheck' => $beforeFinal ? $this->lifecycle->precheck((int) $cutoffId) : null,
                'reopen_blocker' => $beforeFinal ? null : $this->lifecycle->reopenBlocker($master),
                'snapshots_enabled' => $this->lifecycle->snapshotsEnabled(),
                'snapshot' => (int) ($master->snapshot_version ?? 0) > 0 ? $this->snapshots->report((int) $cutoffId) : null,
                'snapshot_intact' => $this->lifecycle->snapshotIntact($master),
                'late_changes' => $adjustments->forCutoff((int) $cutoffId),
                'can_manage' => $this->permissions->can($userId, 'payroll.settings'),
                'can_finalize' => $this->permissions->can($userId, 'cutoff.finalize'),
                'can_reopen' => $this->permissions->can($userId, 'cutoff.reopen'),
                'history' => $this->lifecycle->history((int) $cutoffId),
            ]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function employeeSnapshot($cutoffId, $employeeId)
    {
        try {
            $detail = $this->snapshots->employeeDetail((int) $cutoffId, (int) $employeeId);
            if (!$detail) {
                return response()->json(['success' => false, 'message' => 'This employee has no snapshot in this cutoff yet.'], 404);
            }

            return response()->json(['success' => true] + $detail);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function generateSnapshot($cutoffId)
    {
        try {
            if (!$this->lifecycle->find((int) $cutoffId)) {
                return response()->json(['success' => false, 'message' => 'Cutoff not found.'], 404);
            }
            @set_time_limit(0);
            $report = $this->snapshots->generate((int) $cutoffId, (int) Auth::id());
            $issues = count($report['exceptions']);

            return response()->json([
                'success' => true,
                'message' => "Snapshot generated for {$report['processed']} employee(s), with {$issues} item(s) to review.",
                'report' => $report,
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    // Snapshot in batches: the page calls start, then batch for each slice of
    // employees (showing progress), then finish. See CutoffSnapshotService.

    public function generateSnapshotStart($cutoffId)
    {
        try {
            if (!$this->lifecycle->find((int) $cutoffId)) {
                return response()->json(['success' => false, 'message' => 'Cutoff not found.'], 404);
            }
            $employeeIds = $this->snapshots->startBatches((int) $cutoffId, (int) Auth::id());

            return response()->json(['success' => true, 'employee_ids' => $employeeIds]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function generateSnapshotBatch(Request $request, $cutoffId)
    {
        try {
            $request->validate(['employee_ids' => 'required|array|min:1|max:200', 'employee_ids.*' => 'integer']);
            @set_time_limit(300);
            $processed = $this->snapshots->processBatch((int) $cutoffId, (int) Auth::id(), $request->input('employee_ids'));

            return response()->json(['success' => true, 'processed' => $processed]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function generateSnapshotFinish($cutoffId)
    {
        try {
            $report = $this->snapshots->finishBatches((int) $cutoffId, (int) Auth::id());
            $issues = count($report['exceptions']);

            return response()->json([
                'success' => true,
                'message' => "Snapshot generated for {$report['processed']} employee(s), with {$issues} item(s) to review.",
                'report' => $report,
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    /** The page lost a batch (network error, closed early): put the cutoff back to draft. */
    public function generateSnapshotAbandon(Request $request, $cutoffId)
    {
        try {
            $this->snapshots->abandonBatches((int) $cutoffId, (int) Auth::id(), (string) $request->input('reason', 'Stopped before finishing.'));

            return response()->json(['success' => true]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function returnToDraft($cutoffId)
    {
        try {
            $this->lifecycle->returnToDraft((int) $cutoffId, (int) Auth::id());

            return response()->json(['success' => true, 'message' => 'Cutoff sent back to draft.']);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function finalize(Request $request, $cutoffId)
    {
        try {
            $request->validate(['confirmed' => 'accepted'], [
                'confirmed.accepted' => 'Confirm that you reviewed the pre-check before finalizing.',
            ]);
            $this->lifecycle->finalize((int) $cutoffId, (int) Auth::id());
            $month = \Carbon\Carbon::parse($this->lifecycle->find((int) $cutoffId)->period_start)->format('F Y');

            return response()->json([
                'success' => true,
                'message' => "Cutoff finalized. It can no longer be regenerated. Earned credits for {$month} are ready to post on Leave Credits Earned.",
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function reopen(Request $request, $cutoffId)
    {
        try {
            $validated = $request->validate(['reason' => 'required|string|min:5|max:1000'], [
                'reason.required' => 'Give a reason for reopening this cutoff.',
                'reason.min' => 'Give a reason for reopening this cutoff.',
            ]);
            $this->lifecycle->reopen((int) $cutoffId, (int) Auth::id(), trim($validated['reason']));

            return response()->json(['success' => true, 'message' => 'Cutoff reopened as a draft. You can regenerate it now.']);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    private function validationError(ValidationException $e)
    {
        return response()->json([
            'success' => false,
            'message' => collect($e->errors())->flatten()->first() ?? 'Validation failed.',
            'errors' => $e->errors(),
        ], 422);
    }
}
