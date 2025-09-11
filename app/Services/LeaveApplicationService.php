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
            // Step 1: Get available leave credits
            $availableCredits = DB::table('leave_credits')
                ->where('user_id', Auth::id())
                ->where('leave_id', $data['leave_id'])
                ->value('based_credit');

            if ($availableCredits === null) {
                throw new \Exception("No leave credits found for this leave type.");
            }

            // Step 2: Calculate total hours requested
            $totalHours = collect($data['dates'])->sum('hours');
            $deductPoints = $totalHours / 8; // 8 hrs = 1 point

            // Step 3: Check if balance is enough
            if ($availableCredits < $deductPoints) {
                throw new \Exception("Insufficient leave credits. You only have {$availableCredits} credit(s).");
            }

            // Step 4: Insert leave application
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

            // Step 5: Insert approvers
            $approvals = [
                ['role' => 'SH', 'approver_id' => Auth::user()->section, 'order_no' => 1],
                ['role' => 'DH', 'approver_id' => Auth::user()->division, 'order_no' => 2],
                ['role' => 'HR', 'approver_id' => 15, 'order_no' => 3],
            ];

            $approvalData = collect($approvals)->map(function ($approval) use ($leaveApplicationId) {
                return [
                    'leave_application_id' => $leaveApplicationId,
                    'approver_id' => $approval['approver_id'],
                    'role' => $approval['role'],
                    'order_no' => $approval['order_no'],
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->toArray();

            DB::table('leave_application_approvals')->insert($approvalData);

            // Step 6: Insert leave dates
            $datesData = collect($data['dates'])->map(function ($date) use ($leaveApplicationId) {
                return [
                    'leave_application_id' => $leaveApplicationId,
                    'date_applied' => $date['date_applied'],
                    'hours' => $date['hours']
                ];
            })->toArray();

            DB::table('leave_date_applied')->insert($datesData);

            // Step 7: Deduct leave credits
            DB::table('leave_credits')
                ->where('user_id', Auth::id())
                ->where('leave_id', $data['leave_id'])
                ->decrement('based_credit', $deductPoints);

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
                'la.extra',
                'la.commutation',
                'la.created_at'
            )
            ->first();

        if (!$application) {
            return null;
        }

        $dates = DB::table('hris.leave_date_applied')
            ->where('leave_application_id', $id)
            ->select('date_applied', 'hours')
            ->orderBy('date_applied', 'asc')
            ->get();

        $approvals = DB::table('hris.leave_application_approvals as laa')
            ->where('laa.leave_application_id', $id)
            ->select('laa.role', 'laa.status', 'laa.remarks')
            ->get()
            ->mapWithKeys(function ($item) {
                return [
                    $item->role => [
                        'status' => $item->status,
                        'remarks' => $item->remarks,
                    ]
                ];
            })->toArray();

        $application->dates = $dates;
        $application->approvals = $approvals;

        return $application;
    }
}
