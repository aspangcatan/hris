<?php

namespace App\Http\Controllers;

use App\Models\HospitalOrderMaster;
use App\Services\HospitalOrderServices;
use Illuminate\Http\Request;

class HospitalOrderController extends Controller
{
    protected $hospitalService;

    public function __construct(HospitalOrderServices $hospitalService)
    {
        $this->hospitalService = $hospitalService;
    }

    public function createHospitalOrder(Request $request)
    {
        try {
            $request->validate([
                'order_number' => 'required|integer',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            $this->hospitalService->createHospitalOrder($request->order_number, $request->start_date, $request->end_date);
            return response()->json(['message' => 'Hospital Order created successfully.']);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function getHospitalOrders(Request $request)
    {
        try {
            $list = $this->hospitalService->getHospitalOrders($request->order_number);
            return response()->json($list);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function assignEmployee(Request $request)
    {
        try {
            $this->hospitalService->assignEmployee($request->ho_id, $request->userid);
            return response()->json(['message' => 'Employee(s) assigned successfully']);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function getAssignedEmployees(Request $request)
    {
        try {
            return response()->json(['assignedUserIds' => $this->hospitalService->getAssignedEmployee($request->ho_id)]);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }
}
