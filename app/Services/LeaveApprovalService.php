<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveApprovalService
{
    public function getLeavesForApproval()
    {
        $accessRight = session('access_rights');
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

        if ($accessRight === "SH" || $accessRight === "admin") {
            $query->where('lp.approver_id', $user->section)
                ->where('lp.role', "SH");
        } elseif ($accessRight === "DH") {
            $query->where('lp.approver_id', $user->division)
                ->where('lp.role', "DH")
                ->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('hris.leave_application_approvals as sh')
                        ->whereColumn('sh.leave_application_id', 'la.id')
                        ->where('sh.role', 'SH')
                        ->where('sh.status', 'approved');
                });
        } elseif ($accessRight === "HR" || $accessRight === "HRH") {
            $query->where('lp.approver_id', 15)
                ->where('lp.role', "HR")
                ->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('hris.leave_application_approvals as sh')
                        ->whereColumn('sh.leave_application_id', 'la.id')
                        ->where('sh.role', 'SH')
                        ->where('sh.status', 'approved');
                })
                ->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('hris.leave_application_approvals as dh')
                        ->whereColumn('dh.leave_application_id', 'la.id')
                        ->where('dh.role', 'DH')
                        ->where('dh.status', 'approved');
                });
        }

        $query->where('lp.status', "=", 'pending');
        return $query->get();
    }


    public function updateApprovalStatus($leave_id, $role, $remarks, $status)
    {
        DB::transaction(function () use ($leave_id, $role, $remarks, $status) {
            // Update the approver’s row
            DB::table('hris.leave_application_approvals')
                ->where(function ($query) use ($leave_id, $role) {
                    $query->where('leave_application_id', '=', $leave_id);
                    if ($role == "admin")
                        $query->where('role', '=', "SH");
                    else if ($role == "HRH")
                        $query->where('role', '=', "HR");
                    else
                        $query->where('role', '=', $role);
                })
                ->update([
                    'status' => $status,
                    'remarks' => $remarks,
                    'approved_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);

            // If rejected → restore credits and mark application
            if ($status == "Rejected") {
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
                        'status' => $status,
                        'updated_at' => Carbon::now()
                    ]);

                return;
            }

            // If final approver (HR/HRH), mark application approved
            if ($role == "HRH" || $role == "HR") {
                DB::table('hris.leave_applications')
                    ->where('id', '=', $leave_id)
                    ->update([
                        'status' => $status,
                        'updated_at' => Carbon::now()
                    ]);
            }
        });
    }

}
