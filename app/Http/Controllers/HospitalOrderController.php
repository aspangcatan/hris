<?php

namespace App\Http\Controllers;

use App\Models\HospitalOrder;
use App\Models\HospitalOrderMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HospitalOrderController extends Controller
{
    public function store(Request $request)
    {
        try {
            $request->validate([
                'hospital_order' => 'required|integer',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            HospitalOrder::create([
                'hospital_order' => $request->order_number,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'created_by' => Auth::id(),
            ]);

            return response()->json(['message' => 'Hospital Order created successfully.']);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }
}
