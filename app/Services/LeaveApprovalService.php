<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveApprovalService
{
    private function canActAsHr(): bool
    {
        $accessRight = strtoupper((string) session('access_rights'));

        return in_array($accessRight, ['HR', 'HRH'], true);
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

    public function resolveCurrentApprovalRoleForLeave($leaveId): ?string
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

    public function getLeavesForApproval($search_employee = null)
    {
        $user = Auth::user();
        $isAdmin = strtoupper((string) session('access_rights')) === 'ADMIN';
        $isSectionHead = (bool) session('is_section_head');
        $isDivisionHead = (bool) session('is_division_head');
        $canActAsHr = $this->canActAsHr();

        $query = DB::table('hris.leave_applications as la')
            ->join('tdh_user.users as u', 'la.user_id', '=', 'u.id')
            ->join('hris.leave_types as t', 'la.leave_id', '=', 't.id')
            ->join('hris.leave_application_approvals as lp', 'la.id', '=', 'lp.leave_application_id')
            ->select(
                'la.id',
                't.description',
                'la.detail',
                'la.status',
                'la.created_at',
                'u.fname',
                'u.mname',
                'u.lname',
                'lp.role',
                'lp.approver_id',
                'la.created_at'
            );

        $query->where('lp.status', 'pending')
            ->whereRaw(
                'lp.order_no = (SELECT MIN(p.order_no) FROM hris.leave_application_approvals as p WHERE p.leave_application_id = la.id AND p.status = ?)',
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
                        $q->where('lp.role', 'DH')
                            ->where('lp.approver_id', $user->division);
                    });
                }

                if ($canActAsHr) {
                    $approvalQuery->orWhere(function ($q) {
                        $q->where('lp.role', 'HR')
                            ->where('lp.approver_id', 15);
                    });
                }
            });

        // Optional search
        if (!empty($search_employee)) {
            $query->where(function ($q) use ($search_employee) {
                $q->where('u.fname', 'like', "%{$search_employee}%")
                    ->orWhere('u.lname', 'like', "%{$search_employee}%");
            });
        }

        return $query->get();
    }

    public function updateApprovalStatus($leave_id, $role = null, $remarks = null, $status = null)
    {
        $normalizedStatus = strtolower(trim($status));
        $allowedStatuses = ['pending', 'approved', 'rejected'];
        if (!in_array($normalizedStatus, $allowedStatuses, true)) {
            throw new \InvalidArgumentException('Invalid approval status.');
        }

        $effectiveRole = $role ?: $this->resolveCurrentApprovalRoleForLeave($leave_id);
        if ($effectiveRole === null) {
            throw new \RuntimeException('Current user is not allowed to approve this leave application.');
        }

        DB::transaction(function () use ($leave_id, $effectiveRole, $remarks, $normalizedStatus) {
            // Enforce approval order: SH -> DH -> HR
            if ($effectiveRole === 'DH') {
                $sectionApproved = DB::table('hris.leave_application_approvals')
                    ->where('leave_application_id', $leave_id)
                    ->where('role', 'SH')
                    ->whereRaw('LOWER(status) = ?', ['approved'])
                    ->exists();

                if (!$sectionApproved) {
                    throw new \RuntimeException('Section head approval is required before division head approval.');
                }
            }

            if ($effectiveRole === 'HR') {
                $sectionApproved = DB::table('hris.leave_application_approvals')
                    ->where('leave_application_id', $leave_id)
                    ->where('role', 'SH')
                    ->whereRaw('LOWER(status) = ?', ['approved'])
                    ->exists();

                $divisionApproved = DB::table('hris.leave_application_approvals')
                    ->where('leave_application_id', $leave_id)
                    ->where('role', 'DH')
                    ->whereRaw('LOWER(status) = ?', ['approved'])
                    ->exists();

                if (!$sectionApproved || !$divisionApproved) {
                    throw new \RuntimeException('Section and division head approvals are required before HR approval.');
                }
            }

            // Update the approver's row
            DB::table('hris.leave_application_approvals')
                ->where(function ($query) use ($leave_id, $effectiveRole) {
                    $query->where('leave_application_id', '=', $leave_id);
                    $query->where('role', '=', $effectiveRole);
                })
                ->update([
                    'status' => $normalizedStatus,
                    'remarks' => $remarks,
                    'approved_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);

            // If rejected -> restore credits and mark application
            if ($normalizedStatus === 'rejected') {
                $leaveApp = DB::table('hris.leave_applications')
                    ->where('id', $leave_id)
                    ->first();

                if ($leaveApp) {
                    // Check if already rejected (avoid double restoration)
                    if ($leaveApp->status !== 'Rejected') {
                        // Get total hours
                        $totalHours = DB::table('hris.leave_date_applied')
                            ->where('leave_application_id', $leave_id)
                            ->sum('hours');

                        $restorePoints = $totalHours / 8;

                        // Restore credits
                        DB::table('hris.leave_credits')
                            ->where('user_id', $leaveApp->user_id)
                            ->where('leave_id', $leaveApp->leave_id)
                            ->increment('based_credit', $restorePoints);
                    }
                }

                // Update application status
                DB::table('hris.leave_applications')
                    ->where('id', '=', $leave_id)
                    ->update([
                        'status' => ucfirst($normalizedStatus),
                        'updated_at' => Carbon::now()
                    ]);

                return;
            }

            // If final approver (HR/HRH), mark application approved
            if ($effectiveRole === 'HR') {
                DB::table('hris.leave_applications')
                    ->where('id', '=', $leave_id)
                    ->update([
                        'status' => ucfirst($normalizedStatus),
                        'updated_at' => Carbon::now()
                    ]);
            }
        });
    }
}
