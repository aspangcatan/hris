<?php

namespace App\Http\Controllers;

use App\Models\LeaveBalanceReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function leaveBalance()
    {
        try {
            $results = LeaveBalanceReport::get(['name', 'period', 'vl_bal', 'sl_bal', 'cto_bal', 'fl_bal', 'spl_bal']);
            return response(['code' => 200, 'results' => $results]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    // ─── Quarterly Leave Balance ──────────────────────────────────────────────

    public function quarterlyLeaveBalance()
    {
        return view('reports.quarterly_leave_balance');
    }

    public function getQuarterlyLeaveBalance(Request $request)
    {
        try {
            $request->validate([
                'year'     => 'required|integer|min:2000',
                'quarter'  => 'required|integer|in:1,2,3,4',
                'division' => 'nullable|integer',
                'section'  => 'nullable|integer',
            ]);

            $year     = (int) $request->year;
            $quarter  = (int) $request->quarter;
            $division = $request->division;
            $section  = $request->section;

            // Employees with actual credit assignments only (inner join on leave_credits)
            $rows = DB::table('hris.leave_credits as lc')
                ->join('tdh_user.users as u', 'u.id', '=', 'lc.user_id')
                ->join('hris.leave_types as t', 't.id', '=', 'lc.leave_id')
                ->leftJoin('tdh_user.division as d', 'd.id', '=', 'u.division')
                ->leftJoin('tdh_user.section as s', 's.id', '=', 'u.section')
                ->where('t.status', 'active')
                ->where('lc.based_credit', '>', 0)
                ->when($division, fn($q) => $q->where('u.division', $division))
                ->when($section,  fn($q) => $q->where('u.section',  $section))
                ->orderByRaw('u.lname, u.fname, t.description')
                ->get([
                    'u.id as user_id',
                    'u.fname', 'u.mname', 'u.lname',
                    't.id as leave_type_id',
                    't.description as leave_type',
                    'd.description as division_name',
                    's.description as section_name',
                    DB::raw('lc.based_credit'),
                    DB::raw('lc.used_credit'),
                    DB::raw('lc.based_credit - lc.used_credit as balance'),
                ]);

            if ($rows->isEmpty()) {
                return response()->json([]);
            }

            // Save snapshot
            $divisionName = $division
                ? DB::table('tdh_user.division')->where('id', $division)->value('description')
                : null;
            $sectionName = $section
                ? DB::table('tdh_user.section')->where('id', $section)->value('description')
                : null;

            $snapshotId = DB::table('hris.leave_balance_snapshots')->insertGetId([
                'year'          => $year,
                'quarter'       => $quarter,
                'division_id'   => $division ?: null,
                'section_id'    => $section  ?: null,
                'division_name' => $divisionName,
                'section_name'  => $sectionName,
                'generated_by'  => Auth::id(),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            $detailRows = $rows->map(function ($r) use ($snapshotId) {
                return [
                    'snapshot_id'     => $snapshotId,
                    'user_id'         => $r->user_id,
                    'leave_type_id'   => $r->leave_type_id,
                    'fname'           => $r->fname,
                    'mname'           => $r->mname,
                    'lname'           => $r->lname,
                    'division_name'   => $r->division_name,
                    'section_name'    => $r->section_name,
                    'leave_type_name' => $r->leave_type,
                    'based_credit'    => $r->based_credit,
                    'used_credit'     => $r->used_credit,
                    'balance'         => $r->balance,
                ];
            })->toArray();

            foreach (array_chunk($detailRows, 500) as $chunk) {
                DB::table('hris.leave_balance_snapshot_details')->insert($chunk);
            }

            return response()->json([
                'snapshot_id' => $snapshotId,
                'rows'        => $rows,
            ]);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function getQuarterlyLeaveBalanceHistory()
    {
        try {
            $history = DB::table('hris.leave_balance_snapshots as sn')
                ->leftJoin('tdh_user.users as u', 'u.id', '=', 'sn.generated_by')
                ->orderByDesc('sn.created_at')
                ->limit(50)
                ->get([
                    'sn.id',
                    'sn.year',
                    'sn.quarter',
                    'sn.division_name',
                    'sn.section_name',
                    'sn.created_at',
                    'u.fname as gen_fname',
                    'u.lname as gen_lname',
                ]);

            return response()->json($history);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function getQuarterlyLeaveBalanceSnapshot(int $id)
    {
        try {
            $snapshot = DB::table('hris.leave_balance_snapshots')->where('id', $id)->first();
            if (!$snapshot) {
                return response()->json(['error' => 'Snapshot not found.'], 404);
            }

            $rows = DB::table('hris.leave_balance_snapshot_details')
                ->where('snapshot_id', $id)
                ->orderByRaw('lname, fname, leave_type_name')
                ->get();

            return response()->json([
                'snapshot' => $snapshot,
                'rows'     => $rows,
            ]);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

}
