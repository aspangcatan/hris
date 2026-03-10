<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Barryvdh\DomPDF\Facade\Pdf;

class ScheduleController extends Controller
{
    private function getCurrentAccessRights(): string
    {
        return strtolower((string) session('access_rights'));
    }

    private function getManagedSectionIdsForCurrentUser(bool $includeOwnSectionFallback = true): array
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

    private function canManageEmployee(int $employeeId): bool
    {
        if ($this->getCurrentAccessRights() === 'admin') {
            return true;
        }

        if ($this->getCurrentAccessRights() !== 'schedule') {
            return false;
        }

        $managedSections = $this->getManagedSectionIdsForCurrentUser();
        if (empty($managedSections)) {
            return false;
        }

        return DB::table('tdh_user.users')
            ->where('id', $employeeId)
            ->whereIn('section', $managedSections)
            ->exists();
    }

    private function formatName($user): string
    {
        if (!$user) {
            return '';
        }

        $mInitial = '';
        if (!empty($user->mname)) {
            $mInitial = ' ' . strtoupper(substr(trim($user->mname), 0, 1)) . '.';
        }
        $suffix = !empty($user->suffix) ? ' ' . trim($user->suffix) : '';

        return trim($user->fname . $mInitial . ' ' . $user->lname . $suffix);
    }

    private function isLockedScheduleDate(string $date): bool
    {
        return false;        

        $scheduleDate = Carbon::parse($date)->startOfDay();
        $firstDayOfCurrentMonth = Carbon::now()->startOfMonth();

        // Lock all dates before the current month (includes last month).
        return $scheduleDate->lt($firstDayOfCurrentMonth);
    }

