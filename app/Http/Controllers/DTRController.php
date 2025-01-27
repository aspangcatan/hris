<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
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
                ->where('userid', '=', Auth::id())
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
                    return response()->json(['error' => 'Android device not yet registered'], 500);
                }

                if (Auth::user()->android_id != $request->imei) {
                    return response()->json(['error' => 'Android device not matched'], 500);
                }

                //TODO: return only id,fname,mname,lname
                $user = Auth::user()->only(['id', 'fname', 'mname', 'lname']);
                return response()->json($user);
            } else {
                return response()->json(['error' => "Invalid credentials"], 500);
            }
            return ['code' => 500, 'message' => 'Invalid credentials'];
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }
}
