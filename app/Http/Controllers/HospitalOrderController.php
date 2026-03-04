<?php

namespace App\Http\Controllers;

use App\Services\HospitalOrderServices;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
            if (!in_array(session('access_rights'), ['admin', 'calendar'])) {
                return response()->json(['error' => 'Only authorized users can create hospital orders.'], 403);
            }

            $request->validate([
                'order_number' => 'required|string|max:100',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'time_from' => 'required|date_format:H:i',
                'time_to' => 'required|date_format:H:i|after:time_from',
            ]);

            $this->hospitalService->createHospitalOrder(
                $request->order_number,
                $request->start_date,
                $request->end_date,
                $request->time_from,
                $request->time_to
            );

            return response()->json(['message' => 'Hospital Order created successfully.']);
        } catch (ValidationException $exception) {
            return response()->json(['errors' => $exception->errors()], 422);
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

    public function fileForSelf(Request $request)
    {
        try {
            $request->validate([
                'ho_id' => 'required|integer',
                'filed_date' => 'required|date',
                'time_from' => 'required|date_format:H:i',
                'time_to' => 'required|date_format:H:i|after:time_from',
            ]);

            $this->hospitalService->fileForSelf(
                $request->ho_id,
                $request->filed_date,
                $request->time_from,
                $request->time_to
            );

            return response()->json(['message' => 'Hospital order filing submitted successfully.']);
        } catch (ValidationException $exception) {
            return response()->json(['errors' => $exception->errors()], 422);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function getMyFilings()
    {
        try {
            return response()->json($this->hospitalService->getMyFilings());
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function getFilingsForReview()
    {
        try {
            return response()->json($this->hospitalService->getFilingsForReview());
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function approveFiling(Request $request, $id)
    {
        try {
            $request->validate([
                'remarks' => 'nullable|string|max:500',
            ]);

            $this->hospitalService->reviewFiling($id, 'approved', $request->remarks);
            return response()->json(['message' => 'Filing approved successfully.']);
        } catch (ValidationException $exception) {
            return response()->json(['errors' => $exception->errors()], 422);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function rejectFiling(Request $request, $id)
    {
        try {
            $request->validate([
                'remarks' => 'nullable|string|max:500',
            ]);

            $this->hospitalService->reviewFiling($id, 'rejected', $request->remarks);
            return response()->json(['message' => 'Filing rejected successfully.']);
        } catch (ValidationException $exception) {
            return response()->json(['errors' => $exception->errors()], 422);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }
}
