<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{
    //
    public function getSchedules(Request $request)
    {
        try {
            if (!Auth::check()) {
                return redirect()->route('login')->with('error', 'Unauthorized access');
            }

            // Get the selected month & year (default: current)
            $month = $request->input('month', date('m'));
            $year = $request->input('year', date('Y'));
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

            // Get employees from the same section
            $employees = DB::table('tdh_user.users as u')
                ->join('tdh_user.designation as d', 'u.designation', '=', 'd.id')
                ->where('u.section', Auth::user()->section)
                ->selectRaw("
                u.id as employee_id,
                CONCAT(
                    u.fname,
                    CASE WHEN u.mname IS NOT NULL AND u.mname != '' THEN CONCAT(' ', u.mname) ELSE '' END,
                    ' ', u.lname,
                    CASE WHEN u.suffix IS NOT NULL AND u.suffix != '' THEN CONCAT(' ', u.suffix) ELSE '' END
                ) AS name,
                COALESCE(d.description, 'No Designation') AS designation
            ")
                ->get();

            // Get schedules for the selected month
            $schedules = DB::table('hris.schedules as s')
                ->join('hris.schedule_master_lists as sml', 's.schedule_id', '=', 'sml.id')
                ->whereYear('s.schedule_date', $year)
                ->whereMonth('s.schedule_date', $month)
                ->select('s.employee_id', 's.schedule_date', 'sml.code')
                ->get();

            // Structure the schedule data
            $scheduleList = [];
            foreach ($employees as $employee) {
                $schedule = [
                    'id' => $employee->employee_id,
                    'name' => $employee->name,
                    'designation' => $employee->designation,
                    'schedule' => []
                ];

                // Initialize all days with "RD" as a numeric array
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $schedule['schedule'][] = "RD";
                }

                // Fill in the schedule with only the "code"
                foreach ($schedules as $sched) {
                    if ($sched->employee_id == $employee->employee_id) {
                        $day = (int)date('j', strtotime($sched->schedule_date)); // Extract day from date
                        $schedule['schedule'][$day - 1] = $sched->code; // Store only the code
                    }
                }

                $scheduleList[] = $schedule;
            }

            return response()->json(['schedules' => $scheduleList, 'days' => $daysInMonth]);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }


    public function getScheduleMasterList()
    {
        try {
            return response()->json(DB::table('hris.schedule_master_lists')->get());
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function setEmployeeSchedule(Request $request)
    {
        try {
            if ($request->schedule_id == 0) {

                $employee_id = $request->employee_id;
                $schedule_date = $request->schedule_date;

                DB::table('hris.schedules')->where(function ($query) use ($employee_id, $schedule_date) {
                    $query->where('employee_id', '=', $employee_id);
                    $query->where('schedule_date', '=', $schedule_date);
                })->delete();
            } else {
                DB::table('hris.schedules')->updateOrInsert(
                    [ // Condition to find an existing record
                        "employee_id" => $request->employee_id,
                        "schedule_date" => $request->schedule_date,
                    ],
                    [ // Values to insert or update
                        "schedule_id" => $request->schedule_id,
                        "updated_by" => Auth::id(),
                        "updated_at" => Carbon::now(),
                        "created_at" => Carbon::now()
                    ]
                );
            }
            return response()->json(['message' => "Schedule set successfully"]);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }
}
