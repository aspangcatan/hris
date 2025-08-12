<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveLogsService
{
    public function updateStatus($leave_id, $type, $remarks)
    {
        return DB::table('hris.leave_application_logs')
            ->where('leave_application_id', '=', $leave_id)
            ->where('type', '=', $type)
            ->update([
                'remarks' => $remarks,
                'updated_at' => Carbon::now()
            ]);
    }

    public function insertLogs($leave_id, $destination, $type)
    {
        return DB::table('hris.leave_application_logs')
            ->insertGetId([
                'leave_application_id' => $leave_id,
                'origin' => Auth::id(),
                'destination' => $destination,
                'remarks'=>"For Approval",
                'type' => $type,
                'created_at' => Carbon::now()
            ]);
    }
}
