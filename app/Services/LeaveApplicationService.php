<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveApplicationService
{
    public function updateStatus($leave_id, $remarks)
    {
        return DB::table('hris.leave_applications')
            ->where('id', '=', $leave_id)
            ->update([
                'status' => $remarks,
                'updated_at' => Carbon::now()
            ]);
    }

    public function insertLeaveApplication(array $data)
    {
        return DB::transaction(function () use ($data) {
            $leaveApplicationId = DB::table('leave_applications')->insertGetId([
                'user_id' => Auth::id(),
                'leave_id' => $data['leave_id'],
                'detail' => $data['detail'] ?? null,
                'extra' => $data['extra'] ?? null,
                'commutation' => $data['commutation'] ?? null,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $approvals = [
                ['role' => 'SH', 'approver_id' => 1, 'order_no' => 1],
                ['role' => 'DH', 'approver_id' => 2, 'order_no' => 2],
                ['role' => 'HR', 'approver_id' => 3, 'order_no' => 3],
            ];

            $approvalData = [];
            foreach ($approvals as $approval) {
                $approvalData[] = [
                    'leave_application_id' => $leaveApplicationId,
                    'approver_id' => $approval['approver_id'],
                    'role' => $approval['role'],
                    'order_no' => $approval['order_no'],
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('leave_application_approvals')->insert($approvalData);

            if (!empty($data['dates'])) {
                $datesData = [];
                foreach ($data['dates'] as $date) {
                    $datesData[] = [
                        'leave_application_id' => $leaveApplicationId,
                        'date_applied' => $date['date_applied'],
                        'hours' => $date['hours']
                    ];
                }
                DB::table('leave_date_applied')->insert($datesData);
            }

            return $leaveApplicationId;
        });
    }

    public function findById($id)
    {
        $application = DB::table('hris.leave_applications as la')
            ->join('tdh_user.users as u', 'la.user_id', '=', 'u.id')
            ->join('hris.leave_types as lt', 'la.leave_id', '=', 'lt.id')
            ->where('la.id', $id)
            ->select(
                'la.id',
                'u.fname',
                'u.lname',
                'u.mname',
                'lt.description as leave_type',
                'la.detail',
                'la.commutation',
                'la.created_at'
            )
            ->first();

        if (!$application) {
            return null;
        }

        $dates = DB::table('hris.leave_date_applied')
            ->where('leave_application_id', $id)
            ->select(
                'date_applied',
                'hours'
            )
            ->orderBy('date_applied', 'asc')
            ->get();

        // Always assign a Collection to dates
        $application->dates = $dates;


        return $application;
    }

}
