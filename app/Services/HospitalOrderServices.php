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
}
