<?php

namespace App\Services;

use Carbon\Carbon;
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
}
