<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OvertimeServices
{
    private function isAdmin()
    {
        return in_array(session('access_rights'), ['admin'], true);
    }

    private function isSectionHead()
    {
        return (bool) session('is_section_head');
    }

    private function isDivisionHead()
    {
        return (bool) session('is_division_head');
    }

    private function applyManagedEmployeeScope($query, $employeeAlias = 'u')
    {
        if ($this->isDivisionHead()) {
            $managedDivisionIds = DB::table('tdh_user.division')
                ->where('head', Auth::id())
                ->pluck('id')
                ->toArray();

            if (empty($managedDivisionIds)) {
                $query->whereRaw('1=0');
                return;
            }

            $query->whereIn($employeeAlias . '.division', $managedDivisionIds);
            return;
        }

        if ($this->isSectionHead()) {
            $managedSectionIds = DB::table('tdh_user.section')
                ->where('head', Auth::id())
                ->pluck('id')
                ->toArray();

            if (empty($managedSectionIds)) {
                $query->whereRaw('1=0');
                return;
            }

            $query->whereIn($employeeAlias . '.section', $managedSectionIds);
            return;
        }

        $query->whereRaw('1=0');
    }

    private function resolveOvertimeApprovers(int $userId): array
    {
        $employee = DB::table('tdh_user.users')->where('id', $userId)->first(['section', 'division']);

        $shId = null;
        $dhId = null;

        if ($employee && $employee->section) {
            $shId = DB::table('tdh_user.section')
                ->where('id', $employee->section)
                ->value('head');
        }

        if ($employee && $employee->division) {
            $dhId = DB::table('tdh_user.division')
                ->where('id', $employee->division)
                ->value('head');
        }

        return [
            ['role' => 'SH', 'order_no' => 1, 'approver_id' => $shId ? (int) $shId : null],
            ['role' => 'DH', 'order_no' => 2, 'approver_id' => $dhId ? (int) $dhId : null],
            ['role' => 'HR', 'order_no' => 3, 'approver_id' => 15],
        ];
    }

    public function fileForSelf($otDate, $timeFrom, $timeTo, $reason = null)
    {
        $userId = Auth::id();

        $exists = DB::table('hris.overtime_requests')
            ->where('userid', $userId)
            ->where('ot_date', $otDate)
            ->where('time_from', $timeFrom)
            ->where('time_to', $timeTo)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'ot_date' => 'You already filed an overtime request with the same date and time range.',
            ]);
        }

        DB::transaction(function () use ($otDate, $timeFrom, $timeTo, $reason, $userId) {
            $now = Carbon::now();
            $approvers = $this->resolveOvertimeApprovers($userId);

            $requestId = DB::table('hris.overtime_requests')->insertGetId([
                'userid'     => $userId,
                'ot_date'    => $otDate,
                'time_from'  => $timeFrom,
                'time_to'    => $timeTo,
                'reason'     => $reason,
                'status'     => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($approvers as $approver) {
                $autoApproved = ($approver['approver_id'] === null);
                DB::table('hris.overtime_request_approvals')->insert([
                    'overtime_request_id' => $requestId,
                    'approver_id'         => $approver['approver_id'],
                    'role'                => $approver['role'],
                    'order_no'            => $approver['order_no'],
                    'status'              => $autoApproved ? 'approved' : 'pending',
                    'approved_at'         => $autoApproved ? $now : null,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ]);
            }
        });
    }

    public function getMyFilings()
    {
        return DB::table('hris.overtime_requests as ot')
            ->where('ot.userid', Auth::id())
            ->orderBy('ot.created_at', 'desc')
            ->get([
                'ot.id',
                'ot.ot_date',
                'ot.time_from',
                'ot.time_to',
                'ot.reason',
                'ot.status',
                'ot.remarks',
                'ot.reviewed_at',
            ]);
    }

    public function getFilingsForReview()
    {
        $query = DB::table('hris.overtime_requests as ot')
            ->join('tdh_user.users as u', 'u.id', '=', 'ot.userid')
            ->where('ot.status', 'pending')
            ->orderBy('ot.created_at', 'desc');

        if (!$this->isAdmin()) {
            $query->where(function ($where) {
                $this->applyManagedEmployeeScope($where, 'u');
            });
        }

        return $query->get([
                'ot.id',
                'ot.userid',
                DB::raw("CONCAT(COALESCE(u.fname, ''), ' ', COALESCE(LEFT(u.mname,1), ''), CASE WHEN u.mname IS NULL OR u.mname = '' THEN '' ELSE '. ' END, COALESCE(u.lname, ''), CASE WHEN u.suffix IS NULL OR u.suffix = '' THEN '' ELSE CONCAT(' ', u.suffix) END) as employee_name"),
                'ot.ot_date',
                'ot.time_from',
                'ot.time_to',
                'ot.reason',
                'ot.status',
                'ot.remarks',
                'ot.reviewed_at',
            ]);
    }

    public function reviewFiling($filingId, $status, $remarks = null)
    {
        $filing = DB::table('hris.overtime_requests as ot')
            ->join('tdh_user.users as u', 'u.id', '=', 'ot.userid')
            ->where('ot.id', $filingId)
            ->select('ot.id', 'ot.status')
            ;

        if (!$this->isAdmin()) {
            $filing->where(function ($where) {
                $this->applyManagedEmployeeScope($where, 'u');
            });
        }

        $filing = $filing->first();

        if (!$filing) {
            throw ValidationException::withMessages([
                'id' => 'Overtime filing record not found.',
            ]);
        }

        if (!in_array(strtolower((string) $filing->status), ['pending'], true)) {
            throw ValidationException::withMessages([
                'id' => 'Only pending overtime filings can be reviewed.',
            ]);
        }

        DB::table('hris.overtime_requests')
            ->where('id', $filingId)
            ->update([
                'status' => $status,
                'remarks' => $remarks,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
    }

    private function awardCtoCredits(int $userId, string $timeFrom, string $timeTo): void
    {
        $from = Carbon::createFromFormat('H:i:s', $timeFrom);
        $to   = Carbon::createFromFormat('H:i:s', $timeTo);
        $totalMinutes = (int) $from->diffInMinutes($to);

        if ($totalMinutes <= 0) return;

        $minuteConversions = DB::table('hris.minute_conversions')
            ->pluck('points_deduction', 'minute');

        $fullHours = intdiv($totalMinutes, 60);
        $remaining = $totalMinutes % 60;

        $credits = ((float) ($minuteConversions[60] ?? 0)) * $fullHours
                 + ($remaining > 0 ? (float) ($minuteConversions[$remaining] ?? 0) : 0);

        if ($credits <= 0) return;

        $ctoLeaveId = DB::table('hris.leave_types')->where('code', 'CTO')->value('id');
        if (!$ctoLeaveId) return;

        $existing = DB::table('hris.leave_credits')
            ->where('user_id', $userId)
            ->where('leave_id', $ctoLeaveId)
            ->first(['id', 'based_credit']);

        if ($existing) {
            DB::table('hris.leave_credits')
                ->where('id', $existing->id)
                ->update([
                    'based_credit'       => (float) $existing->based_credit + $credits,
                    'last_updated_month' => Carbon::now()->format('Y-m'),
                ]);
        } else {
            DB::table('hris.leave_credits')->insert([
                'user_id'            => $userId,
                'leave_id'           => $ctoLeaveId,
                'based_credit'       => $credits,
                'used_credit'        => 0,
                'last_updated_month' => Carbon::now()->format('Y-m'),
            ]);
        }
    }

    public function deleteMyFiling($filingId)
    {
        $filing = DB::table('hris.overtime_requests')
            ->where('id', $filingId)
            ->where('userid', Auth::id())
            ->first(['id', 'status']);

        if (!$filing) {
            throw ValidationException::withMessages([
                'id' => 'Overtime filing record not found.',
            ]);
        }

        $status = strtolower((string) $filing->status);
        if (!in_array($status, ['pending', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'id' => 'Only pending or rejected overtime filings can be deleted.',
            ]);
        }

        DB::table('hris.overtime_requests')
            ->where('id', $filingId)
            ->delete();
    }
}
