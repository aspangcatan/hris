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
                ->where('lp.role', "=", 'SH');
        } elseif ($accessRight === "DH") {
            $query->where('lp.approver_id', $user->division)
                ->where('lp.role', "DH");
        } elseif ($accessRight === "HR" || $accessRight === "HRH") {
            $query->where('lp.approver_id', 15)
                ->where('lp.role', "HR");
        }

        $query->where('lp.status', "=", 'pending');
        return $query->get();
    }

    public function updateApprovalStatus($leave_id, $role, $remarks, $status)
    {
        DB::table('hris.leave_application_approvals')
            ->where(function ($query) use ($leave_id, $role) {
                $query->where('leave_application_id', '=', $leave_id);
                if ($role == "admin")
                    $query->where('role', '=', "SH");
                else if ($role == "HRH")
                    $query->where('role', '=', "HR");
                else
                    $query->where('role', '=', $role);
            })->update([
                'status' => $status,
                'remarks' => $remarks,
                'approved_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

        if ($status == "Rejected") {
            DB::table('hris.leave_applications')
                ->where('id', '=', $leave_id)
                ->update([
                    'status' => $status,
                    'updated_at' => Carbon::now()
                ]);
            return;
        }

        if ($role == "HRH" || $role == "HR") {
            DB::table('hris.leave_applications')
                ->where('id', '=', $leave_id)
                ->update([
                    'status' => $status,
                    'updated_at' => Carbon::now()
                ]);
        }
    }
}
