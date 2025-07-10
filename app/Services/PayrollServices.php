<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollServices
{
    public function getPayrollEmployee($payroll_id)
    {
        $list = DB::table('hris.payroll_employees as e')
            ->join('tdh_user.users as u', 'u.id', '=', 'e.employee_id')
            ->join('tdh_user.division as d', 'd.id', '=', 'u.division')
            ->join('tdh_user.section as s', 's.id', '=', 'u.section')
            ->where('e.payroll_id', '=', $payroll_id)
            ->select(
                'e.*',
                'u.fname',
                'u.mname',
                'u.lname',
                'u.suffix',
                'd.description as division',
                's.description as section'
            )
            ->get();

        return $list;
    }
}
