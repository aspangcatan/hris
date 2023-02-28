<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    #TODO
    #1. PERSONNEL ACCOUNT
    #a. GET EMPLOYEE DETAILS
    #b. INSERT/UPDATE EMPLOYEE DETAILS

    public function getEmployeeDetails(Request $request)
    {
        #CREATE USER_ID VARIABLE
        #THIS FUNCTION CAN BE REUSED IN HR

        $user_id = 1116;
        try {
            $employee = DB::table('hris.employees as e')
                ->join('tdh_user.users as u', 'e.user_id', '=', 'u.id')
                ->where('e.user_id', '=', $user_id)
                ->get(['u.fname', 'u.mname', 'u.lname', 'u.suffix', 'u.picture', 'e.*']);

            return response(['code' => 200, 'results' => $employee]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function insertUpdateEmployee(Request $request)
    {
        try {
            $user_id = 1116;
            $user = User::find($user_id);
            if (!$user_id) {
                return response(['code' => 500, 'message' => 'User ID dont exists']);
            }
            $user->fname = $request->fname;
            $user->mname = $request->mname;
            $user->lname = $request->lname;
            $user->suffix = $request->suffix;
            $user->save();

            $employee = Employee::where('user_id', $user_id)->first();
            if (!$employee) {
                $employee = new Employee;
                $employee->user_id = $user_id;
            }

            $employee->birthdate = $request->birthdate;
            $employee->birthplace = $request->birthplace;
            $employee->sex = $request->sex;
            $employee->civil_status = $request->civil_status;
            $employee->height = $request->height;
            $employee->weight = $request->weight;
            $employee->blood_type = $request->blood_type;
            $employee->gsis_id_no = $request->gsis_id_no;
            $employee->pagibig_id_no = $request->pagibig_id_no;
            $employee->philhealth_no = $request->philhealth_no;
            $employee->sss_no = $request->sss_no;
            $employee->tin_no = $request->tin_no;
            $employee->employee_no = $request->employee_no;
            $employee->citizenship = $request->citizenship;
            $employee->citizenship_type = $request->citizenship_type;
            $employee->citizenship_country = $request->citizenship_country;
            $employee->telephone_no = $request->telephone_no;
            $employee->mobile_no = $request->mobile_no;
            $employee->email_address = $request->email_address;
            $employee->save();

            return response(['code' => 200, 'message' => 'Personal Information updated']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }
}
