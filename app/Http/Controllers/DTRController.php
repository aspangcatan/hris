<?php

namespace App\Http\Controllers;

use Barryvdh\Snappy\Facades\SnappyPdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DTRController extends Controller
{
    public function store(Request $request)
    {
        try {
            DB::table('temp_dtr_logs')->updateOrInsert(
                [
                    'user_id' => $request->user_id,
                    'month' => $request->month,
                    'year' => $request->year
                ],
                [
                    'absent' => $request->absent,
                    'late' => $request->late
                ]);
            return response()->json(['message' => "DTR stored successfully"]);
        } catch (\Exception $exception) {
            return response()->json($exception->getMessage(), 500);
        }
    }

    public function getUserInfoById(Request $request)
    {
        try {
            // Validate input
            $request->validate([
                'employee_no' => 'required|string|regex:/^\d+$/',
            ]);

            $user = DB::table('tdh_user.users')
                ->leftJoin('hris.employees', 'users.id', '=', 'employees.user_id')
                ->leftJoin('tdh_user.designation', 'users.designation', '=', 'designation.id')
                ->leftJoin('tdh_user.division', 'users.division', '=', 'division.id')
                ->select(
                    'users.id',
                    'username',
                    'fname',
                    'mname',
                    'lname',
                    'suffix',
                    'designation.description as designation',
                    'division.description as division'
                )
                ->where('employees.employee_no', '=', (int)$request->employee_no)
                ->first();

            if (!$user) {
                return response()->json(['error' => 'User not found.'], 404);
            }

            return response()->json($user);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }


    public function getUserInfo(Request $request)
    {
        try {
            $user = DB::table('tdh_user.users')
                ->leftJoin('tdh_user.designation', 'users.designation', '=', 'designation.id')
                ->leftJoin('tdh_user.division', 'users.division', '=', 'division.id')
                ->select('users.id', 'fname', 'mname', 'lname', 'suffix', 'is_deployed', 'designation.description as designation', 'division.description as division')
                ->where('users.username', '=', $request->username)
                ->first();
            return response()->json($user);
        } catch (\Exception $exception) {
            return response()->json($exception->getMessage(), 500);
        }
    }

    public function verifyPin(Request $request)
    {
        try {
            $user = DB::table('tdh_user.users')
                ->where('username', '=', $request->username)
                ->first();

            if ($user && Hash::check($request->security_pin, $user->security_pin)) {
                return response()->json(['message' => 'Pin verified successfully']);
            } else {
                return response()->json(['error' => 'Invalid Pin'], 500);
            }
        } catch (\Exception $exception) {
            return response()->json($exception->getMessage(), 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $user_id = $request->user_id;
            $year = $request->year;
            $list = DB::table('temp_dtr_logs')
                ->where(function ($query) use ($user_id, $year) {
                    $query->where('user_id', '=', $user_id);
                    $query->where('year', '=', $year);
                })
                ->orderBy('month', 'ASC')
                ->get();

            return response()->json($list);
        } catch (\Exception $exception) {
            return response()->json($exception->getMessage(), 500);
        }
    }

    public function delete(Request $request)
    {
        try {
            DB::table('temp_dtr_logs')
                ->where('id', '=', $request->id)
                ->delete();
            return response()->json(['message' => 'DTR successfully deleted']);
        } catch (\Exception $exception) {
            return response()->json($exception->getMessage(), 500);
        }
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    public function storeDtr(Request $request)
    {
        try {

            // Check IF DEPLOYED STATUS IS MATCH
            $checkStatus = DB::table('tdh_user.users')
                ->where('id', '=', $request->input('userid'))
                ->where('is_deployed', '=', $request->input('is_deployed'))
                ->first();

            if (!$checkStatus) {
                return response()->json(['error' => "Your deployment status does not match, please logout then login again"], 500);
            }

            // Check if photo exists
            $photo = $request->input('photo');
            if (!$photo) {
                return response()->json(['error' => 'Photo file is missing'], 400);
            }

            // Decode Base64 image
            $image = base64_decode($photo);
            if (!$image) {
                return response()->json(['error' => 'Invalid image data'], 400);
            }


            $monthYear = Carbon::now()->format('mY'); // e.g., "012025" for January 2025
            $filePath = 'img/' . $request->input('userid') . '/' . $monthYear . '/' . uniqid() . '.jpg';

            // Save image to storage
            Storage::disk('public')->put($filePath, $image);

            $eventTime = $request->has('event_time')
                ? Carbon::parse($request->input('event_time'))
                : Carbon::now(); // fallback if not provided

            // Insert into database
            DB::table('dtr_logs')->insert([
                'userid' => $request->input('userid'),
                'status' => $request->input('status'),
                'type' => 1, // 1 for mobile DTR app
                'photo_path' => $filePath,
                'created_at' => $eventTime,
            ]);

            return response()->json(['message' => 'Successfully Timed ' . $request->input('status')]);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function storeManual(Request $request)
    {
        $request->validate([
            'status' => 'required|in:IN,OUT',
            'date' => 'required|date',
            'time' => 'required',
            'log_id' => 'nullable|integer'
        ]);

        try {
            $datetime = Carbon::parse("{$request->date} {$request->time}");
            $logId = $request->input('log_id', 0);

            if ($logId != 0) {
                // Update existing log when log_id is NOT zero
                DB::table('dtr_logs')
                    ->where('id', $logId)
                    ->update([
                        'status' => $request->status,
                        'created_at' => $datetime,
                        'updated_at' => now(),
                    ]);
                return response()->json(['message' => "Log successfully updated!"]);
            } else {
                // Insert new log when log_id == 0
                DB::table('dtr_logs')->insert([
                    'userid' => $request->userid,
                    'status' => $request->status,
                    'type' => 2, // manual
                    'photo_path' => null,
                    'created_at' => $datetime,
                    'updated_at' => now(),
                ]);
                return response()->json(['message' => "Log successfully saved!"]);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    public function deleteTimeLog(Request $request)
    {
        try {
            DB::table('hris.dtr_logs')->where('id', '=', $request->log_id)->delete();
            return response()->json(['message' => "DTR log successfully deleted"]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }


    public function getDTRLast3(Request $request)
    {
        try {
            // Get the current date
            $currentDate = Carbon::now();

            // Calculate the start date (six days before the current date)
            $startDate = $currentDate->copy()->subDays(6);

            // Create a collection of the last seven days starting with the current day
            $days = collect(range(0, 6))->map(function ($offset) use ($currentDate) {
                $date = $currentDate->copy()->subDays($offset);
                return [
                    'date' => $date->format('F j, Y'), // Human-readable format for response
                    'raw_date' => $date->toDateString(), // Raw date for comparison
                    'date_timein' => "--:-- --",
                    'date_timeout' => "--:-- --",
                ];
            });

            // Get all existing DTR logs for the last seven days
            $logs = DB::table('dtr_logs')
                ->whereDate('created_at', '>=', $startDate->toDateString())
                ->whereDate('created_at', '<=', $currentDate->toDateString())
                ->where('userid', '=', $request->userid)
                ->orderBy('created_at', 'ASC')
                ->get();

            // Map each day and attach DTR log data if available
            $result = $days->map(function ($dayData) use ($logs) {
                // Filter logs for the specific day
                $dayLogs = $logs->filter(function ($log) use ($dayData) {
                    return Carbon::parse($log->created_at)->toDateString() === $dayData['raw_date'];
                });

                // Get the time-in and time-out logs
                $timeInLog = $dayLogs->firstWhere('status', 'IN');
                $timeOutLog = $dayLogs
                    ->where('status', 'OUT')
                    ->sortByDesc('created_at')
                    ->first();

                if ($timeInLog) {
                    $dayData['date_timein'] = Carbon::parse($timeInLog->created_at)->format('h:i A');
                }

                if ($timeOutLog) {
                    $dayData['date_timeout'] = Carbon::parse($timeOutLog->created_at)->format('h:i A');
                }

                // Remove raw_date from the response
                unset($dayData['raw_date']);
                return $dayData;
            });

            return response()->json(['results' => $result]);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }


    public function getDTRLogs(Request $request)
    {
        try {
            // Retrieve the month and year from the request
            $month = $request->month;
            $year = $request->year;

            $userid = Auth::id();
            if (isset($request->userid)) {
                $userid = $request->userid;
            }

            // Get the actual number of days in the specified month
            $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;

            // Create a collection of all days in the given month
            $days = collect(range(1, $daysInMonth))->map(function ($day) use ($month, $year) {
                return [
                    'day' => $day,
                    'date_timein' => null,
                    'date_timeout' => null,
                ];
            });

            // Get all existing DTR logs for the specified month and year
            $logs = DB::table('dtr_logs')
                ->whereMonth('created_at', $month)
                ->whereYear('created_at', $year)
                ->where('userid', '=', $userid)
                ->orderBy('created_at', 'ASC')
                ->get();

            // Map each day and attach DTR log data if available
            $result = $days->map(function ($dayData) use ($logs, $month, $year) {
                // Build the full date for the current day
                $currentDay = Carbon::createFromDate($year, $month, $dayData['day'])->toDateString();

                // Filter logs for the specific day
                $dayLogs = $logs->filter(function ($log) use ($currentDay) {
                    return Carbon::parse($log->created_at)->toDateString() === $currentDay;
                });

                // Get the time-in and time-out logs
                $timeInLog = $dayLogs->firstWhere('status', 'IN');
                $timeOutLog = $dayLogs
                    ->where('status', 'OUT')
                    ->sortByDesc('created_at')
                    ->first();

                if ($timeInLog) {
                    $dayData['date_timein'] = $timeInLog->created_at;
                }

                if ($timeOutLog) {
                    $dayData['date_timeout'] = $timeOutLog->created_at;
                }

                return $dayData;
            });

            return response()->json(['results' => $result, 'raw_data' => $logs]);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function login(Request $request)
    {
        try {
            $credentials = ['username' => $request->username, 'password' => $request->password];
            if (Auth::attempt($credentials)) {
                //CHECK IF user android device is registered
                if (Auth::user()->username != 'jparker') {
                    if (!Auth::user()->android_id) {
                        return response()->json(['error' => 'Device not yet registered'], 500);
                    }

                    if (Auth::user()->android_id != $request->imei) {
                        return response()->json(['error' => 'Device not matched'], 500);
                    }
                }

                $userData = DB::table('tdh_user.users as u')
                    ->join('tdh_user.section as s', 'u.section', '=', 's.id')
                    ->join('tdh_user.division as d', 'u.division', '=', 'd.id')
                    ->join('tdh_user.designation as de', 'u.designation', '=', 'de.id')
                    ->where('u.id', Auth::id())
                    ->select([
                        'u.id',
                        'u.fname',
                        'u.mname',
                        'u.lname',
                        'u.picture',
                        'u.is_deployed',
                        's.description as department',
                        'd.description as division',
                        'de.description as designation'
                    ])
                    ->first();

                $userData->current_date = Carbon::now()->toDateTimeString();

                return response()->json($userData);
            } else {
                return response()->json(['error' => "Invalid credentials"], 500);
            }
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

//PRINT DTR
    public function printDTR(Request $request)
    {
        try {
            $month = $request->month;
            $year = $request->year;
            $cutoff = $request->cutoff ?? 'full';
            $userid = $request->userid ?? Auth::id();

            $startDay = 1;
            $endDay = Carbon::createFromDate($year, $month, 1)->daysInMonth;
            if ($cutoff === 'first') $endDay = 15;
            elseif ($cutoff === 'second') $startDay = 16;

            $employees = DB::table('tdh_user.users')->where('id', $userid)->get();

            $dtrData = [];
            $formattedDate = Carbon::parse("$year-$month-01")->format('F Y');

            // Get in-charged
            if (self::checkIfDivisionHead($userid)) {
                $division_id = 1;
            } else {
                $division_id = self::getDivisionByUser($userid);
            }

            $in_charged = collect(self::getInCharged($division_id))->only(['fname', 'mname', 'lname', 'suffix', 'title']);
            $fname = $in_charged['fname'] ?? '';
            $mname = !empty($in_charged['mname']) ? strtoupper(substr($in_charged['mname'], 0, 1)) . '.' : '';
            $lname = $in_charged['lname'] ?? '';
            $suffix = !empty($in_charged['suffix']) ? ' ' . strtoupper($in_charged['suffix']) : '';
            $title = !empty($in_charged['title']) ? ', ' . strtoupper($in_charged['title']) : '';
            $in_charged = strtoupper(trim("$fname $mname $lname$suffix$title"));

            // Get holidays
            $holidays = DB::table('calendar_holidays')
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->get()
                ->pluck('date')
                ->map(fn($d) => Carbon::parse($d)->toDateString())
                ->toArray();

            foreach ($employees as $employee) {

                $logs = DB::table('dtr_logs')
                    ->whereMonth('created_at', $month)
                    ->whereYear('created_at', $year)
                    ->where('userid', '=', $employee->id)
                    ->orderBy('created_at', 'ASC')
                    ->get();

                // Leave data
                $leavesRaw = DB::table('hris.leave_applications as la')
                    ->join('hris.leave_types as lt', 'la.leave_id', '=', 'lt.id')
                    ->join('hris.leave_date_applied as lda', 'la.id', '=', 'lda.leave_application_id')
                    ->where('la.user_id', $employee->id)
                    ->whereMonth('lda.date_applied', $month)
                    ->whereYear('lda.date_applied', $year)
                    ->select(DB::raw('DATE(lda.date_applied) as date_applied'), 'lt.code')
                    ->get();

                $leaves = [];
                foreach ($leavesRaw as $l) {
                    $leaves[Carbon::parse($l->date_applied)->toDateString()] = $l->code;
                }

                // H.O
                $hosRaw = DB::table('hris.hospital_orders as ho')
                    ->join('hris.hospital_order_employees as hoe', 'ho.id', '=', 'hoe.ho_id')
                    ->where('hoe.userid', $employee->id)
                    ->where(function ($q) use ($month, $year) {
                        $q->whereMonth('ho.start_date', $month)->whereYear('ho.start_date', $year)
                            ->orWhereMonth('ho.end_date', $month)->whereYear('ho.end_date', $year);
                    })
                    ->select(DB::raw('DATE(ho.start_date) as start_date'), DB::raw('DATE(ho.end_date) as end_date'), 'ho.order_number')
                    ->get();

                $hospitalOrders = [];
                foreach ($hosRaw as $ho) {
                    $start = Carbon::parse($ho->start_date);
                    $end = Carbon::parse($ho->end_date);
                    while ($start->lte($end)) {
                        $hospitalOrders[$start->toDateString()] = 'H.O #' . $ho->order_number;
                        $start->addDay();
                    }
                }

                // Build daily DTR
                $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
                $days = collect(range(1, $daysInMonth))->map(function ($day) use ($month, $year, $startDay, $endDay, $logs, $holidays, $leaves, $hospitalOrders) {

                    $currentDay = Carbon::createFromDate($year, $month, $day)->toDateString();
                    $dayName = Carbon::createFromDate($year, $month, $day)->format('l');
                    $isHoliday = in_array($currentDay, $holidays);

                    $data = [
                        'day' => $day,
                        'day_name' => $dayName,
                        'isInsideCutoff' => ($day >= $startDay && $day <= $endDay),
                        'isEmpty' => true,

                        'date_timein_am' => '',
                        'date_timeout_am' => '',
                        'date_timein_pm' => '',
                        'date_timeout_pm' => '',
                    ];

                    if (!$data['isInsideCutoff']) return $data;

                    // Get logs of the day
                    $dayLogs = $logs->filter(fn($log) =>
                        Carbon::parse($log->created_at)->toDateString() === $currentDay
                    );

                    if ($dayLogs->isNotEmpty()) {

                        // Bucket logs by time
                        $amIns = [];
                        $pmIns = [];
                        $amOuts = [];
                        $pmOuts = [];

                        foreach ($dayLogs as $log) {
                            $time = Carbon::parse($log->created_at);
                            $t = $time->format('H:i');

                            $isAM = $time->format('H:i:s') <= '12:00:00';


                            if ($log->status === 'IN') {
                                $isAM ? $amIns[] = $t : $pmIns[] = $t;
                            } else {
                                $isAM ? $amOuts[] = $t : $pmOuts[] = $t;
                            }
                        }

                        // AM
                        if (!empty($amIns)) $data['date_timein_am'] = min($amIns);
                        if (!empty($amOuts)) $data['date_timeout_am'] = max($amOuts);

                        // PM
                        if (!empty($pmIns)) $data['date_timein_pm'] = min($pmIns);
                        if (!empty($pmOuts)) $data['date_timeout_pm'] = max($pmOuts);

                        if (!empty($amIns) || !empty($pmIns) || !empty($amOuts) || !empty($pmOuts)) {
                            $data['isEmpty'] = false;
                        }
                    }

                    // LEAVE / HOLIDAY / H.O
                    if ($data['isEmpty']) {
                        if (!empty($leaves[$currentDay])) {
                            $code = $leaves[$currentDay];
                        } elseif (!empty($hospitalOrders[$currentDay])) {
                            $code = $hospitalOrders[$currentDay];
                        } elseif ($isHoliday) {
                            $code = 'HOLIDAY';
                        } else {
                            $code = null;
                        }

                        if ($code !== null) {
                            $data['date_timein_am'] = $code;
                            $data['date_timeout_am'] = $code;
                            $data['date_timein_pm'] = $code;
                            $data['date_timeout_pm'] = $code;
                        }
                    }

                    return $data;
                });

                $dtrData[] = [
                    'name' => strtoupper($employee->fname . ' ' . ($employee->mname ? strtoupper(substr($employee->mname, 0, 1)) . '.' : '') . ' ' . $employee->lname . ($employee->suffix ? ' ' . strtoupper($employee->suffix) : '')),
                    'in_charged' => $in_charged,
                    'date' => $formattedDate,
                    'dtrs' => $days,
                    'start_day' => $startDay,
                    'end_day' => $endDay
                ];
            }

            $pdf = SnappyPdf::loadView('pdf.dtr', ['employees' => $dtrData])
                ->setPaper('A4', 'portrait')
                ->setOption('margin-top', 5)
                ->setOption('margin-bottom', 5)
                ->setOption('margin-left', 5)
                ->setOption('margin-right', 5)
                ->setOption('enable-smart-shrinking', true)
                ->setOption('no-images', false)
                ->setOption('disable-javascript', true)
                ->setOption('load-error-handling', 'ignore')
                ->setOption('dpi', 96);

            return $pdf->inline('DTR.pdf');

        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public
    function getInCharged($division)
    {
        return DB::table('tdh_user.division as d')
            ->join('tdh_user.users as u', 'd.head', '=', 'u.id')
            ->where('d.id', '=', $division)
            ->first();
    }

    public
    function checkIfDivisionHead($id)
    {
        return DB::table('tdh_user.division')
            ->where('head', '=', $id)
            ->exists();
    }

    public
    function getDivisionByUser($id)
    {
        return DB::table('tdh_user.users')
            ->where('id', '=', $id)
            ->value('division');
    }

    public
    function getEmployeeDtr(Request $request)
    {
        try {
            $search_division = $request->search_division;
            $search_section = $request->search_section;
            $search_fname = $request->search_fname;
            $search_lname = $request->search_lname;

            // Default to logged-in user's section if search_section = 0
            if ($search_section == 0) {
                $search_section = Auth::user()->section;
            }

            $employee = DB::table('tdh_user.users as u')
                ->join('hris.employees as e', 'u.id', '=', 'e.user_id')
                ->join('tdh_user.designation as d1', 'd1.id', '=', 'u.designation')
                ->select(
                    'u.id',
                    'u.fname',
                    'u.mname',
                    'u.lname',
                    'd1.description as designation',
                    'u.picture',
                    'u.section',
                    'u.division'
                )
                ->where(function ($query) {
                    $query->where('e.employee_type', '!=', 'Resigned')
                        ->orWhereNull('e.employee_type');
                });

            // Apply filters
            if (!empty($search_section)) {
                $employee->where('u.section', '=', $search_section);
            }

            if (!empty($search_division)) {
                $employee->where('u.division', '=', $search_division);
            }

            if (!empty($search_fname)) {
                $employee->where('u.fname', 'LIKE', "%$search_fname%");
            }

            if (!empty($search_lname)) {
                $employee->where('u.lname', 'LIKE', "%$search_lname%");
            }

            $result = $employee->orderBy('u.lname')->get();

            return response()->json($result);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }


    public
    function getDivisionList()
    {
        try {
            $list = DB::table('tdh_user.division')
                ->get(['id', 'description']);

            return response()->json($list);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public
    function getSectionList(Request $request)
    {
        try {
            $list = DB::table('tdh_user.section')
                ->where('division', '=', $request->division)
                ->get(['id', 'description']);

            return response()->json($list);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public
    function getLatestLog(Request $request)
    {
        try {
            $request->validate([
                'userid' => 'required|integer',
            ]);

            $userid = $request->userid;
            $logs = DB::table('dtr_logs')
                ->where('userid', '=', $userid)
                ->orderBy('created_at', 'desc')
                ->first();
            if (!$logs)
                return 'OUT';
            return $logs->status;
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public
    function getRecentLogs(Request $request)
    {
        try {
            $request->validate([
                'userid' => 'required|integer',
            ]);

            $userid = $request->userid;
            $logs = DB::table('dtr_logs')
                ->where('userid', $userid)
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->orderBy('created_at', 'desc')
                ->get();
            return response()->json($logs);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public
    function getInOutLogs(Request $request): JsonResponse
    {
        try {
            // Validate userid
            $request->validate([
                'userid' => 'required|integer',
            ]);

            $userId = $request->userid;
            $today = Date::today(); // Today's date without time

            // Get first IN log for today
            $start = DB::table('dtr_logs')
                ->where('userid', $userId)
                ->where('status', 'IN')
                ->whereDate('created_at', $today)
                ->orderBy('created_at', 'ASC')
                ->first();

            // Get last OUT log for today
            $end = DB::table('dtr_logs')
                ->where('userid', $userId)
                ->where('status', 'OUT')
                ->whereDate('created_at', $today)
                ->orderBy('created_at', 'DESC')
                ->first();

            return response()->json([
                'in' => $start->created_at ?? null,
                'out' => $end->created_at ?? null,
            ]);

        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }
}
