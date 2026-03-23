<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveApprovalService
{
    public function getLeavesForApproval($search_employee = null)
    {
        $accessRight = session('access_rights');
        $isSectionHead = (bool) session('is_section_head');
        $isDivisionHead = (bool) session('is_division_head');
        $user = Auth::user();

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

        // Apply role-based filters
        if ($isSectionHead) {
            $query->where('lp.approver_id', $user->section)
                ->where('lp.role', 'SH');
        } elseif ($isDivisionHead) {
            $query->where('lp.approver_id', $user->division)
                ->where('lp.role', 'DH')
                ->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('hris.leave_application_approvals as sh')
                        ->whereColumn('sh.leave_application_id', 'la.id')
                        ->where('sh.role', 'SH')
                        ->whereRaw('LOWER(sh.status) = ?', ['approved']);
                });
        } elseif ($accessRight === 'HR' || $accessRight === 'HRH') {
            $query->where('lp.approver_id', 15)
                ->where('lp.role', 'HR')
                ->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('hris.leave_application_approvals as sh')
                        ->whereColumn('sh.leave_application_id', 'la.id')
                        ->where('sh.role', 'SH')
                        ->whereRaw('LOWER(sh.status) = ?', ['approved']);
                })
                ->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('hris.leave_application_approvals as dh')
                        ->whereColumn('dh.leave_application_id', 'la.id')
                        ->where('dh.role', 'DH')
                        ->whereRaw('LOWER(dh.status) = ?', ['approved']);
                });
        }

        // Always pending
        $query->where('lp.status', 'pending');

        // Optional search
        if (!empty($search_employee)) {
            $query->where(function ($q) use ($search_employee) {
                $q->where('u.fname', 'like', "%{$search_employee}%")
                    ->orWhere('u.lname', 'like', "%{$search_employee}%");
            });
        }

        return $query->get();
    }

    public function updateApprovalStatus($leave_id, $role, $remarks, $status)
    {
        $normalizedStatus = strtolower(trim($status));
        $allowedStatuses = ['pending', 'approved', 'rejected'];
        if (!in_array($normalizedStatus, $allowedStatuses, true)) {
            throw new \InvalidArgumentException('Invalid approval status.');
        }

        $effectiveRole = $role;
        if ($role === 'admin') {
            $effectiveRole = 'SH';
        } elseif ($role === 'HRH') {
            $effectiveRole = 'HR';
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
