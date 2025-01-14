<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            $time_in = Carbon::now();
            // Insert a new time in record
            DB::table('dtr_logs')->insert([
                'userid' => 1116,
                'status' => $request->status,
                'type' => 1, // 1 for mobile dtr app
                'created_at' => $time_in
            ]);

            return response()->json(['message' => $time_in]);
        } catch (\Exception $exception) {
            return response()->json($exception->getMessage(), 500);
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
}
