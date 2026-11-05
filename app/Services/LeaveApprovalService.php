<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveApprovalService
{
    private PermissionService $permissions;
    private OrgHierarchyService $org;

    public function __construct(PermissionService $permissions, OrgHierarchyService $org)
    {
        $this->permissions = $permissions;
        $this->org         = $org;
    }

    /** Sections/divisions the current user actually heads, cached for the request. */
    private array $headed = [];

    private function headed(): array
    {
        $id = (int) Auth::id();
        if (!isset($this->headed[$id])) {
            $this->headed[$id] = [
                'sections'  => $id ? $this->org->headedSectionIds($id) : [],
                'divisions' => $id ? $this->org->headedDivisionIds($id) : [],
            ];
        }
        return $this->headed[$id];
    }

    private function isOwnLeave(int $leaveId): bool
    {
        return (int) DB::table('hris.leave_applications')->where('id', $leaveId)->value('user_id') === (int) Auth::id();
    }

    /**
     * Owner, ADMIN, HR, or a head named in this leave's approval chain.
     */
    public function canViewLeave(int $leaveId): bool
    {
        $app = DB::table('hris.leave_applications')->where('id', $leaveId)->first(['user_id']);
        if (!$app) {
            return false;
        }
        if ((int) $app->user_id === (int) Auth::id()
            || strtoupper((string) session('access_rights')) === 'ADMIN'
            || $this->canActAsHr()) {
            return true;
        }

        $headed = $this->headed();
        return DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $leaveId)
            ->where(function ($q) use ($headed) {
                $q->where(fn($s) => $s->where('role', 'SH')->whereIn('approver_id', $headed['sections'] ?: [0]))
                  ->orWhere(fn($d) => $d->where('role', 'DH')->whereIn('approver_id', $headed['divisions'] ?: [0]));
            })
            ->exists();
    }

    private function canActAsHr(): bool
    {
        $accessRight = strtoupper((string) session('access_rights'));
        if (in_array($accessRight, ['HR', 'HRH'], true)) {
            return true;
        }

        $userId = Auth::id();
        return $userId && $this->permissions->can($userId, 'leave.approve_hr');
    }

    /**
     * SH/DH steps are matched against the sections/divisions the user actually heads
     * (tdh_user.section.head / division.head), not the user's own section/division
     * fields, which for many heads point somewhere else.
     */
    private function canApprovePendingStep(object $pendingApproval, $user): bool
    {
        $accessRight = strtoupper((string) session('access_rights'));
        $headed      = $this->headed();

        if ($pendingApproval->role === 'SH') {
            return $accessRight === 'ADMIN'
                || in_array((int) $pendingApproval->approver_id, $headed['sections'], true);
        }

        if ($pendingApproval->role === 'DH') {
            return in_array((int) $pendingApproval->approver_id, $headed['divisions'], true);
        }

        if ($pendingApproval->role === 'HR' || $pendingApproval->role === 'HR_COMPLIANCE') {
            return $this->canActAsHr();
        }

        return false;
    }

    public function resolveCurrentApprovalRoleForLeave(int $leaveId): ?string
    {
        $user = Auth::user();

        // Nobody acts on their own leave at any step.
        if ($this->isOwnLeave($leaveId)) {
            return null;
        }

        $pendingApproval = DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $leaveId)
            ->where('status', 'pending')
            ->orderBy('order_no', 'asc')
            ->first(['role', 'approver_id']);

        if (!$pendingApproval) {
            return null;
        }

        return $this->canApprovePendingStep($pendingApproval, $user)
            ? $pendingApproval->role
            : null;
    }

    public function getLeavesForApproval(?string $search_employee = null, string $filter = 'pending')
    {
        $isAdmin    = strtoupper((string) session('access_rights')) === 'ADMIN';
        $canActAsHr = $this->canActAsHr();
        $headed     = $this->headed();
        $sections   = $headed['sections'];
        $divisions  = $headed['divisions'];

        $baseSelect = [
            'la.id',
            DB::raw("(SELECT GROUP_CONCAT(DISTINCT lt.description ORDER BY lt.id SEPARATOR ', ')
                      FROM hris.leave_date_applied lda
                      INNER JOIN hris.leave_types lt ON lda.leave_type_id = lt.id
                      WHERE lda.leave_application_id = la.id) as description"),
            DB::raw("(SELECT GROUP_CONCAT(DISTINCT lda2.date_applied ORDER BY lda2.date_applied SEPARATOR ',')
                      FROM hris.leave_date_applied lda2
                      WHERE lda2.leave_application_id = la.id) as dates_applied"),
            DB::raw("(SELECT ld2.detail FROM hris.leave_details ld2
                      WHERE ld2.leave_application_id = la.id LIMIT 1) as detail"),
            'la.status',
            'la.created_at',
            'la.is_lwop',
            'u.fname',
            'u.mname',
            'u.lname',
        ];

        /**
         * Restricts $q (on approval alias $a) to the steps this user may act on / has acted on.
         * Adds an always-false condition when the user has no approving role at all, so an
         * empty group can never widen the query to every leave.
         */
        $mySteps = function ($q, string $a, bool $adminAllSh) use ($isAdmin, $canActAsHr, $sections, $divisions) {
            $any = false;
            $q->where(function ($w) use ($a, $adminAllSh, $isAdmin, $canActAsHr, $sections, $divisions, &$any) {
                if (($isAdmin && $adminAllSh) || !empty($sections)) {
                    $any = true;
                    $w->orWhere(function ($s) use ($a, $adminAllSh, $isAdmin, $sections) {
                        $s->where("$a.role", 'SH');
                        if (!($isAdmin && $adminAllSh)) {
                            $s->whereIn("$a.approver_id", $sections);
                        }
                    });
                }
                if (!empty($divisions)) {
                    $any = true;
                    $w->orWhere(fn($d) => $d->where("$a.role", 'DH')->whereIn("$a.approver_id", $divisions));
                }
                if ($canActAsHr) {
                    $any = true;
                    $w->orWhere(fn($h) => $h->whereIn("$a.role", ['HR', 'HR_COMPLIANCE'])->where("$a.approver_id", 15));
                }
            });
            if (!$any) {
                $q->whereRaw('1 = 0');
            }
        };

        $query = DB::table('hris.leave_applications as la')
            ->join('tdh_user.users as u', 'la.user_id', '=', 'u.id')
            // Nobody sees their own leave in an approval list
            ->where('la.user_id', '!=', (int) Auth::id());

        if ($filter === 'pending') {
            $query->join('hris.leave_application_approvals as lp', 'la.id', '=', 'lp.leave_application_id')
                ->select(array_merge($baseSelect, ['lp.role', 'lp.approver_id']))
                ->where('lp.status', 'pending')
                ->whereRaw(
                    'lp.order_no = (SELECT MIN(p.order_no) FROM hris.leave_application_approvals as p
                                     WHERE p.leave_application_id = la.id AND p.status = ?)',
                    ['pending']
                );
            $mySteps($query, 'lp', true);

        } elseif ($filter === 'approved_by_stage') {
            $query->select($baseSelect)->whereExists(function ($sub) use ($mySteps) {
                $sub->from('hris.leave_application_approvals as lp2')
                    ->whereColumn('lp2.leave_application_id', 'la.id')
                    ->whereIn('lp2.status', ['approved', 'acknowledged']);
                $mySteps($sub, 'lp2', true);
            });

        } else { // approved_all
            $query->select($baseSelect)->where('la.status', 'Approved');
            if (!$isAdmin) {
                $query->whereExists(function ($sub) use ($mySteps) {
                    $sub->from('hris.leave_application_approvals as lp2')
                        ->whereColumn('lp2.leave_application_id', 'la.id');
                    $mySteps($sub, 'lp2', false);
                });
            }
        }

        if (!empty($search_employee)) {
            $query->where(function ($q) use ($search_employee) {
                $q->where('u.fname', 'like', "%{$search_employee}%")
                  ->orWhere('u.lname', 'like', "%{$search_employee}%");
            });
        }

        return $query->get();
    }

    private function findUserApprovedStep(int $leaveId): ?object
    {
        $user = Auth::user();

        if ($this->isOwnLeave($leaveId)) {
            return null;
        }

        $approvals = DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $leaveId)
            ->orderBy('order_no', 'asc')
            ->get(['role', 'status', 'order_no', 'approver_id']);

        $myApproval = null;
        foreach ($approvals as $approval) {
            // HR's step is "acknowledged" rather than "approved"; both can be undone by their owner
            if (!in_array(strtolower($approval->status), ['approved', 'acknowledged'], true)) {
                continue;
            }
            if ($this->canApprovePendingStep($approval, $user)) {
                $myApproval = $approval; // iterate all; last match = highest order_no
            }
        }

        return $myApproval;
    }

    public function canUndoApproval(int $leaveId): bool
    {
        $myApproval = $this->findUserApprovedStep($leaveId);

        if (!$myApproval) {
            return false;
        }

        // A later stage that already acted (approved, acknowledged OR rejected) locks this one.
        // Undoing an approval underneath a later decision would leave the chain inconsistent,
        // e.g. SH pending with DH rejected, or a DH undo reopening a leave HR already finalized.
        return !DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $leaveId)
            ->where('order_no', '>', $myApproval->order_no)
            ->whereRaw('LOWER(status) IN (?, ?, ?)', ['approved', 'acknowledged', 'rejected'])
            ->exists();
    }

    public function undoApproval(int $leaveId): void
    {
        if (!$this->canUndoApproval($leaveId)) {
            throw new \RuntimeException('You are not allowed to undo this approval, or a later stage has already approved or rejected it.');
        }

        $myApproval = $this->findUserApprovedStep($leaveId);

        DB::transaction(function () use ($leaveId, $myApproval) {
            DB::table('hris.leave_application_approvals')
                ->where('leave_application_id', $leaveId)
                ->where('role', $myApproval->role)
                ->update([
                    'status'      => 'pending',
                    'remarks'     => null,   // the approval is withdrawn, so its remarks go too
                    'approved_at' => null,
                    'signature'   => null,
                    'updated_at'  => Carbon::now(),
                ]);

            // Revert application status if it was fully approved (HR undid their step)
            DB::table('hris.leave_applications')
                ->where('id', $leaveId)
                ->where('status', 'Approved')
                ->update(['status' => 'Pending', 'updated_at' => Carbon::now()]);

            if ($myApproval->role === 'DH') {
                $this->reverseDhDateDisapprovals($leaveId);
            }
        });
    }

    /**
     * @param bool $overturnRejection Explicit confirmation that a later approver is approving a leave
     *                                an earlier approver rejected. Without it, that approval is refused.
     */
    public function updateApprovalStatus(int $leave_id, ?string $role = null, ?string $remarks = null, ?string $status = null, array $disapprovedDates = [], ?string $signature = null, bool $overturnRejection = false): void
    {
        $app = DB::table('hris.leave_applications')->where('id', $leave_id)->first(['id']);
        if (!$app) {
            throw new \Exception('Leave application not found.');
        }

        $normalizedStatus = strtolower(trim($status ?? ''));
        $allowedStatuses  = ['pending', 'approved', 'rejected', 'acknowledged'];

        if (!in_array($normalizedStatus, $allowedStatuses, true)) {
            throw new \InvalidArgumentException('Invalid approval status.');
        }

        // Blank remarks default to the action taken so the remarks history is never incomplete.
        $remarks = trim((string) $remarks);
        if ($remarks === '') {
            $remarks = ['approved' => 'Approved', 'rejected' => 'Rejected', 'acknowledged' => 'Acknowledged'][$normalizedStatus] ?? null;
        }

        $effectiveRole = $role ?: $this->resolveCurrentApprovalRoleForLeave($leave_id);
        if ($effectiveRole === null) {
            throw new \RuntimeException('Current user is not allowed to approve this leave application.');
        }

        $requiresSignature = $normalizedStatus === 'approved' && in_array($effectiveRole, ['SH', 'DH'], true);
        if ($requiresSignature && empty($signature)) {
            throw new \InvalidArgumentException('A signature is required to approve this leave application.');
        }

        DB::transaction(function () use ($leave_id, $effectiveRole, $remarks, $normalizedStatus, $disapprovedDates, $signature, $requiresSignature, $overturnRejection) {
            // Enforce chain order generically: every earlier step (by
            // order_no) must already be approved before this role's step
            // can be acted on. Works for both the 3-step (SH/DH/HR) and
            // 4-step (HR_COMPLIANCE/SH/DH/HR) chains without knowing which
            // roles precede which - see
            // docs/superpowers/specs/2026-07-21-leave-hr-compliance-step-design.md.
            $myOrderNo = DB::table('hris.leave_application_approvals')
                ->where('leave_application_id', $leave_id)
                ->where('role', $effectiveRole)
                ->value('order_no');

            $priorByRole = DB::table('hris.leave_application_approvals')
                ->where('leave_application_id', $leave_id)
                ->where('order_no', '<', $myOrderNo)
                ->pluck('status', 'role')
                ->map(fn($s) => strtolower($s));
            $priorStatuses = $priorByRole->values();

            // A prior step that is neither approved nor rejected is still pending.
            if ($priorStatuses->contains(fn($s) => !in_array($s, ['approved', 'rejected'], true))) {
                throw new \RuntimeException('A prior approval step must be completed first.');
            }

            // Maternity/Paternity cannot be approved without its supporting document. Checked here
            // (not only at filing) because an edit uploads new files after saving the dates.
            if ($normalizedStatus === 'approved') {
                $typeIds = DB::table('hris.leave_date_applied')->where('leave_application_id', $leave_id)->distinct()->pluck('leave_type_id')->filter()->all();
                $hasAttachment = DB::table('hris.leave_application_attachments')->where('leave_application_id', $leave_id)->exists()
                    || DB::table('hris.leave_applications')->where('id', $leave_id)->whereNotNull('attachment')->exists();
                if (!$hasAttachment && app(LeaveApplicationService::class)->requiresAttachment($typeIds)) {
                    throw new \RuntimeException('This leave requires a supporting attachment (Maternity/Paternity Leave) before it can be approved.');
                }
            }

            $leaveIsRejected = strtolower((string) DB::table('hris.leave_applications')->where('id', $leave_id)->value('status')) === 'rejected';

            // HR's step is an acknowledgement of whatever the Division Head decided. A Division
            // Head rejection is final: nobody can overturn it, HR only acknowledges it. This
            // depends on the DH's step, not the leave's status, so records left in an
            // inconsistent state (DH rejected, leave Pending) are still covered.
            $dhRejected = ($priorByRole['DH'] ?? null) === 'rejected';
            if ($normalizedStatus === 'acknowledged') {
                if ($effectiveRole !== 'HR') {
                    throw new \RuntimeException('Only HR can acknowledge a leave.');
                }
            } elseif ($dhRejected) {
                throw new \RuntimeException('The Division Head rejected this leave. That decision is final and cannot be overturned; HR can only acknowledge it.');
            }

            // Any other prior rejection (e.g. the Section Head's): while the leave is still
            // marked Rejected, only an explicitly confirmed approval may overturn it. Once
            // overturned the leave is Pending again and the old rejection stays in the
            // history without blocking the chain.
            if ($normalizedStatus !== 'acknowledged' && $priorStatuses->contains('rejected') && $leaveIsRejected) {
                if ($normalizedStatus !== 'approved') {
                    throw new \RuntimeException('A prior approver already rejected this leave.');
                }
                if (!$overturnRejection) {
                    throw new \RuntimeException('A prior approver rejected this leave. Confirm to overturn the rejection.');
                }

                $this->redeductCredits($leave_id);
                DB::table('hris.leave_applications')
                    ->where('id', $leave_id)
                    ->update(['status' => 'Pending', 'updated_at' => Carbon::now()]);
            }

            $approvalUpdate = [
                'status'      => $normalizedStatus,
                'remarks'     => $remarks,
                'approved_at' => Carbon::now(),
                'updated_at'  => Carbon::now(),
            ];
            if ($requiresSignature) {
                $approvalUpdate['signature'] = $signature;
            }

            DB::table('hris.leave_application_approvals')
                ->where('leave_application_id', $leave_id)
                ->where('role', $effectiveRole)
                ->update($approvalUpdate);

            if ($normalizedStatus === 'acknowledged') {
                if (!$dhRejected) {
                    // Acknowledging a leave the Division Head approved is HR's final step.
                    DB::table('hris.leave_applications')
                        ->where('id', $leave_id)
                        ->update(['status' => 'Approved', 'updated_at' => Carbon::now()]);
                } elseif (!$leaveIsRejected) {
                    // DH-rejected: the leave stays Rejected with its credits returned. If it was left
                    // in any other state (e.g. Pending with credits deducted), put it back to that.
                    $this->restoreCredits($leave_id);
                    DB::table('hris.leave_applications')
                        ->where('id', $leave_id)
                        ->update(['status' => 'Rejected', 'updated_at' => Carbon::now()]);
                }
                return;
            }

            if ($normalizedStatus === 'rejected') {
                $this->restoreCredits($leave_id);
                DB::table('hris.leave_applications')
                    ->where('id', $leave_id)
                    ->update(['status' => 'Rejected', 'updated_at' => Carbon::now()]);
                return;
            }

            if ($normalizedStatus === 'approved' && in_array($effectiveRole, ['SH', 'DH'], true)) {
                $this->applyDateDisapprovals($leave_id, $effectiveRole, $disapprovedDates);
            }

            if ($effectiveRole === 'HR') {
                DB::table('hris.leave_applications')
                    ->where('id', $leave_id)
                    ->update(['status' => 'Approved', 'updated_at' => Carbon::now()]);
            }
        });

        // A completely rejected leave no longer keeps its attachments.
        app(LeaveApplicationService::class)->purgeAttachmentsIfCompletelyRejected($leave_id);
    }

    /**
     * Reverse of restoreCredits(): deducts a rejected leave's credits again when a
     * later approver overturns the rejection. Fails if the balance no longer covers it.
     */
    private function redeductCredits(int $leaveId): void
    {
        $app = DB::table('hris.leave_applications')->where('id', $leaveId)->first(['id', 'user_id']);

        $creditsByType = DB::table('hris.leave_date_applied')
            ->where('leave_application_id', $leaveId)
            ->where('final_disapproved', 0)
            ->where('status', '!=', 'cancelled')
            ->get(['leave_type_id', 'credits_deducted'])
            ->groupBy('leave_type_id');

        foreach ($creditsByType as $typeId => $rows) {
            $needed = (float) $rows->sum('credits_deducted');
            if (!$typeId || $needed <= 0) continue;

            $credit = DB::table('hris.leave_credits')
                ->where('user_id', $app->user_id)
                ->where('leave_id', $typeId)
                ->lockForUpdate()
                ->first(['based_credit', 'used_credit']);

            if (!$credit || round($credit->based_credit - $credit->used_credit, 3) < round($needed, 3)) {
                $description = DB::table('hris.leave_types')->where('id', $typeId)->value('description');
                throw new \RuntimeException("Cannot overturn the rejection: the employee no longer has enough {$description} credits ({$needed} needed).");
            }

            DB::table('hris.leave_credits')
                ->where('user_id', $app->user_id)
                ->where('leave_id', $typeId)
                ->decrement('based_credit', $needed);
        }
    }

    private function restoreCredits(int $leaveId): void
    {
        $app = DB::table('hris.leave_applications')->where('id', $leaveId)->first(['id', 'status', 'user_id']);
        if (!$app || strtolower($app->status) === 'rejected') {
            return;
        }

        // DH-disapproved and cancelled dates already had their credits given back.
        $dates = DB::table('hris.leave_date_applied')
            ->where('leave_application_id', $leaveId)
            ->where('final_disapproved', 0)
            ->where('status', '!=', 'cancelled')
            ->get(['leave_type_id', 'credits_deducted']);

        $creditsByType = $dates->groupBy('leave_type_id');
        foreach ($creditsByType as $typeId => $rows) {
            if (!$typeId) continue;
            DB::table('hris.leave_credits')
                ->where('user_id', $app->user_id)
                ->where('leave_id', $typeId)
                ->increment('based_credit', $rows->sum('credits_deducted'));
        }
    }

    /**
     * Applies per-date disapproval flags for the SH or DH stage of a leave
     * application, on top of that stage's own overall approve/reject
     * decision - see
     * docs/superpowers/specs/2026-07-15-per-date-leave-disapproval-design.md.
     *
     * SH's action only ever records a suggestion (sh_flagged_disapprove) -
     * it never touches final_disapproved or credits. DH's action is the
     * final, operative verdict: $disapprovedDates is DH's complete desired
     * state for the application, and any date whose final_disapproved value
     * changes as a result has its credits restored (newly disapproved) or
     * re-deducted (overridden back to approved).
     *
     * @param array<int, array{date_applied_id: mixed, remark?: ?string}> $disapprovedDates
     */
    private function applyDateDisapprovals(int $leaveId, string $role, array $disapprovedDates): void
    {
        $remarkByDateId = collect($disapprovedDates)->mapWithKeys(
            fn($d) => [(int) $d['date_applied_id'] => $d['remark'] ?? null]
        );

        $dates = DB::table('hris.leave_date_applied')
            ->where('leave_application_id', $leaveId)
            ->get(['id', 'leave_type_id', 'credits_deducted', 'final_disapproved']);

        if ($role === 'SH') {
            foreach ($dates as $date) {
                $flagged = $remarkByDateId->has($date->id);
                DB::table('hris.leave_date_applied')
                    ->where('id', $date->id)
                    ->update([
                        'sh_flagged_disapprove' => $flagged,
                        'sh_disapproval_remark' => $flagged ? $remarkByDateId->get($date->id) : null,
                    ]);
            }
            return;
        }

        // DH: $disapprovedDates is the complete final state for every date.
        $app = DB::table('hris.leave_applications')->where('id', $leaveId)->first(['user_id']);

        foreach ($dates as $date) {
            $willBeDisapproved = $remarkByDateId->has($date->id);
            $wasDisapproved    = (bool) $date->final_disapproved;

            if ($wasDisapproved === $willBeDisapproved) {
                continue;
            }

            DB::table('hris.leave_date_applied')
                ->where('id', $date->id)
                ->update([
                    'final_disapproved'     => $willBeDisapproved,
                    'dh_disapproval_remark' => $willBeDisapproved ? $remarkByDateId->get($date->id) : null,
                ]);

            if (!$date->leave_type_id) {
                continue;
            }

            $creditQuery = DB::table('hris.leave_credits')
                ->where('user_id', $app->user_id)
                ->where('leave_id', $date->leave_type_id);

            if ($willBeDisapproved) {
                $creditQuery->increment('based_credit', $date->credits_deducted);
            } else {
                $creditQuery->decrement('based_credit', $date->credits_deducted);
            }
        }
    }

    /**
     * Reverses every date the Division Head disapproved on this
     * application, restoring credits that were re-deducted and clearing
     * final_disapproved - called when a DH undoes their own approval.
     * sh_flagged_disapprove/sh_disapproval_remark are left untouched, so
     * when DH redoes their review, the Supervisor's original suggestion is
     * still there to work from.
     */
    private function reverseDhDateDisapprovals(int $leaveId): void
    {
        $app = DB::table('hris.leave_applications')->where('id', $leaveId)->first(['user_id']);

        $disapprovedDates = DB::table('hris.leave_date_applied')
            ->where('leave_application_id', $leaveId)
            ->where('final_disapproved', 1)
            ->get(['id', 'leave_type_id', 'credits_deducted']);

        foreach ($disapprovedDates as $date) {
            if ($date->leave_type_id) {
                DB::table('hris.leave_credits')
                    ->where('user_id', $app->user_id)
                    ->where('leave_id', $date->leave_type_id)
                    ->decrement('based_credit', $date->credits_deducted);
            }

            DB::table('hris.leave_date_applied')
                ->where('id', $date->id)
                ->update([
                    'final_disapproved'     => false,
                    'dh_disapproval_remark' => null,
                ]);
        }
    }
}
