<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

            // Insert into database
            DB::table('dtr_logs')->insert([
                'userid' => $request->input('userid'),
                'status' => $request->input('status'),
                'type' => 1, // 1 for mobile DTR app
                'photo_path' => $filePath,
                'created_at' => Carbon::now(),
            ]);

            return response()->json(['message' => 'Successfully Timed ' . $request->input('status')]);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
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

                if (!Auth::user()->android_id) {
                    return response()->json(['error' => 'Device not yet registered'], 500);
                }

                if (Auth::user()->android_id != $request->imei) {
                    return response()->json(['error' => 'Device not matched'], 500);
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

                return response()->json($userData);
            } else {
                return response()->json(['error' => "Invalid credentials"], 500);
            }
            return ['code' => 500, 'message' => 'Invalid credentials'];
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
            $cutoff = $request->cutoff ?? 'full'; // default is full month

            // Determine start and end day based on cutoff
            $startDay = 1;
            $endDay = Carbon::createFromDate($year, $month, 1)->daysInMonth;

            if ($cutoff === 'first') {
                $endDay = 15;
            } elseif ($cutoff === 'second') {
                $startDay = 16;
            }

            // Fetch all employees in the current division
            $employees = DB::table('tdh_user.users')
                ->where('division', Auth::user()->division)
                ->where('id', Auth::id())
                ->get();

            $dtrData = [];

            $formattedDate = Carbon::parse("$year-$month-01")->format('F Y');
            $in_charged = collect(self::getInCharged(Auth::user()->division))->only(['fname', 'mname', 'lname', 'suffix', 'title']);

            $fname = $in_charged['fname'] ?? '';
            $mname = !empty($in_charged['mname']) ? strtoupper(substr($in_charged['mname'], 0, 1)) . '.' : '';
            $lname = $in_charged['lname'] ?? '';
            $suffix = !empty($in_charged['suffix']) ? ' ' . strtoupper($in_charged['suffix']) : '';
            $title = !empty($in_charged['title']) ? ', ' . strtoupper($in_charged['title']) : '';

            $in_charged = strtoupper(trim("$fname $mname $lname$suffix$title"));

            // Fetch all holidays for the specified month and year
            $holidays = DB::table('calendar_holidays')
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->get();

            foreach ($employees as $employee) {
                // Get all DTR logs for this employee within the full month
                $logs = DB::table('dtr_logs')
                    ->whereMonth('created_at', $month)
                    ->whereYear('created_at', $year)
                    ->where('userid', '=', $employee->id)
                    ->orderBy('created_at', 'ASC')
                    ->get();

                // Generate full month range, populate only within cutoff
                $days = collect(range(1, Carbon::createFromDate($year, $month, 1)->daysInMonth))->map(function ($day) use ($month, $year, $startDay, $endDay, $logs, $holidays) {
                    $currentDay = Carbon::createFromDate($year, $month, $day)->toDateString();
                    $dayName = Carbon::createFromDate($year, $month, $day)->format('l');
                    $isHoliday = $holidays->contains('date', $currentDay);

                    $data = [
                        'day' => $day,
                        'date_timein' => '',
                        'date_timeout' => '',
                        'day_name' => $dayName,
                    ];

                    if ($day >= $startDay && $day <= $endDay) {
                        $dayLogs = $logs->filter(function ($log) use ($currentDay) {
                            return Carbon::parse($log->created_at)->toDateString() === $currentDay;
                        });

                        if ($dayLogs->isNotEmpty()) {
                            $timeInLog = $dayLogs->firstWhere('status', 'IN');
                            $timeOutLog = $dayLogs->where('status', 'OUT')->sortByDesc('created_at')->first();

                            $data['date_timein'] = $timeInLog ? Carbon::parse($timeInLog->created_at)->format('H:i') : '';
                            $data['date_timeout'] = $timeOutLog ? Carbon::parse($timeOutLog->created_at)->format('H:i') : '';
                        } elseif ($isHoliday) {
                            $data['date_timein'] = $dayName . '-HOLIDAY';
                            $data['date_timeout'] = $dayName . '-HOLIDAY';
                        } else {
                            $data['date_timein'] = $dayName;
                            $data['date_timeout'] = $dayName;
                        }
                    }

                    return $data;
                });

                $dtrData[] = [
                    'name' => strtoupper($employee->fname . ' ' .
                        ($employee->mname ? strtoupper(substr($employee->mname, 0, 1)) . '.' : '') .
                        ' ' . $employee->lname),
                    'in_charged' => $in_charged,
                    'date' => $formattedDate,
                    'dtrs' => $days
                ];
            }

            // Generate PDF
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

    public function getInCharged($division)
    {
        return DB::table('tdh_user.division as d')
            ->join('tdh_user.users as u', 'd.head', '=', 'u.id')
            ->where('d.id', '=', $division)
            ->first();
    }

    public function getEmployeeDtr(Request $request)
    {
        try {
            $search_division = $request->search_division;
            $search_section = $request->search_section;
            $search_fname = $request->search_fname;
            $search_lname = $request->search_lname;

            if ($search_section == 0) {
                $search_section = Auth::user()->section;
            }

            $today = Carbon::now()->toDateString(); // Ensure same format as MySQL CURDATE()

            // Start query
            $employee = DB::table('tdh_user.users as u')
                ->join('hris.employees as e', 'u.id', '=', 'e.user_id')
                ->join('tdh_user.designation as d1', 'd1.id', '=', 'u.designation')
                ->leftJoin('hris.dtr_logs as d', function ($join) {
                    $join->on('u.id', '=', 'd.userid');
                })
                ->select(
                    'u.id',
                    'u.fname',
                    'u.mname',
                    'u.lname',
                    'd1.description as designation',
                    'u.picture',
                    'u.section',
                    'u.division',
                    DB::raw("(SELECT MIN(d1.created_at) FROM hris.dtr_logs d1 WHERE d1.userid = u.id AND d1.status = 'IN' AND DATE(d1.created_at) = '$today') as time_in"),
                    DB::raw("(SELECT MAX(d2.created_at) FROM hris.dtr_logs d2 WHERE d2.userid = u.id AND d2.status = 'OUT' AND DATE(d2.created_at) = '$today') as time_out")
                )
                ->where(function ($query) {
                    // Exclude 'Resigned' employees and include those with NULL 'employee_type'
                    $query->where('e.employee_type', '!=', 'Resigned')
                        ->orWhereNull('e.employee_type');
                })
                ->groupBy('u.id', 'u.fname', 'u.mname', 'u.lname', 'u.section', 'u.division')
                ->orderByRaw('time_in IS NULL, time_in ASC'); // Sort by earliest time_in, NULLs last

            // Apply dynamic filters
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

            $result = $employee->get();

            return response()->json($result);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }


    public function getDivisionList()
    {
        try {
            $list = DB::table('tdh_user.division')
                ->get(['id', 'description']);

            return response()->json($list);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function getSectionList(Request $request)
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
}
