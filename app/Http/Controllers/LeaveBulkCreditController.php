<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveBulkCreditController extends Controller
{
    public function getDivisions()
    {
        try {
            $divisions = DB::table('tdh_user.division')
                ->orderBy('description')
                ->get(['id', 'description']);
            return response()->json($divisions);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function getSections(Request $request)
    {
        try {
            $query = DB::table('tdh_user.section')->orderBy('description');
            if ($request->filled('division_id')) {
                $query->where('division', $request->division_id);
            }
            return response()->json($query->get(['id', 'description']));
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function getEmployees(Request $request)
    {
        try {
            $query = DB::table('hris.employees as e')
                ->join('tdh_user.users as u', 'e.user_id', '=', 'u.id')
                ->leftJoin('tdh_user.designation as des', 'u.designation', '=', 'des.id')
                ->leftJoin('tdh_user.division as div', 'u.division', '=', 'div.id')
                ->leftJoin('tdh_user.section as sec', 'u.section', '=', 'sec.id');

            if ($request->filled('division_id')) {
                $query->where('u.division', $request->division_id);
            }
            if ($request->filled('section_id')) {
                $query->where('u.section', $request->section_id);
            }
            if ($request->filled('employee_type')) {
                $query->where('e.employee_type', $request->employee_type);
            }
            if ($request->filled('hired_from')) {
                $query->whereDate('e.date_hired', '>=', $request->hired_from);
            }
            if ($request->filled('hired_to')) {
                $query->whereDate('e.date_hired', '<=', $request->hired_to);
            }

            $employees = $query->orderBy('u.lname')->orderBy('u.fname')
                ->get([
                    'e.user_id',
                    'u.fname as firstname',
                    'u.lname as lastname',
                    'des.description as designation_name',
                    'div.description as division_name',
                    'sec.description as section_name',
                    'e.employee_type',
                    'e.date_hired',
                ]);

            return response()->json($employees);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function getAllLeaveTypes()
    {
        try {
            $types = DB::table('leave_types')
                ->where('status', 'active')
                ->orderBy('description')
                ->get(['id', 'description', 'code']);
            return response()->json($types);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function getEmployeeTypes()
    {
        try {
            $types = DB::table('hris.employees')
                ->whereNotNull('employee_type')
                ->where('employee_type', '!=', '')
                ->distinct()
                ->orderBy('employee_type')
                ->pluck('employee_type');
            return response()->json($types);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function getBatches(Request $request)
    {
        try {
            $perPage = 10;
            $page    = max(1, (int) $request->input('page', 1));

            $rows = DB::table('leave_credit_batches as b')
                ->join('tdh_user.users as u', 'u.id', '=', 'b.created_by')
                ->orderBy('b.created_at', 'desc')
                ->get([
                    'b.id',
                    'b.notes',
                    'b.created_at',
                    'u.fname as admin_fname',
                    'u.lname as admin_lname',
                ]);

            // Attach employee count per batch
            $counts = DB::table('leave_credit_batch_items')
                ->selectRaw('batch_id, COUNT(DISTINCT user_id) as emp_count')
                ->groupBy('batch_id')
                ->pluck('emp_count', 'batch_id');

            $total    = $rows->count();
            $lastPage = max(1, (int) ceil($total / $perPage));
            $items    = $rows->forPage($page, $perPage)->values()->map(function ($row) use ($counts) {
                return [
                    'id'         => $row->id,
                    'date'       => \Carbon\Carbon::parse($row->created_at)->format('m/d/Y h:i A'),
                    'admin'      => "{$row->admin_lname}, {$row->admin_fname}",
                    'notes'      => $row->notes ?? '—',
                    'emp_count'  => $counts[$row->id] ?? 0,
                    'print_url'  => route('bulk-leave-credits.print', $row->id),
                ];
            });

            return response()->json([
                'results'    => $items,
                'pagination' => [
                    'current_page'   => $page,
                    'last_page'      => $lastPage,
                    'per_page'       => $perPage,
                    'total'          => $total,
                    'from'           => $total > 0 ? ($page - 1) * $perPage + 1 : null,
                    'to'             => $total > 0 ? min($page * $perPage, $total) : null,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'user_ids'              => 'required|array|min:1',
            'user_ids.*'            => 'required|integer',
            'credits'               => 'required|array|min:1',
            'credits.*.leave_type_id' => 'required|integer',
            'credits.*.value'       => 'required|numeric|min:0.001',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        try {
            DB::transaction(function () use ($request) {
                $userIds    = $request->user_ids;
                $creditRows = $request->credits; // [{leave_type_id, value}, ...]

                // Resolve leave type codes once
                $leaveTypeIds = collect($creditRows)->pluck('leave_type_id')->unique()->toArray();
                $leaveTypes   = DB::table('leave_types')
                    ->whereIn('id', $leaveTypeIds)
                    ->get(['id', 'code'])
                    ->keyBy('id');

                // Resolve employee details once
                $employees = DB::table('hris.employees as e')
                    ->join('tdh_user.users as u', 'e.user_id', '=', 'u.id')
                    ->leftJoin('tdh_user.designation as des', 'u.designation', '=', 'des.id')
                    ->leftJoin('tdh_user.division as div', 'u.division', '=', 'div.id')
                    ->leftJoin('tdh_user.section as sec', 'u.section', '=', 'sec.id')
                    ->whereIn('e.user_id', $userIds)
                    ->get([
                        'e.user_id',
                        'u.fname as firstname',
                        'u.lname as lastname',
                        'des.description as designation_name',
                        'div.description as division_name',
                        'sec.description as section_name',
                        'e.employee_type',
                    ])
                    ->keyBy('user_id');

                // Create batch header
                $batchId = DB::table('leave_credit_batches')->insertGetId([
                    'created_by' => Auth::id(),
                    'notes'      => $request->notes ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $batchItems = [];

                foreach ($userIds as $userId) {
                    $emp = $employees[$userId] ?? null;
                    if (!$emp) continue;

                    foreach ($creditRows as $row) {
                        $leaveTypeId    = (int) $row['leave_type_id'];
                        $creditsToAdd   = round((float) $row['value'], 3);
                        $leaveType      = $leaveTypes[$leaveTypeId] ?? null;
                        if (!$leaveType) continue;

                        // Upsert leave_credits and get new balance
                        $existing = DB::table('leave_credits')
                            ->where('user_id', $userId)
                            ->where('leave_id', $leaveTypeId)
                            ->first(['based_credit', 'used_credit']);

                        if ($existing) {
                            DB::table('leave_credits')
                                ->where('user_id', $userId)
                                ->where('leave_id', $leaveTypeId)
                                ->increment('based_credit', $creditsToAdd);
                            $balanceAfter = round(
                                ($existing->based_credit + $creditsToAdd) - $existing->used_credit,
                                3
                            );
                        } else {
                            DB::table('leave_credits')->insert([
                                'user_id'      => $userId,
                                'leave_id'     => $leaveTypeId,
                                'based_credit' => $creditsToAdd,
                                'used_credit'  => 0,
                            ]);
                            $balanceAfter = $creditsToAdd;
                        }

                        $batchItems[] = [
                            'batch_id'         => $batchId,
                            'user_id'          => $userId,
                            'firstname'        => $emp->firstname,
                            'lastname'         => $emp->lastname,
                            'designation_name' => $emp->designation_name,
                            'division_name'    => $emp->division_name,
                            'section_name'     => $emp->section_name,
                            'employee_type'    => $emp->employee_type,
                            'leave_type_id'    => $leaveTypeId,
                            'leave_type_code'  => $leaveType->code,
                            'credits_added'    => $creditsToAdd,
                            'balance_after'    => $balanceAfter,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ];
                    }
                }

                if (!empty($batchItems)) {
                    DB::table('leave_credit_batch_items')->insert($batchItems);
                }

                session(['last_bulk_credit_batch_id' => $batchId]);
            });

            $batchId = session('last_bulk_credit_batch_id');
            return response()->json([
                'message'  => 'Leave credits updated successfully.',
                'batch_id' => $batchId,
                'print_url' => route('bulk-leave-credits.print', $batchId),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function printBatch($id)
    {
        $batch = DB::table('leave_credit_batches as b')
            ->join('tdh_user.users as u', 'u.id', '=', 'b.created_by')
            ->where('b.id', $id)
            ->first(['b.id', 'b.created_at', 'b.notes', 'u.fname as admin_fname', 'u.lname as admin_lname']);

        if (!$batch) abort(404);

        $items = DB::table('leave_credit_batch_items')
            ->where('batch_id', $id)
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->orderBy('leave_type_code')
            ->get();

        // Group by user_id so each employee is one row with multiple leave types
        $grouped = $items->groupBy('user_id')->map(function ($rows) {
            $first = $rows->first();
            return (object) [
                'firstname'        => $first->firstname,
                'lastname'         => $first->lastname,
                'designation_name' => $first->designation_name,
                'division_name'    => $first->division_name,
                'section_name'     => $first->section_name,
                'employee_type'    => $first->employee_type,
                'credits_added'    => $rows->map(fn($r) => "{$r->leave_type_code} = " . number_format($r->credits_added, 3))->join(', '),
                'balance_after'    => $rows->map(fn($r) => "{$r->leave_type_code} = " . number_format($r->balance_after, 3))->join(', '),
            ];
        })->values();

        return view('print.bulk_leave_credits', compact('batch', 'grouped'));
    }
}