    //
    public function getSchedules(Request $request)
    {
        try {
            if (!Auth::check()) {
                return redirect()->route('login')->with('error', 'Unauthorized access');
            }

            $accessRights = $this->getCurrentAccessRights();

            // Get the selected month & year (default: current)
            $month = $request->input('month', date('m'));
            $year = $request->input('year', date('Y'));
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

            $employees = DB::table('tdh_user.users as u')
                ->join('tdh_user.designation as d', 'u.designation', '=', 'd.id')
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
                ->orderBy('u.lname', 'asc')
                ->orderBy('u.fname', 'asc');

            $requestedDivision = $request->input('division');
            $requestedSection = $request->input('section');
            $requestedDivision = (is_null($requestedDivision) || $requestedDivision === '' || $requestedDivision === '0' || $requestedDivision === 'null') ? null : (int) $requestedDivision;
            $requestedSection = (is_null($requestedSection) || $requestedSection === '' || $requestedSection === '0' || $requestedSection === 'null') ? null : (int) $requestedSection;

            if ($accessRights === 'admin') {
                if (!is_null($requestedSection)) {
                    $employees->where('u.section', $requestedSection);
                } elseif (!is_null($requestedDivision)) {
                    $employees->where('u.division', $requestedDivision);
                } else {
                    $employees->where('u.section', Auth::user()->section);
                }
            } elseif ($accessRights === 'schedule') {
                $managedSections = $this->getManagedSectionIdsForCurrentUser();
                if (empty($managedSections)) {
                    return response()->json(['schedules' => [], 'days' => $daysInMonth]);
                }

                if (!is_null($requestedSection)) {
                    if (!in_array($requestedSection, $managedSections, true)) {
                        return response()->json(['schedules' => [], 'days' => $daysInMonth]);
                    }
                    $employees->where('u.section', $requestedSection);
                } else {
                    $employees->whereIn('u.section', $managedSections);
                    if (!is_null($requestedDivision)) {
                        $employees->where('u.division', $requestedDivision);
                    }
                }
            } else {
                $employees->where('u.section', Auth::user()->section);
            }

            $employees = $employees->get();

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

                // Initialize all days with "RD"
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

    public function printSchedule(Request $request)
    {
        try {
            if (!Auth::check()) {
                return redirect()->route('login')->with('error', 'Unauthorized access');
            }

            $accessRights = $this->getCurrentAccessRights();
            $month = (int) $request->input('month', date('m'));
            $year = (int) $request->input('year', date('Y'));

            if ($month < 1 || $month > 12 || $year < 2000) {
                return response()->json(['message' => 'Invalid month or year.'], 422);
            }

            $employeesQuery = DB::table('tdh_user.users as u')
                ->join('tdh_user.designation as d', 'u.designation', '=', 'd.id')
                ->selectRaw("
                    u.id as employee_id,
                    u.section as section_id,
                    u.division as division_id,
                    CONCAT(
                        u.fname,
                        CASE WHEN u.mname IS NOT NULL AND u.mname != '' THEN CONCAT(' ', u.mname) ELSE '' END,
                        ' ', u.lname,
                        CASE WHEN u.suffix IS NOT NULL AND u.suffix != '' THEN CONCAT(' ', u.suffix) ELSE '' END
                    ) AS name,
                    COALESCE(d.description, 'No Designation') AS designation
                ")
                ->orderBy('u.lname', 'asc')
                ->orderBy('u.fname', 'asc');

            $requestedDivision = $request->input('division');
            $requestedSection = $request->input('section');
            $requestedDivision = (is_null($requestedDivision) || $requestedDivision === '' || $requestedDivision === '0' || $requestedDivision === 'null') ? null : (int) $requestedDivision;
            $requestedSection = (is_null($requestedSection) || $requestedSection === '' || $requestedSection === '0' || $requestedSection === 'null') ? null : (int) $requestedSection;

            if ($accessRights === 'admin') {
                if (!is_null($requestedSection)) {
                    $employeesQuery->where('u.section', $requestedSection);
                } elseif (!is_null($requestedDivision)) {
                    $employeesQuery->where('u.division', $requestedDivision);
                } else {
                    $employeesQuery->where('u.section', Auth::user()->section);
                }
            } elseif ($accessRights === 'schedule') {
                $managedSections = $this->getManagedSectionIdsForCurrentUser();
                if (empty($managedSections)) {
                    return response()->json(['message' => 'No managed sections assigned.'], 422);
                }

                if (!is_null($requestedSection)) {
                    if (!in_array($requestedSection, $managedSections, true)) {
                        return response()->json(['message' => 'You are not allowed to print this section.'], 403);
                    }
                    $employeesQuery->where('u.section', $requestedSection);
                } else {
                    $employeesQuery->whereIn('u.section', $managedSections);
                    if (!is_null($requestedDivision)) {
                        $employeesQuery->where('u.division', $requestedDivision);
                    }
                }
            } else {
                $employeesQuery->where('u.section', Auth::user()->section);
            }

            $employees = $employeesQuery->get();

            $schedules = DB::table('hris.schedules as s')
                ->join('hris.schedule_master_lists as sml', 's.schedule_id', '=', 'sml.id')
                ->whereYear('s.schedule_date', $year)
                ->whereMonth('s.schedule_date', $month)
                ->select('s.employee_id', 's.schedule_date', 'sml.code', 'sml.label')
                ->get();

            $scheduleMap = [];
            foreach ($schedules as $sched) {
                $dateKey = Carbon::parse($sched->schedule_date)->toDateString();
                $scheduleMap[$sched->employee_id][$dateKey] = $sched->label ?: $sched->code;
            }

            $weekDates = $this->getFirstWeekDates($year, $month);
            $scheduleList = [];
            foreach ($employees as $employee) {
                $weekSchedule = [];
                foreach ($weekDates as $date) {
                    $weekSchedule[] = $scheduleMap[$employee->employee_id][$date] ?? 'OFF';
                }

                $scheduleList[] = [
                    'name' => $employee->name,
                    'schedule' => $weekSchedule,
                    'total_hours' => '40 HRS.',
                ];
            }

            $divisionLabel = '';
            $sectionLabel = '';
            $divisionId = null;
            if (!is_null($requestedSection)) {
                $sectionInfo = DB::table('tdh_user.section as s')
                    ->join('tdh_user.division as d', 's.division', '=', 'd.id')
                    ->where('s.id', $requestedSection)
                    ->select('s.description as section', 'd.description as division', 'd.id as division_id')
                    ->first();
                $divisionLabel = $sectionInfo->division ?? '';
                $sectionLabel = $sectionInfo->section ?? '';
                $divisionId = $sectionInfo->division_id ?? null;
            } elseif (!is_null($requestedDivision)) {
                $divisionInfo = DB::table('tdh_user.division')
                    ->where('id', $requestedDivision)
                    ->select('description')
                    ->first();
                $divisionLabel = $divisionInfo->description ?? '';
                $sectionLabel = 'ALL SECTIONS';
                $divisionId = $requestedDivision;
            } else {
                if ($accessRights === 'schedule') {
                    $divisionLabel = 'ASSIGNED DIVISIONS';
                    $sectionLabel = 'ALL ALLOWED SECTIONS';
                    $divisionId = Auth::user()->division ?? null;
                } else {
                    $sectionInfo = DB::table('tdh_user.section as s')
                        ->join('tdh_user.division as d', 's.division', '=', 'd.id')
                        ->where('s.id', Auth::user()->section)
                        ->select('s.description as section', 'd.description as division', 'd.id as division_id')
                        ->first();
                    $divisionLabel = $sectionInfo->division ?? '';
                    $sectionLabel = $sectionInfo->section ?? '';
                    $divisionId = $sectionInfo->division_id ?? (Auth::user()->division ?? null);
                }
            }

            $preparedUser = Auth::user();
            $preparedName = $this->formatName($preparedUser);
            $preparedTitle = session('designation') ?? '';

            $approverName = '';
            $approverTitle = '';
            $approvedDesignation = '';
            if (!is_null($divisionId)) {
                $divisionHead = DB::table('tdh_user.division as d')
                    ->join('tdh_user.users as u', 'd.head', '=', 'u.id')
                    ->leftJoin('tdh_user.designation as de', 'u.designation', '=', 'de.id')
                    ->where('d.id', $divisionId)
                    ->select('u.fname', 'u.mname', 'u.lname', 'u.suffix','u.title', 'de.description as designation')
                    ->first();

                $approverName = $this->formatName($divisionHead);
                $approverTitle = $divisionHead->title ?? '';
                $approvedDesignation = $divisionHead->designation;
            }

            $approverDisplay = trim($approverName . ($approverTitle ? ', ' . $approverTitle : ''));
        
            $pdf = Pdf::loadView('pdf.schedule', [
                'month_label' => strtoupper(Carbon::createFromDate($year, $month, 1)->format('F Y')),
                'division' => $divisionLabel,
                'section' => $sectionLabel,
                'employees' => array_chunk($scheduleList, 9),
                'prepared_by' => $preparedName,
                'prepared_title' => $preparedTitle,
                'approved_by' => $approverDisplay,
                'approved_title' => $approvedDesignation,
            ])->setPaper('a4', 'landscape');

            return $pdf->stream(sprintf('schedule_%s_%02d.pdf', $year, $month), ['Attachment' => false]);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    private function getFirstWeekDates(int $year, int $month): array
    {
        $firstOfMonth = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $monday = $firstOfMonth->copy();

        while ($monday->format('N') !== '1') {
            $monday->addDay();
        }

        $dates = [];
        for ($i = 0; $i < 7; $i++) {
            $dates[] = $monday->copy()->addDays($i)->toDateString();
        }

        return $dates;
    }



    public function getScheduleMasterList()
    {
        try {
            return response()->json(
                DB::table('hris.schedule_master_lists')
                    ->orderBy('label')
                    ->get()
            );
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function saveScheduleMasterList(Request $request)
    {
        try {
            if ($this->getCurrentAccessRights() !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $entryId = (int) $request->input('id', 0);
            $validated = $request->validate([
                'label' => 'required|string|max:50',
                'code' => [
                    'required',
                    'string',
                    'max:10',
                    Rule::unique('hris.schedule_master_lists', 'code')->ignore($entryId)
                ],
                'type' => ['required', Rule::in(['FIXED', 'GLIDING'])],
                'time_in' => 'required|date_format:H:i',
                'time_in_latest' => 'required|date_format:H:i',
                'time_out' => 'required|date_format:H:i',
                'time_out_latest' => 'required|date_format:H:i',
            ]);

            $payload = [
                'label' => trim($validated['label']),
                'code' => strtoupper(trim($validated['code'])),
                'type' => strtoupper($validated['type']),
                'time_in' => $validated['time_in'],
                'time_in_latest' => $validated['time_in_latest'],
                'time_out' => $validated['time_out'],
                'time_out_latest' => $validated['time_out_latest'],
                'updated_at' => now(),
            ];

            if ($entryId > 0) {
                DB::table('hris.schedule_master_lists')
                    ->where('id', $entryId)
                    ->update($payload);

                return response()->json(['message' => 'Schedule master list updated successfully.']);
            }

            $payload['created_at'] = now();
            DB::table('hris.schedule_master_lists')->insert($payload);

            return response()->json(['message' => 'Schedule master list created successfully.']);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return response()->json([
                'message' => $exception->validator->errors()->first(),
                'errors' => $exception->validator->errors(),
            ], 422);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function deleteScheduleMasterList(int $id)
    {
        try {
            if ($this->getCurrentAccessRights() !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $isInUse = DB::table('hris.schedules')
                ->where('schedule_id', $id)
                ->exists();

            if ($isInUse) {
                return response()->json([
                    'message' => 'This schedule is already used in employee schedules and cannot be deleted.'
                ], 422);
            }

            DB::table('hris.schedule_master_lists')
                ->where('id', $id)
                ->delete();

            return response()->json(['message' => 'Schedule master list deleted successfully.']);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function setEmployeeSchedule(Request $request)
    {
        try {
            if (!$this->canManageEmployee((int) $request->employee_id)) {
                return response()->json(['message' => 'You are not allowed to manage this employee schedule.'], 403);
            }

            if ($this->isLockedScheduleDate($request->schedule_date)) {
                return response()->json([
                    'message' => 'Schedules from last month and older can no longer be edited.'
                ], 422);
            }

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

    public function saveAdvanceSchedule(Request $request)
    {
        $schedules = $request->input('schedules');

        foreach ($schedules as $sched) {
            if (!$this->canManageEmployee((int) ($sched['employee_id'] ?? 0))) {
                return response()->json(['message' => 'You are not allowed to manage one or more selected employees.'], 403);
            }
        }

        foreach ($schedules as $sched) {
            if ($this->isLockedScheduleDate($sched['schedule_date'])) {
                return response()->json([
                    'message' => 'Schedules from last month and older can no longer be edited.'
                ], 422);
            }
        }

        foreach ($schedules as $sched) {
            DB::table('hris.schedules')->updateOrInsert(
                [
                    'employee_id' => $sched['employee_id'],
                    'schedule_date' => $sched['schedule_date'],
                ],
                [
                    'schedule_id' => $sched['schedule_id'],
                    'updated_by' => Auth::id(),
                    'updated_at' => now(),
                    'created_at' => now()
                ]
            );
        }

        return response()->json(['message' => 'Schedules saved successfully']);
    }

    public function getManagedSections()
    {
        try {
            if (!Auth::check()) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $accessRights = $this->getCurrentAccessRights();

            $sectionQuery = DB::table('tdh_user.section as s')
                ->join('tdh_user.division as d', 's.division', '=', 'd.id')
                ->select(
                    's.id',
                    's.description',
                    's.division',
                    'd.description as division_description'
                )
                ->orderBy('d.description')
                ->orderBy('s.description');

            if ($accessRights === 'schedule') {
                $managedSections = $this->getManagedSectionIdsForCurrentUser();
                if (empty($managedSections)) {
                    return response()->json(['sections' => []]);
                }
                $sectionQuery->whereIn('s.id', $managedSections);
            } elseif ($accessRights !== 'admin') {
                $sectionQuery->where('s.id', Auth::user()->section);
            }

            return response()->json(['sections' => $sectionQuery->get()]);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function getScheduleManagerAssignments()
    {
        try {
            if ($this->getCurrentAccessRights() !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $sections = DB::table('tdh_user.section as s')
                ->join('tdh_user.division as d', 's.division', '=', 'd.id')
                ->select(
                    's.id',
                    's.description',
                    's.division',
                    'd.description as division_description'
                )
                ->orderBy('d.description')
                ->orderBy('s.description')
                ->get();

            $managers = DB::table('tdh_user.user_priv as up')
                ->join('tdh_user.users as u', 'up.user_id', '=', 'u.id')
                ->leftJoin('tdh_user.designation as de', 'u.designation', '=', 'de.id')
                ->where('up.syscode', 'hris')
                ->where('up.level', 'schedule')
                ->selectRaw("
                    u.id,
                    CONCAT(
                        u.fname,
                        CASE WHEN u.mname IS NOT NULL AND u.mname != '' THEN CONCAT(' ', u.mname) ELSE '' END,
                        ' ', u.lname,
                        CASE WHEN u.suffix IS NOT NULL AND u.suffix != '' THEN CONCAT(' ', u.suffix) ELSE '' END
                    ) AS name,
                    COALESCE(de.description, 'No Designation') AS designation
                ")
                ->orderBy('u.lname')
                ->orderBy('u.fname')
                ->get();

            $assigned = DB::table('hris.schedule_user_sections')
                ->select('user_id', 'section_id')
                ->get()
                ->groupBy('user_id')
                ->map(function ($rows) {
                    return $rows->pluck('section_id')->map(fn($id) => (int) $id)->values();
                });

            $managerList = $managers->map(function ($manager) use ($assigned) {
                $manager->assigned_section_ids = $assigned->get($manager->id, collect([]));
                return $manager;
            });

            return response()->json([
                'managers' => $managerList,
                'sections' => $sections
            ]);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function saveScheduleManagerSections(Request $request)
    {
        try {
            if ($this->getCurrentAccessRights() !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $userId = (int) $request->input('manager_id');
            $sectionIds = collect($request->input('section_ids', []))
                ->map(fn($id) => (int) $id)
                ->filter(fn($id) => $id > 0)
                ->unique()
                ->values();

            $isScheduleManager = DB::table('tdh_user.user_priv')
                ->where('user_id', $userId)
                ->where('syscode', 'hris')
                ->where('level', 'schedule')
                ->exists();

            if (!$isScheduleManager) {
                return response()->json(['message' => 'Selected user is not a schedule manager.'], 422);
            }

            $validSectionIds = DB::table('tdh_user.section')
                ->whereIn('id', $sectionIds->all())
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->values();

            DB::beginTransaction();

            DB::table('hris.schedule_user_sections')
                ->where('user_id', $userId)
                ->delete();

            if ($validSectionIds->isNotEmpty()) {
                $now = now();
                $rows = $validSectionIds->map(function ($sectionId) use ($userId, $now) {
                    return [
                        'user_id' => $userId,
                        'section_id' => $sectionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->all();

                DB::table('hris.schedule_user_sections')->insert($rows);
            }

            DB::commit();

            return response()->json(['message' => 'Managed sections updated successfully.']);
        } catch (\Exception $exception) {
            DB::rollBack();
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }
}
