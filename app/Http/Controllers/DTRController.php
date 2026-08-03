<?php

namespace App\Http\Controllers;

use App\Services\PermissionService;
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
    public function __construct(private PermissionService $permissions) {}

    private function getCurrentAccessRights(): string
    {
        return strtolower((string) session('access_rights'));
    }

    private function getScheduleManagedSectionIds(bool $includeOwnSectionFallback = false): array
    {
        $managedSectionIds = DB::table('hris.schedule_user_sections')
            ->where('user_id', Auth::id())
            ->pluck('section_id')
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();

        if (empty($managedSectionIds) && $includeOwnSectionFallback && Auth::check() && Auth::user()->section) {
            $managedSectionIds = [(int) Auth::user()->section];
        }

        return $managedSectionIds;
    }

    private function canAccessEmployeeDtrModule(): bool
    {
        return $this->permissions->can(Auth::id(), 'dtr.view_others');
    }

    private function canManageRawTimelog(): bool
    {
        return config('hris.dtr_raw_timelog_editing_enabled')
            && $this->permissions->can(Auth::id(), 'dtr.edit');
    }

    private function canViewEmployeeDtrForUser(int $userId): bool
    {
        if (!Auth::check()) {
            return false;
        }

        if ($userId === (int) Auth::id()) {
            return true;
        }

        $accessRights = $this->getCurrentAccessRights();

        if ($accessRights === 'admin') {
            return true;
        }

        if ($accessRights === 'schedule') {
            $managedSections = $this->getScheduleManagedSectionIds(false);
            if (empty($managedSections)) {
                return false;
            }

            return DB::table('tdh_user.users')
                ->where('id', $userId)
                ->whereIn('section', $managedSections)
                ->exists();
        }

        if ((bool) session('is_division_head') || $accessRights === 'dh') {
            return DB::table('tdh_user.users')
                ->where('id', $userId)
                ->where('division', Auth::user()->division)
                ->exists();
        }

        if ((bool) session('is_section_head')) {
            return DB::table('tdh_user.users')
                ->where('id', $userId)
                ->where('section', Auth::user()->section)
                ->exists();
        }

        return false;
    }

    private function normalizePrintableUserIds(Request $request): array
    {
        $requestedIds = collect();

        $userIdsInput = $request->input('userids', []);
        if (is_string($userIdsInput)) {
            $requestedIds = $requestedIds->merge(explode(',', $userIdsInput));
        } elseif (is_array($userIdsInput)) {
            $requestedIds = $requestedIds->merge($userIdsInput);
        }

        if ($request->filled('userid')) {
            $requestedIds->push($request->input('userid'));
        }

        $normalized = $requestedIds
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        return empty($normalized) ? [(int) Auth::id()] : $normalized;
    }

    private function applyEmployeeDtrScope($query, ?int $requestedDivision, ?int $requestedSection): bool
    {
        $accessRights = $this->getCurrentAccessRights();

        if ($accessRights === 'admin') {
            if (!is_null($requestedSection)) {
                $query->where('u.section', $requestedSection);
            }

            if (!is_null($requestedDivision)) {
                $query->where('u.division', $requestedDivision);
            }

            return true;
        }

        if ($accessRights === 'schedule') {
            $managedSections = $this->getScheduleManagedSectionIds(false);
            if (empty($managedSections)) {
                $query->whereRaw('1 = 0');
                return true;
            }

            if (!is_null($requestedSection)) {
                if (!in_array($requestedSection, $managedSections, true)) {
                    $query->whereRaw('1 = 0');
                    return true;
                }

                $query->where('u.section', $requestedSection);
            } else {
                $query->whereIn('u.section', $managedSections);
            }

            if (!is_null($requestedDivision)) {
                $query->where('u.division', $requestedDivision);
            }

            return true;
        }

        if ((bool) session('is_division_head') || $accessRights === 'dh') {
            $query->where('u.division', Auth::user()->division);

            if (!is_null($requestedSection)) {
                $query->where('u.section', $requestedSection);
            }

            return true;
        }

        if ((bool) session('is_section_head')) {
            $query->where('u.section', Auth::user()->section);
            return true;
        }

        return false;
    }

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
                ]
            );
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
                ->where('employees.employee_no', '=', (int) $request->employee_no)
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
            $isOnline = $request->input('is_online') ?? 0;
            DB::table('dtr_logs')->insert([
                'userid' => $request->input('userid'),
                'status' => $request->input('status'),
                'type' => 1, // 1 for mobile DTR app
                'device' => $request->input('device') ?? 'Unknown',
                'is_online' => $isOnline,
                'photo_path' => $filePath,
                'created_at' => ($isOnline == 1) ? Carbon::now() : $eventTime,
            ]);

            return response()->json(['message' => 'Successfully Timed ' . $request->input('status')]);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function storeManual(Request $request)
    {
        if (!$this->canManageRawTimelog()) {
            return response()->json(['error' => 'Permission denied'], 403);
        }

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
        if (!$this->canManageRawTimelog()) {
            return response()->json(['error' => 'Permission denied'], 403);
        }

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

            if (!$this->canViewEmployeeDtrForUser((int) $userid)) {
                return response()->json(['error' => 'Permission denied'], 403);
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
            $userIds = $this->normalizePrintableUserIds($request);

            foreach ($userIds as $userId) {
                if (!$this->canViewEmployeeDtrForUser($userId)) {
                    return response()->json(['error' => 'Permission denied'], 403);
                }
            }

            $month = (int) $request->month;
            $year = (int) $request->year;
            $cutoff = $request->cutoff ?? 'full';
            $fromDateInput = $request->input('from');
            $toDateInput = $request->input('to');

            $reg_days_am = $request->reg_days_am;
            $reg_days_pm = $request->reg_days_pm;
            $sat_days_am = $request->sat_days_am;
            $sat_days_pm = $request->sat_days_pm;
            $enableAutomation = strtolower($request->input('enable_automation', 'yes'));
            $skipAllAutomation = ($enableAutomation === 'no');
            $showDayNames = strtolower($request->input('show_day_names', 'yes')) !== 'no';
            $showInChargeName = strtolower($request->input('show_in_charge_name', 'yes')) !== 'no';

            if (!empty($fromDateInput) && !empty($toDateInput)) {
                $fromDate = Carbon::parse($fromDateInput)->startOfDay();
                $toDate = Carbon::parse($toDateInput)->startOfDay();

                if ($toDate->lt($fromDate)) {
                    return response()->json(['error' => 'The end date must be on or after the start date.'], 422);
                }

                if ($fromDate->month !== $toDate->month || $fromDate->year !== $toDate->year) {
                    return response()->json(['error' => 'Bulk DTR printing currently supports a single month only.'], 422);
                }

                $month = (int) $fromDate->month;
                $year = (int) $fromDate->year;
                $startDay = (int) $fromDate->day;
                $endDay = (int) $toDate->day;
            } else {
                if ($month < 1 || $month > 12 || $year < 2000) {
                    return response()->json(['error' => 'Invalid month or year.'], 422);
                }

                $startDay = 1;
                $endDay = Carbon::createFromDate($year, $month, 1)->daysInMonth;
                if ($cutoff === 'first') {
                    $endDay = 15;
                } elseif ($cutoff === 'second') {
                    $startDay = 16;
                }
            }

            $employees = DB::table('tdh_user.users')
                ->whereIn('id', $userIds)
                ->orderBy('lname')
                ->orderBy('fname')
                ->get();

            if ($employees->isEmpty()) {
                return response()->json(['error' => 'No employees found for printing.'], 422);
            }

            $dtrData = [];
            $formattedDate = Carbon::createFromDate($year, $month, 1)->format('F Y');

            // Get holidays
            $holidaysRaw = DB::table('calendar_holidays')
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->get();

            $holidays = [];
            foreach ($holidaysRaw as $h) {
                $holidays[Carbon::parse($h->date)->toDateString()] = [
                    'type' => $h->type,
                    'description' => $h->description
                ];
            }

            foreach ($employees as $employee) {
                $inChargedDivision = $this->checkIfDivisionHead($employee->id) ? 1 : $employee->division;
                $divisionHead = collect(self::getInCharged($inChargedDivision))->only(['fname', 'mname', 'lname', 'suffix', 'title']);
                $fname = $divisionHead['fname'] ?? '';
                $mname = !empty($divisionHead['mname']) ? strtoupper(substr($divisionHead['mname'], 0, 1)) . '.' : '';
                $lname = $divisionHead['lname'] ?? '';
                $suffix = !empty($divisionHead['suffix']) ? ' ' . strtoupper($divisionHead['suffix']) : '';
                $title = !empty($divisionHead['title']) ? ', ' . strtoupper($divisionHead['title']) : '';

                $in_charged = strtoupper(trim("$fname $mname $lname$suffix$title"));

                $monthStart = Carbon::createFromDate($year, $month, 1)->startOfDay();
                $monthEndPlusOne = Carbon::createFromDate($year, $month, 1)->endOfMonth()->addDay()->endOfDay();

                $logs = DB::table('dtr_logs')
                    ->whereBetween('created_at', [$monthStart, $monthEndPlusOne])
                    ->where('userid', '=', $employee->id)
                    ->orderBy('created_at', 'ASC')
                    ->get();

                // Leave data
                $leavesRaw = DB::table('hris.leave_applications as la')
                    ->join('hris.leave_date_applied as lda', 'la.id', '=', 'lda.leave_application_id')
                    ->join('hris.leave_types as lt', 'lda.leave_type_id', '=', 'lt.id')
                    ->where('la.user_id', $employee->id)
                    ->whereMonth('lda.date_applied', $month)
                    ->whereYear('lda.date_applied', $year)
                    ->where('la.status', 'Approved')
                    ->select(DB::raw('DATE(lda.date_applied) as date_applied'), 'lt.code', 'lda.hours')
                    ->get();

                $leaves = [];
                foreach ($leavesRaw as $l) {
                    $leaves[Carbon::parse($l->date_applied)->toDateString()] = [
                        'code' => $l->code,
                        'hours' => $l->hours
                    ];
                }

                // H.O
                // Coverage is the parent hospital_orders' start_date..end_date
                // range, not the single filed_date on the employee's filing -
                // filing is a one-time acknowledgement of the order, not a
                // per-day attestation.
                $monthEnd = Carbon::createFromDate($year, $month, 1)->endOfMonth();
                $hosRaw = DB::table('hris.hospital_order_employees as hoe')
                    ->join('hris.hospital_orders as ho', 'ho.id', '=', 'hoe.ho_id')
                    ->where('hoe.userid', $employee->id)
                    ->where('hoe.status', 'approved')
                    ->where('ho.start_date', '<=', $monthEnd->toDateString())
                    ->where('ho.end_date', '>=', $monthStart->toDateString())
                    ->select('ho.order_number', 'ho.start_date', 'ho.end_date')
                    ->distinct()
                    ->get();

                $hospitalOrders = [];
                foreach ($hosRaw as $ho) {
                    $rangeStart = Carbon::parse($ho->start_date)->max($monthStart);
                    $rangeEnd = Carbon::parse($ho->end_date)->min($monthEnd);
                    for ($d = $rangeStart->copy(); $d->lte($rangeEnd); $d->addDay()) {
                        $hospitalOrders[$d->toDateString()] = 'H.O #' . $ho->order_number;
                    }
                }

                // Overtime
                $otsRaw = DB::table('hris.overtime_requests as ot')
                    ->where('ot.userid', $employee->id)
                    ->where('ot.status', 'approved')
                    ->whereMonth('ot.ot_date', $month)
                    ->whereYear('ot.ot_date', $year)
                    ->select(DB::raw('DATE(ot.ot_date) as ot_date'), 'ot.time_from', 'ot.time_to')
                    ->get();

                $overtimeRequests = [];
                foreach ($otsRaw as $ot) {
                    $otDate = Carbon::parse($ot->ot_date)->toDateString();
                    $range = Carbon::parse($ot->time_from)->format('H:i') . ' - ' . Carbon::parse($ot->time_to)->format('H:i');
                    $label = 'OT: ' . $range;

                    if (!isset($overtimeRequests[$otDate])) {
                        $overtimeRequests[$otDate] = $label;
                    } elseif (strpos($overtimeRequests[$otDate], $label) === false) {
                        $overtimeRequests[$otDate] .= ' | ' . $label;
                    }
                }

                // Fetch schedules for the employee for the month
                $schedulesRaw = DB::table('hris.schedules as s')
                    ->join('hris.schedule_master_lists as sml', 's.schedule_id', '=', 'sml.id')
                    ->where('s.employee_id', $employee->id)
                    ->whereMonth('s.schedule_date', $month)
                    ->whereYear('s.schedule_date', $year)
                    ->select(
                        's.schedule_date',
                        'sml.time_in',
                        'sml.time_in_latest',
                        'sml.time_out',
                        'sml.time_out_latest',
                        'sml.break_out',
                        'sml.break_in',
                        'sml.type',
                        'sml.code',
                        'sml.label'
                    )
                    ->get();

                $schedules = [];
                foreach ($schedulesRaw as $s) {
                    $schedules[$s->schedule_date] = $s;
                }

                // Approved pass slips: waive late/undertime for the day.
                // Official type still keeps the OB marker display behavior.
                $approvedPassSlipsRaw = DB::table('hris.pass_slips')
                    ->where('user_id', $employee->id)
                    ->where('status', 'approved')
                    ->whereMonth('slip_date', $month)
                    ->whereYear('slip_date', $year)
                    ->select(DB::raw('DATE(slip_date) as slip_date'), 'type')
                    ->get();

                $approvedPassSlips = [];
                $officialPassSlips = [];
                foreach ($approvedPassSlipsRaw as $ps) {
                    $approvedPassSlips[$ps->slip_date] = true;
                    if (strtoupper((string) $ps->type) === 'OFFICIAL') {
                        $officialPassSlips[$ps->slip_date] = true;
                    }
                }

                // Build daily DTR
                $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
                $lateCount = 0;
                $undertimeCount = 0;
                $lateTotalMinutes = 0;
                $undertimeTotalMinutes = 0;

                $days = collect(range(1, $daysInMonth))->map(function ($day) use ($employee, $month, $year, $startDay, $endDay, $logs, $holidays, $leaves, $hospitalOrders, $overtimeRequests, $schedules, $approvedPassSlips, $officialPassSlips, $skipAllAutomation, &$lateCount, &$undertimeCount, &$lateTotalMinutes, &$undertimeTotalMinutes) {
                    $formatMinutes = function ($minutes) {
                        $minutes = (int) $minutes;
                        if ($minutes <= 0)
                            return '0m';

                        $hours = intdiv($minutes, 60);
                        $mins = $minutes % 60;

                        if ($hours > 0 && $mins > 0)
                            return $hours . 'h ' . $mins . 'm';
                        if ($hours > 0)
                            return $hours . 'h';
                        return $mins . 'm';
                    };

                    $getScheduledBreakMinutes = function ($schedule, string $scheduleDate) {
                        if (
                            !$schedule ||
                            empty($schedule->break_out) ||
                            empty($schedule->break_in)
                        ) {
                            return 0;
                        }

                        $scheduleIn = Carbon::parse($scheduleDate . ' ' . $schedule->time_in);
                        $scheduleOut = Carbon::parse($scheduleDate . ' ' . $schedule->time_out);
                        if ($scheduleOut->lte($scheduleIn)) {
                            $scheduleOut->addDay();
                        }

                        $breakOut = Carbon::parse($scheduleDate . ' ' . $schedule->break_out);
                        $breakIn = Carbon::parse($scheduleDate . ' ' . $schedule->break_in);

                        if ($breakOut->lt($scheduleIn)) {
                            $breakOut->addDay();
                        }
                        if ($breakIn->lte($breakOut)) {
                            $breakIn->addDay();
                        }

                        $effectiveBreakStart = $breakOut->copy()->max($scheduleIn);
                        $effectiveBreakEnd = $breakIn->copy()->min($scheduleOut);

                        if ($effectiveBreakEnd->lte($effectiveBreakStart)) {
                            return 0;
                        }

                        return $effectiveBreakStart->diffInMinutes($effectiveBreakEnd);
                    };

                    $currentDay = Carbon::createFromDate($year, $month, $day)->toDateString();
                    $dayName = Carbon::createFromDate($year, $month, $day)->format('l');
                    $isHoliday = array_key_exists($currentDay, $holidays);
                    $hasLeave = array_key_exists($currentDay, $leaves);
                    $hasHospitalOrder = !empty($hospitalOrders[$currentDay]);
                    $hasApprovedOvertime = !empty($overtimeRequests[$currentDay]);
                    $hasApprovedPassSlip = !empty($approvedPassSlips[$currentDay]);
                    $hasOfficialPassSlip = !empty($officialPassSlips[$currentDay]);
                    $hasExcusedRecord = $hasLeave || $hasHospitalOrder || $hasApprovedPassSlip;
                    // Holidays should still compute late/undertime when there are actual timelogs.
                    // Only approved leave, hospital order, and pass slips waive the computation.
                    $hasComputationExemption = $hasExcusedRecord;

                    $data = [
                        'day' => $day,
                        'day_name' => $dayName,
                        'isInsideCutoff' => ($day >= $startDay && $day <= $endDay),
                        'isEmpty' => true,
                        'late_minutes' => 0,
                        'day_marker' => '',
                        'off_label' => '',
                        'suppress_off_label' => false,
                        'planned_schedule' => '',
                        'rendered_minutes' => 0,

                        'date_timein_am' => '',
                        'date_timeout_am' => '',
                        'date_timein_pm' => '',
                        'date_timeout_pm' => '',
                        'undertime_hours' => '',
                        'undertime_minutes' => '',
                    ];

                    // Populate planned schedule
                    $sched = $schedules[$currentDay] ?? null;
                    $isOffSchedule = false;
                    if ($sched) {
                        $scheduleCode = trim((string) $sched->code);
                        $scheduleLabel = trim((string) ($sched->label ?: $sched->code));
                        if ($scheduleCode !== '' && strtoupper($scheduleCode) === 'OFF') {
                            $isOffSchedule = true;
                            $data['off_label'] = $scheduleLabel !== '' ? $scheduleLabel : $scheduleCode;
                        }

                        $formatTime = function ($time, $showAmpm = true) {
                            $c = Carbon::parse($time);
                            $format = ($c->format('i') === '00') ? 'g' : 'g:i';
                            if ($showAmpm)
                                $format .= 'A';
                            return $c->format($format);
                        };

                        if (!$isOffSchedule) {
                            $in = $formatTime($sched->time_in, $sched->time_in == $sched->time_in_latest);
                            if ($sched->time_in != $sched->time_in_latest) {
                                $in .= '-' . $formatTime($sched->time_in_latest, true);
                            }

                            $out = $formatTime($sched->time_out, $sched->time_out == $sched->time_out_latest);
                            if ($sched->time_out != $sched->time_out_latest) {
                                $out .= '-' . $formatTime($sched->time_out_latest, true);
                            }

                            $data['planned_schedule'] = $in . "\n" . $out;

                            $scheduledBreakMinutes = $getScheduledBreakMinutes($sched, $currentDay);
                            $scheduledSpanMinutes = Carbon::parse($currentDay . ' ' . $sched->time_in)
                                ->diffInMinutes(
                                    Carbon::parse($currentDay . ' ' . $sched->time_out)->lte(Carbon::parse($currentDay . ' ' . $sched->time_in))
                                    ? Carbon::parse($currentDay . ' ' . $sched->time_out)->addDay()
                                    : Carbon::parse($currentDay . ' ' . $sched->time_out)
                                );
                            $data['rendered_minutes'] = max($scheduledSpanMinutes - $scheduledBreakMinutes, 0);
                        }
                    }

                    if (!$data['isInsideCutoff'])
                        return $data;

                    // Get logs of the day
                    $dayLogs = $logs->filter(
                        fn($log) =>
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
                            $timeString = $time->format('H:i:s');

                            if ($log->status === 'IN') {
                                // AM IN: Before 12NN (< 12:00:00)
                                // PM IN: After 12NN (>= 12:00:00)
                                if ($timeString < '12:00:00') {
                                    $amIns[] = $t;
                                } else {
                                    $pmIns[] = $t;
                                }
                            } else {
                                // AM OUT: Before 1PM (< 13:00:00)
                                // PM OUT: 1PM onwards (>= 13:00:00)
                                if ($timeString < '13:00:00') {
                                    $amOuts[] = $t;
                                } else {
                                    $pmOuts[] = $t;
                                }
                            }
                        }

                        // AM
                        if (!empty($amIns))
                            $data['date_timein_am'] = min($amIns);
                        if (!empty($amOuts))
                            $data['date_timeout_am'] = max($amOuts);

                        // PM
                        if (!empty($pmIns))
                            $data['date_timein_pm'] = min($pmIns);
                        if (!empty($pmOuts))
                            $data['date_timeout_pm'] = max($pmOuts);

                        if (!empty($amIns) || !empty($pmIns) || !empty($amOuts) || !empty($pmOuts)) {
                            $data['isEmpty'] = false;
                        }

                        $dayResult = app(\App\Services\DtrDayComputationService::class)->computeRange(
                            $employee->id,
                            $currentDay,
                            $currentDay,
                            collect($schedules),
                            $logs,
                            [
                                'holidays' => $holidays,
                                'leaves' => $leaves,
                                'hospitalOrders' => $hospitalOrders,
                                'passSlips' => $approvedPassSlips,
                            ],
                            $skipAllAutomation
                        )[$currentDay];

                        $lateMinutes = $dayResult['late_minutes'];
                        $undertimeMinutes = $dayResult['undertime_minutes'];

                        if ($lateMinutes > 0) $lateCount++;
                        if ($undertimeMinutes > 0) $undertimeCount++;
                        $lateTotalMinutes += $lateMinutes;
                        $undertimeTotalMinutes += $undertimeMinutes;
                        $data['late_minutes'] = $lateMinutes;

                        $totalMinutes = $lateMinutes + $undertimeMinutes;
                        if ($totalMinutes > 0) {
                            $data['undertime_hours'] = floor($totalMinutes / 60) ?: '';
                            $data['undertime_minutes'] = ($totalMinutes % 60) ?: '';
                        }
                    }

                    // Keep timelog fields as pure times; render OB/OT as separate marker in the PDF.
                    // Holiday tuning:
                    // - Worked holiday on a full-day schedule: hide HOLIDAY.
                    // - Worked holiday on a half-day schedule: keep HOLIDAY unless there is late/undertime.
                    // Priority for worked days: HOLIDAY (half-day only, no penalty), then OB, then OT.
                    if (!$data['isEmpty']) {
                        $hasLateOrUndertime =
                            (int) ($data['late_minutes'] ?? 0) > 0 ||
                            (int) ($data['undertime_hours'] ?: 0) > 0 ||
                            (int) ($data['undertime_minutes'] ?: 0) > 0;
                        $isHalfDaySchedule =
                            !$isOffSchedule &&
                            (int) ($data['rendered_minutes'] ?? 0) > 0 &&
                            (int) ($data['rendered_minutes'] ?? 0) <= 240;

                        if ($isHoliday) {
                            if ($isHalfDaySchedule && !$hasLateOrUndertime) {
                                $holiday = $holidays[$currentDay];
                                $data['day_marker'] = $holiday['type'] != 'Regular' ? strtoupper($holiday['description']) : 'HOLIDAY';
                            } else {
                                $data['day_marker'] = '';
                            }
                        } elseif ($hasOfficialPassSlip) {
                            // OB reflects regardless of whether the employee also has timelogs that day.
                            $data['day_marker'] = 'OB';
                        } elseif (!$hasExcusedRecord && $hasApprovedOvertime) {
                            $lateMarker = '';
                            if (!empty($data['late_minutes']) && (int) $data['late_minutes'] > 0) {
                                $lateMarker = 'L: ' . $formatMinutes((int) $data['late_minutes']) . ' ';
                            }
                            $data['day_marker'] = $lateMarker . $overtimeRequests[$currentDay];
                        } else {
                            $data['day_marker'] = '';
                        }
                    }

                    // LEAVE / HOLIDAY / H.O / OB / OT
                    if ($data['isEmpty']) {
                        if ($hasLeave) {
                            $code = $leaves[$currentDay]['code'];
                        } elseif ($hasHospitalOrder) {
                            $code = $hospitalOrders[$currentDay];
                        } elseif ($hasOfficialPassSlip) {
                            $code = 'OB';
                        } elseif (!empty($overtimeRequests[$currentDay])) {
                            $code = $overtimeRequests[$currentDay];
                        } elseif ($isHoliday) {
                            $holiday = $holidays[$currentDay];
                            $code = $holiday['type'] != 'Regular' ? strtoupper($holiday['description']) : 'HOLIDAY';
                        } else {
                            $code = null;
                        }

                        if ($code !== null) {
                            $data['day_marker'] = $code;
                        }
                    }

                    return $data;
                });

                $dtrData[] = [
                    'name' => strtoupper($employee->fname . ' ' . ($employee->mname ? strtoupper(substr($employee->mname, 0, 1)) . '.' : '') . ' ' . $employee->lname . ($employee->suffix ? ' ' . strtoupper($employee->suffix) : '')),
                    'in_charged' => $in_charged,
                    'date' => $formattedDate,
                    'dtrs' => $days,
                    'late_count' => $lateCount,
                    'undertime_count' => $undertimeCount,
                    'late_total_minutes' => $lateTotalMinutes,
                    'undertime_total_minutes' => $undertimeTotalMinutes,
                    'start_day' => $startDay,
                    'end_day' => $endDay,
                    'reg_days_am' => $reg_days_am,
                    'reg_days_pm' => $reg_days_pm,
                    'sat_days_am' => $sat_days_am,
                    'sat_days_pm' => $sat_days_pm,
                    'division' => $employee->division,
                    'enable_automation' => $enableAutomation,
                    'show_day_names' => $showDayNames,
                    'show_in_charge_name' => $showInChargeName,
                ];
            }


            $pdf = SnappyPdf::loadView('pdf.old_dtr', ['employees' => $dtrData])
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

    public function checkIfDivisionHead($id)
    {
        return DB::table('tdh_user.division')
            ->where('head', '=', $id)
            ->exists();
    }

    public function getDivisionByUser($id)
    {
        return DB::table('tdh_user.users')
            ->where('id', '=', $id)
            ->value('division');
    }

    public
        function getEmployeeDtrV2(
        Request $request
    ) {
        try {
            $search_division = $request->search_division;
            $search_section = $request->search_section;
            $search_fname = $request->search_fname;
            $search_lname = $request->search_lname;

            $employee = DB::table('tdh_user.users as u')
                ->join('hris.employees as e', 'u.id', '=', 'e.user_id')
                ->join('tdh_user.designation as d1', 'd1.id', '=', 'u.designation')
                ->leftJoin('tdh_user.division as dv', 'dv.id', '=', 'u.division')
                ->leftJoin('tdh_user.section as sc', 'sc.id', '=', 'u.section')
                ->select(
                    'u.id',
                    'u.fname',
                    'u.mname',
                    'u.lname',
                    'd1.description as designation',
                    'u.picture',
                    'u.section',
                    'u.division',
                    'dv.description as division_name',
                    'sc.description as section_name'
                )
                ->where(function ($query) {
                    $query->where('e.employee_type', '!=', 'Resigned')
                        ->orWhereNull('e.employee_type');
                });

            // Default to logged-in user's section if search_section = 0
            if ($search_section == 0) {
                // Fetch sections where the current user is the head
                $managed_sections = DB::table('tdh_user.section')
                    ->where('head', Auth::id())
                    ->pluck('id')
                    ->toArray();

                if (!empty($managed_sections)) {
                    // If the user is a head of one or more sections, fetch all employees in those sections
                    $employee->whereIn('u.section', $managed_sections);
                } else {
                    // Fallback to the current user's own section if they are not a head
                    $employee->where('u.section', '=', Auth::user()->section);
                }
            } else {
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

    public function getEmployeeDtr(Request $request)
    {
        try {
            if (!$this->canAccessEmployeeDtrModule()) {
                return response()->json(['error' => 'Permission denied'], 403);
            }

            $search_division = $request->search_division;
            $search_section = $request->search_section;
            $search_fname = $request->search_fname;
            $search_lname = $request->search_lname;
            $perPage = (int) $request->input('per_page', 12);
            if ($perPage < 1) {
                $perPage = 12;
            }
            if ($perPage > 60) {
                $perPage = 60;
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

            $searchDivision = (is_null($search_division) || $search_division === '' || $search_division === '0' || $search_division === 'null')
                ? null
                : (int) $search_division;
            $searchSection = (is_null($search_section) || $search_section === '' || $search_section === '0' || $search_section === 'null')
                ? null
                : (int) $search_section;

            if (!$this->applyEmployeeDtrScope($employee, $searchDivision, $searchSection)) {
                return response()->json(['error' => 'Permission denied'], 403);
            }

            if (!empty($search_fname)) {
                $employee->where('u.fname', 'LIKE', "%$search_fname%");
            }

            if (!empty($search_lname)) {
                $employee->where('u.lname', 'LIKE', "%$search_lname%");
            }

            $result = $employee->orderBy('u.lname')->paginate($perPage)->appends($request->all());

            return response()->json($result);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }


    public
        function getDivisionList(
    ) {
        try {
            $list = DB::table('tdh_user.division')
                ->get(['id', 'description']);

            return response()->json($list);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public
        function getSectionList(
        Request $request
    ) {
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
        function getLatestLog(
        Request $request
    ) {
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
        function getRecentLogs(
        Request $request
    ) {
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
        function getInOutLogs(
        Request $request
    ): JsonResponse {
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
