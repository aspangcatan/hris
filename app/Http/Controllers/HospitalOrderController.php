<?php

namespace App\Http\Controllers;

use App\Services\HospitalOrderServices;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class HospitalOrderController extends Controller
{
    protected $hospitalService;
    protected PermissionService $permissions;

    public function __construct(HospitalOrderServices $hospitalService)
    {
        $this->hospitalService = $hospitalService;
        $this->permissions = app(PermissionService::class);
    }

    private function canReviewHospitalOrderFilings()
    {
        return $this->permissions->can(Auth::id(), 'hospital_order.approve');
    }

    public function createHospitalOrder(Request $request)
    {
        try {
            if (!$this->permissions->can(Auth::id(), 'hospital_order.approve')) {
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
            if (!$this->canReviewHospitalOrderFilings()) {
                return response()->json(['error' => 'You are not allowed to review filings.'], 403);
            }
            return response()->json($this->hospitalService->getFilingsForReview());
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function approveFiling(Request $request, $id)
    {
        try {
            if (!$this->canReviewHospitalOrderFilings()) {
                return response()->json(['error' => 'You are not allowed to review filings.'], 403);
            }

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
            if (!$this->canReviewHospitalOrderFilings()) {
                return response()->json(['error' => 'You are not allowed to review filings.'], 403);
            }

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

    public function deleteMyFiling($id)
    {
        try {
            $this->hospitalService->deleteMyFiling($id);
            return response()->json(['message' => 'Filing deleted successfully.']);
        } catch (ValidationException $exception) {
            return response()->json(['errors' => $exception->errors()], 422);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }
}
