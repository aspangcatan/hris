<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class EmployeeServices
{
    public function getEmployees()
    {
        return DB::table('tdh_user.users')
            ->select('id', 'fname', 'mname', 'lname', 'suffix')
            ->get();
    }
}
