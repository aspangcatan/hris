<?php

namespace App\Services;

use Carbon\Carbon;
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

}
