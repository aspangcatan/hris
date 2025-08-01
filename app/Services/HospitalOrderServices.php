<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HospitalOrderServices
{
    public function createHospitalOrder($order_number, $start_date, $end_date)
    {
        DB::table('hris.hospital_orders')->insert([
            'order_number' => $order_number,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'created_by' => Auth::id(),
            'created_at' => Carbon::now()
        ]);
    }

    public function getHospitalOrders($order_number)
    {
        return DB::table('hris.hospital_orders')
            ->where(function ($query) use ($order_number) {
                if ($order_number != "") $query->where('order_number', 'LIKE', '%' . $order_number . '%');
            })
            ->limit(10)
            ->get();
    }

    public function getHospitalOrderById($id)
    {
        return DB::table('hris.hospital_orders')
            ->where('id', '=', $id)
            ->first();
    }

    public function assignEmployee($ho_id, $userIds)
    {
        // If single id passed as string/int, convert to array for safety
        if (!is_array($userIds)) {
            $userIds = [$userIds];
        }

        $insertData = [];
        foreach ($userIds as $userId) {
            $insertData[] = [
                'ho_id' => $ho_id,
                'userid' => $userId,
            ];
        }

        DB::table('hris.hospital_order_employees')
            ->where('ho_id', $ho_id)
            ->whereIn('userid', $userIds)
            ->delete();

        DB::table('hris.hospital_order_employees')->insert($insertData);
    }

    public function getAssignedEmployee($ho_id)
    {
        return $assigned = DB::table('hris.hospital_order_employees')
            ->where('ho_id', $ho_id)
            ->pluck('userid'); // returns collection of user_ids
    }
}
