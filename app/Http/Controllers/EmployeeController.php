<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use App\Services\EmployeeServices;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    protected $employeeService;
    protected PermissionService $permissions;

    public function __construct(EmployeeServices $employeeService, PermissionService $permissions)
    {
        $this->employeeService = $employeeService;
        $this->permissions = $permissions;
    }

    public function getEmployeeDetails(Request $request)
    {
        $user_id = ($request->id == 0) ? Auth::id() : $request->id;
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
            $target_user_id = $request->user_id ?: Auth::id();
            $is_self_update = ((int) $target_user_id === (int) Auth::id());
            $can_update_others = $this->permissions->can(Auth::id(), 'employee.edit');

            if (!$is_self_update && !$can_update_others) {
                return response(['code' => 500, 'message' => 'Unauthorized employee update request']);
            }

            $user = User::find($target_user_id);
            if (!$user) {
                return response(['code' => 500, 'message' => 'User ID dont exists']);
            }

            $user->fname = $request->fname;
            $user->mname = $request->mname;
            $user->lname = $request->lname;
            $user->suffix = $request->suffix;
            $user->save();

            $employee = Employee::where('user_id', $target_user_id)->first();
            if (!$employee) {
                $employee = new Employee;
                $employee->user_id = $target_user_id;
            }

            $employee->birthdate = $request->birthdate;
            $employee->birthplace = $request->birthplace;
            $employee->sex = $request->sex;
            $employee->civil_status = $request->civil_status;
            $employee->height = $request->height;
            $employee->weight = $request->weight;
            $employee->blood_type = $request->blood_type;
            $employee->umid_id_no = $request->umid_id_no;
            $employee->gsis_id_no = $request->gsis_id_no;
            $employee->pagibig_id_no = $request->pagibig_id_no;
            $employee->philhealth_no = $request->philhealth_no;
            $employee->sss_no = $request->sss_no;
            $employee->philsys_no = $request->philsys_no;
            $employee->tin_no = $request->tin_no;
            $employee->employee_no = $request->employee_no;
            $employee->employee_type = $request->employee_type;
            $employee->date_hired = $request->date_hired;
            $employee->date_resigned = $request->date_resigned;
            $employee->citizenship = $request->citizenship;
            $employee->citizenship_type = $request->citizenship_type;
            $employee->citizenship_country = $request->citizenship_country;
            $employee->telephone_no = $request->telephone_no;
            $employee->mobile_no = $request->mobile_no;
            $employee->email_address = $request->email_address;
            $employee->issued_id = $request->issued_id;
            $employee->issued_id_no = $request->issued_id_no;
            $employee->issued_date = $request->issued_date;
            $employee->save();
            return response(['code' => 200, 'message' => 'Personal Information updated']);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function getEmployeeByDivisionSection(Request $request)
    {
        try {

            $division = $request->division;
            $section = $request->section;
            $type = $request->type;

            $employees = DB::table('hris.employees as e')
                ->leftJoin('tdh_user.users as u', 'e.user_id', '=', 'u.id')
                ->leftJoin('tdh_user.designation as d', 'u.designation', '=', 'd.id')
                ->where(function ($query) use ($division, $section, $type) {
                    $query->where('u.division', '=', $division);
                    if ($section)
                        $query->where('u.section', '=', $section);
                    if ($type)
                        $query->where('e.employee_type', '=', $type);
                })
                ->orderBy('u.fname', 'ASC')
                ->orderBy('u.lname', 'ASC')
                ->get(['e.user_id', 'u.fname', 'u.mname', 'u.lname', 'u.suffix', 'u.picture', 'd.description as designation']);

            return response(['code' => 200, 'results' => $employees]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function getEmployee(Request $request)
    {
        try {
            $search = [];
            $no_employee_no = $request->boolean('no_employee_no');
            if ($request->fname != "")
                $search[] = ["u.fname", "LIKE", "%" . $request->fname . "%"];
            if ($request->lname != "")
                $search[] = ["u.lname", "LIKE", "%" . $request->lname . "%"];

            $employees = DB::table('hris.employees as e')
                ->join('tdh_user.users as u', 'e.user_id', '=', 'u.id')
                ->join('tdh_user.designation as d', 'u.designation', '=', 'd.id')
                ->join('tdh_user.section as s', 'u.section', '=', 's.id')
                ->where($search);

            if ($no_employee_no) {
                $employees->whereRaw("TRIM(COALESCE(e.employee_no, '')) = ''");
            }

            $paginator = $employees
                ->orderBy('u.fname', 'ASC')
                ->orderBy('u.lname', 'ASC')
                ->paginate(10, ['e.user_id', 'u.fname', 'u.mname', 'u.lname', 'u.suffix', 'u.picture', 'd.description as designation', 's.description as section']);

            return response([
                'code' => 200,
                'results' => $paginator->items(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                    'has_more_pages' => $paginator->hasMorePages(),
                ]
            ]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function updateEmployeeStatus(Request $request)
    {
        try {
            $employee = Employee::where('user_id', $request->user_id)->first();
            if (!$employee) {
                return response(['code' => 500, 'message' => 'Employee ID dont exists']);
            }
            $employee->employee_type = $request->employee_type;
            $employee->date_hired = $request->date_hired;
            $employee->date_resigned = $request->date_resigned;
            $employee->save();
            return response(['code' => 200, 'message' => "Employee status updated"]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    public function getEmployees(Request $request)
    {
        try {
            return response()->json($this->employeeService->getEmployees());
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }
}
