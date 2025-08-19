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
                ->where('lp.role', "DH");
        } elseif ($accessRight === "HR") {
            $query->where('lp.approver_id', $user->section)
                ->where('lp.role', "HR");
        }

        return $query->get();
    }

}
