<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveApprovalService
{
    private PermissionService $permissions;

    public function __construct(PermissionService $permissions)
    {
        $this->permissions = $permissions;
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

    private function canApprovePendingStep(object $pendingApproval, $user): bool
    {
        $accessRight = strtoupper((string) session('access_rights'));

        if ($pendingApproval->role === 'SH') {
            return $accessRight === 'ADMIN'
                || (
                    (bool) session('is_section_head')
                    && !empty($user->section)
                    && (int) $pendingApproval->approver_id === (int) $user->section
                );
        }

        if ($pendingApproval->role === 'DH') {
            return (bool) session('is_division_head')
                && !empty($user->division)
                && (int) $pendingApproval->approver_id === (int) $user->division;
        }

        if ($pendingApproval->role === 'HR') {
            return $this->canActAsHr();
        }

        return false;
    }

    public function resolveCurrentApprovalRoleForLeave(int $leaveId): ?string
    {
        $user = Auth::user();

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
        $user           = Auth::user();
        $isAdmin        = strtoupper((string) session('access_rights')) === 'ADMIN';
        $isSectionHead  = (bool) session('is_section_head');
        $isDivisionHead = (bool) session('is_division_head');
        $canActAsHr     = $this->canActAsHr();

        $baseSelect = [
            'la.id',
            DB::raw("(SELECT GROUP_CONCAT(DISTINCT lt.description ORDER BY lt.id SEPARATOR ', ')
                      FROM hris.leave_date_applied lda
                      INNER JOIN hris.leave_types lt ON lda.leave_type_id = lt.id
                      WHERE lda.leave_application_id = la.id) as description"),
            DB::raw("(SELECT ld2.detail FROM hris.leave_details ld2
                      WHERE ld2.leave_application_id = la.id LIMIT 1) as detail"),
            'la.status',
            'la.created_at',
            'u.fname',
            'u.mname',
            'u.lname',
        ];

        if ($filter === 'pending') {
            $query = DB::table('hris.leave_applications as la')
                ->join('tdh_user.users as u', 'la.user_id', '=', 'u.id')
                ->join('hris.leave_application_approvals as lp', 'la.id', '=', 'lp.leave_application_id')
                ->select(array_merge($baseSelect, ['lp.role', 'lp.approver_id']))
                ->where('lp.status', 'pending')
                ->whereRaw(
                    'lp.order_no = (SELECT MIN(p.order_no) FROM hris.leave_application_approvals as p
                                     WHERE p.leave_application_id = la.id AND p.status = ?)',
                    ['pending']
                )
                ->where(function ($approvalQuery) use ($isAdmin, $isSectionHead, $isDivisionHead, $canActAsHr, $user) {
                    if ($isAdmin || ($isSectionHead && !empty($user->section))) {
                        $approvalQuery->orWhere(function ($q) use ($isAdmin, $isSectionHead, $user) {
                            $q->where('lp.role', 'SH');
                            if (!$isAdmin && $isSectionHead) {
                                $q->where('lp.approver_id', $user->section);
                            }
                        });
                    }

                    if ($isDivisionHead && !empty($user->division)) {
                        $approvalQuery->orWhere(function ($q) use ($user) {
                            $q->where('lp.role', 'DH')->where('lp.approver_id', $user->division);
                        });
                    }

                    if ($canActAsHr) {
                        $approvalQuery->orWhere(function ($q) {
                            $q->where('lp.role', 'HR')->where('lp.approver_id', 15);
                        });
                    }
                });

        } elseif ($filter === 'approved_by_stage') {
            $query = DB::table('hris.leave_applications as la')
                ->join('tdh_user.users as u', 'la.user_id', '=', 'u.id')
                ->select($baseSelect);

            if ($isAdmin) {
                $query->whereExists(function ($sub) {
                    $sub->from('hris.leave_application_approvals as lp2')
                        ->whereColumn('lp2.leave_application_id', 'la.id')
                        ->where('lp2.role', 'SH')
                        ->where('lp2.status', 'approved');
                });
            } else {
                $query->where(function ($q) use ($isSectionHead, $isDivisionHead, $canActAsHr, $user) {
                    if ($isSectionHead && !empty($user->section)) {
                        $q->orWhereExists(function ($sub) use ($user) {
                            $sub->from('hris.leave_application_approvals as lp2')
                                ->whereColumn('lp2.leave_application_id', 'la.id')
                                ->where('lp2.role', 'SH')
                                ->where('lp2.approver_id', $user->section)
                                ->where('lp2.status', 'approved');
                        });
                    }
                    if ($isDivisionHead && !empty($user->division)) {
                        $q->orWhereExists(function ($sub) use ($user) {
                            $sub->from('hris.leave_application_approvals as lp2')
                                ->whereColumn('lp2.leave_application_id', 'la.id')
                                ->where('lp2.role', 'DH')
                                ->where('lp2.approver_id', $user->division)
                                ->where('lp2.status', 'approved');
                        });
                    }
                    if ($canActAsHr) {
                        $q->orWhereExists(function ($sub) {
                            $sub->from('hris.leave_application_approvals as lp2')
                                ->whereColumn('lp2.leave_application_id', 'la.id')
                                ->where('lp2.role', 'HR')
                                ->where('lp2.approver_id', 15)
                                ->where('lp2.status', 'approved');
                        });
                    }
                });
            }

        } else { // approved_all
            $query = DB::table('hris.leave_applications as la')
                ->join('tdh_user.users as u', 'la.user_id', '=', 'u.id')
                ->select($baseSelect)
                ->where('la.status', 'Approved');

            if (!$isAdmin) {
                $query->where(function ($q) use ($isSectionHead, $isDivisionHead, $canActAsHr, $user) {
                    if ($isSectionHead && !empty($user->section)) {
                        $q->orWhereExists(function ($sub) use ($user) {
                            $sub->from('hris.leave_application_approvals as lp2')
                                ->whereColumn('lp2.leave_application_id', 'la.id')
                                ->where('lp2.role', 'SH')
                                ->where('lp2.approver_id', $user->section);
                        });
                    }
                    if ($isDivisionHead && !empty($user->division)) {
                        $q->orWhereExists(function ($sub) use ($user) {
                            $sub->from('hris.leave_application_approvals as lp2')
                                ->whereColumn('lp2.leave_application_id', 'la.id')
                                ->where('lp2.role', 'DH')
                                ->where('lp2.approver_id', $user->division);
                        });
                    }
                    if ($canActAsHr) {
                        $q->orWhereExists(function ($sub) {
                            $sub->from('hris.leave_application_approvals as lp2')
                                ->whereColumn('lp2.leave_application_id', 'la.id')
                                ->where('lp2.role', 'HR')
                                ->where('lp2.approver_id', 15);
                        });
                    }
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

        $approvals = DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $leaveId)
            ->orderBy('order_no', 'asc')
            ->get(['role', 'status', 'order_no', 'approver_id']);

        $myApproval = null;
        foreach ($approvals as $approval) {
            if (strtolower($approval->status) !== 'approved') {
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

        return !DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $leaveId)
            ->where('order_no', '>', $myApproval->order_no)
            ->whereRaw('LOWER(status) = ?', ['approved'])
            ->exists();
    }

    public function undoApproval(int $leaveId): void
    {
        if (!$this->canUndoApproval($leaveId)) {
            throw new \RuntimeException('You are not allowed to undo this approval, or a subsequent stage has already approved.');
        }

        $myApproval = $this->findUserApprovedStep($leaveId);

        $roleToLogType = ['SH' => 1, 'DH' => 2, 'HR' => 3];
        $logTypeFrom   = $roleToLogType[$myApproval->role] ?? null;

        DB::transaction(function () use ($leaveId, $myApproval, $logTypeFrom) {
            DB::table('hris.leave_application_approvals')
                ->where('leave_application_id', $leaveId)
                ->where('role', $myApproval->role)
                ->update([
                    'status'      => 'pending',
                    'approved_at' => null,
                    'updated_at'  => Carbon::now(),
                ]);

            // Revert application status if it was fully approved (HR undid their step)
            DB::table('hris.leave_applications')
                ->where('id', $leaveId)
                ->where('status', 'Approved')
                ->update(['status' => 'Pending', 'updated_at' => Carbon::now()]);

            // Remove this stage's log and any downstream forwarding logs
            if ($logTypeFrom !== null) {
                DB::table('hris.leave_application_logs')
                    ->where('leave_application_id', $leaveId)
                    ->where('type', '>=', $logTypeFrom)
                    ->delete();
            }
        });
    }

    public function updateApprovalStatus(int $leave_id, ?string $role = null, ?string $remarks = null, ?string $status = null): void
    {
        $app = DB::table('hris.leave_applications')->where('id', $leave_id)->first(['id']);
        if (!$app) {
            throw new \Exception('Leave application not found.');
        }

        $normalizedStatus = strtolower(trim($status ?? ''));
        $allowedStatuses  = ['pending', 'approved', 'rejected'];

        if (!in_array($normalizedStatus, $allowedStatuses, true)) {
            throw new \InvalidArgumentException('Invalid approval status.');
        }

        $effectiveRole = $role ?: $this->resolveCurrentApprovalRoleForLeave($leave_id);
        if ($effectiveRole === null) {
            throw new \RuntimeException('Current user is not allowed to approve this leave application.');
        }

        DB::transaction(function () use ($leave_id, $effectiveRole, $remarks, $normalizedStatus) {
            // Enforce SH → DH → HR order
            if ($effectiveRole === 'DH') {
                $shApproved = DB::table('hris.leave_application_approvals')
                    ->where('leave_application_id', $leave_id)
                    ->where('role', 'SH')
                    ->whereRaw('LOWER(status) = ?', ['approved'])
                    ->exists();

                if (!$shApproved) {
                    throw new \RuntimeException('Section head approval is required before division head approval.');
                }
            }

            if ($effectiveRole === 'HR') {
                $shApproved = DB::table('hris.leave_application_approvals')
                    ->where('leave_application_id', $leave_id)
                    ->where('role', 'SH')
                    ->whereRaw('LOWER(status) = ?', ['approved'])
                    ->exists();

                $dhApproved = DB::table('hris.leave_application_approvals')
                    ->where('leave_application_id', $leave_id)
                    ->where('role', 'DH')
                    ->whereRaw('LOWER(status) = ?', ['approved'])
                    ->exists();

                if (!$shApproved || !$dhApproved) {
                    throw new \RuntimeException('Section and division head approvals are required before HR approval.');
                }
            }

            DB::table('hris.leave_application_approvals')
                ->where('leave_application_id', $leave_id)
                ->where('role', $effectiveRole)
                ->update([
                    'status'      => $normalizedStatus,
                    'remarks'     => $remarks,
                    'approved_at' => Carbon::now(),
                    'updated_at'  => Carbon::now(),
                ]);

            if ($normalizedStatus === 'rejected') {
                $this->restoreCredits($leave_id);
                DB::table('hris.leave_applications')
                    ->where('id', $leave_id)
                    ->update(['status' => 'Rejected', 'updated_at' => Carbon::now()]);
                return;
            }

            if ($effectiveRole === 'HR') {
                DB::table('hris.leave_applications')
                    ->where('id', $leave_id)
                    ->update(['status' => 'Approved', 'updated_at' => Carbon::now()]);
            }
        });
    }

    private function restoreCredits(int $leaveId): void
    {
        $app = DB::table('hris.leave_applications')->where('id', $leaveId)->first(['id', 'status', 'user_id']);
        if (!$app || strtolower($app->status) === 'rejected') {
            return;
        }

        $dates = DB::table('hris.leave_date_applied')
            ->where('leave_application_id', $leaveId)
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
}
