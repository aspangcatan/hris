<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HospitalOrderServices
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

    public function createHospitalOrder($orderNumber, $startDate, $endDate, $timeFrom, $timeTo)
    {
        DB::table('hris.hospital_orders')->insert([
            'order_number' => $orderNumber,
            'order_date' => $startDate,
            'time_from' => $timeFrom,
            'time_to' => $timeTo,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'created_by' => Auth::id(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    public function getHospitalOrders($orderNumber = '')
    {
        return DB::table('hris.hospital_orders as ho')
            ->where(function ($query) use ($orderNumber) {
                if ($orderNumber !== '') {
                    $query->where('ho.order_number', 'LIKE', '%' . $orderNumber . '%');
                }
            })
            ->orderBy('ho.start_date', 'desc')
            ->orderBy('ho.id', 'desc')
            ->limit(10)
            ->get([
                'ho.id',
                'ho.order_number',
                'ho.start_date',
                'ho.end_date',
                'ho.time_from',
                'ho.time_to',
                'ho.created_by',
            ]);
    }

    public function fileForSelf($hoId, $filedDate, $timeFrom, $timeTo)
    {
        $hospitalOrder = DB::table('hris.hospital_orders')
            ->where('id', $hoId)
            ->first();

        if (!$hospitalOrder) {
            throw ValidationException::withMessages([
                'ho_id' => 'Hospital order not found.',
            ]);
        }

        if ($hospitalOrder->start_date && $hospitalOrder->end_date) {
            $startDate = Carbon::parse($hospitalOrder->start_date)->startOfDay();
            $endDate = Carbon::parse($hospitalOrder->end_date)->endOfDay();
            $toFileDate = Carbon::parse($filedDate);

            if (!$toFileDate->betweenIncluded($startDate, $endDate)) {
                throw ValidationException::withMessages([
                    'filed_date' => 'Filed date must be within the hospital order date range.',
                ]);
            }
        } elseif ($hospitalOrder->order_date && $filedDate !== $hospitalOrder->order_date) {
            throw ValidationException::withMessages([
                'filed_date' => 'Filed date must match the hospital order date.',
            ]);
        }

        $exists = DB::table('hris.hospital_order_employees')
            ->where('ho_id', $hoId)
            ->where('userid', Auth::id())
            ->where('filed_date', $filedDate)
            ->where('time_from', $timeFrom)
            ->where('time_to', $timeTo)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'filed_date' => 'You already filed this hospital order with the same date and time range.',
            ]);
        }

        DB::table('hris.hospital_order_employees')->insert([
            'ho_id' => $hoId,
            'userid' => Auth::id(),
            'filed_date' => $filedDate,
            'time_from' => $timeFrom,
            'time_to' => $timeTo,
            'status' => 'pending',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    public function getMyFilings()
    {
        return DB::table('hris.hospital_order_employees as hoe')
            ->join('hris.hospital_orders as ho', 'ho.id', '=', 'hoe.ho_id')
            ->where('hoe.userid', Auth::id())
            ->orderBy('hoe.created_at', 'desc')
            ->get([
                'hoe.id',
                'hoe.ho_id',
                'ho.order_number',
                'hoe.filed_date',
                'hoe.time_from',
                'hoe.time_to',
                'hoe.status',
                'hoe.remarks',
                'hoe.reviewed_at',
            ]);
    }

    public function getFilingsForReview()
    {
        $query = DB::table('hris.hospital_order_employees as hoe')
            ->join('tdh_user.users as u', 'u.id', '=', 'hoe.userid')
            ->join('hris.hospital_orders as ho', 'ho.id', '=', 'hoe.ho_id')
            ->orderByRaw("CASE WHEN hoe.status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('hoe.created_at', 'desc');

        if (!$this->isAdmin()) {
            $query->where(function ($where) {
                $this->applyManagedEmployeeScope($where, 'u');
            });
        }

        return $query->get([
            'hoe.id',
            'ho.order_number',
            'ho.created_by',
            'hoe.userid',
            DB::raw("CONCAT(COALESCE(u.fname, ''), ' ', COALESCE(LEFT(u.mname,1), ''), CASE WHEN u.mname IS NULL OR u.mname = '' THEN '' ELSE '. ' END, COALESCE(u.lname, ''), CASE WHEN u.suffix IS NULL OR u.suffix = '' THEN '' ELSE CONCAT(' ', u.suffix) END) as employee_name"),
            'hoe.filed_date',
            'hoe.time_from',
            'hoe.time_to',
            'hoe.status',
            'hoe.remarks',
            'hoe.reviewed_at',
        ]);
    }

    public function reviewFiling($filingId, $status, $remarks = null)
    {
        $filing = DB::table('hris.hospital_order_employees as hoe')
            ->join('tdh_user.users as u', 'u.id', '=', 'hoe.userid')
            ->where('hoe.id', $filingId)
            ->select('hoe.id', 'hoe.status')
            ;

        if (!$this->isAdmin()) {
            $filing->where(function ($where) {
                $this->applyManagedEmployeeScope($where, 'u');
            });
        }

        $filing = $filing->first();

        if (!$filing) {
            throw ValidationException::withMessages([
                'id' => 'Filing record not found.',
            ]);
        }

        if (!in_array(strtolower((string) $filing->status), ['pending'], true)) {
            throw ValidationException::withMessages([
                'id' => 'Only pending filings can be reviewed.',
            ]);
        }

        DB::table('hris.hospital_order_employees')
            ->where('id', $filingId)
            ->update([
                'status' => $status,
                'remarks' => $remarks,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
    }

    public function deleteMyFiling($filingId)
    {
        $filing = DB::table('hris.hospital_order_employees')
            ->where('id', $filingId)
            ->where('userid', Auth::id())
            ->first(['id', 'status']);

        if (!$filing) {
            throw ValidationException::withMessages([
                'id' => 'Filing record not found.',
            ]);
        }

        $status = strtolower((string) $filing->status);
        if (!in_array($status, ['pending', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'id' => 'Only pending or rejected filings can be deleted.',
            ]);
        }

        DB::table('hris.hospital_order_employees')
            ->where('id', $filingId)
            ->delete();
    }
}
