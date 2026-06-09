<?php

namespace App\Http\Controllers;

use App\Services\OvertimeServices;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class OvertimeController extends Controller
{
    protected $overtimeService;
    protected PermissionService $permissions;

    public function __construct(OvertimeServices $overtimeService)
    {
        $this->overtimeService = $overtimeService;
        $this->permissions = app(PermissionService::class);
    }

    private function canReviewOvertimeFilings()
    {
        return $this->permissions->can(Auth::id(), 'overtime.approve')
            || (bool) session('is_section_head')
            || (bool) session('is_division_head')
            || Auth::id() == 325;
    }

    public function fileForSelf(Request $request)
    {
        try {
            $request->validate([
                'ot_date' => 'required|date',
                'time_from' => 'required|date_format:H:i',
                'time_to' => 'required|date_format:H:i|after:time_from',
                'reason' => 'nullable|string|max:500',
            ]);

            $this->overtimeService->fileForSelf(
                $request->ot_date,
                $request->time_from,
                $request->time_to,
                $request->reason
            );

            return response()->json(['message' => 'Overtime request submitted successfully.']);
        } catch (ValidationException $exception) {
            return response()->json(['errors' => $exception->errors()], 422);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function getMyFilings()
    {
        try {
            return response()->json($this->overtimeService->getMyFilings());
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function getFilingsForReview()
    {
        try {
            if (!$this->canReviewOvertimeFilings()) {
                return response()->json(['error' => 'You are not allowed to review overtime filings.'], 403);
            }

            return response()->json($this->overtimeService->getFilingsForReview());
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function approveFiling(Request $request, $id)
    {
        try {
            if (!$this->canReviewOvertimeFilings()) {
                return response()->json(['error' => 'You are not allowed to review overtime filings.'], 403);
            }

            $request->validate([
                'remarks' => 'nullable|string|max:500',
            ]);

            $this->overtimeService->reviewFiling($id, 'approved', $request->remarks);
            return response()->json(['message' => 'Overtime filing approved successfully.']);
        } catch (ValidationException $exception) {
            return response()->json(['errors' => $exception->errors()], 422);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function rejectFiling(Request $request, $id)
    {
        try {
            if (!$this->canReviewOvertimeFilings()) {
                return response()->json(['error' => 'You are not allowed to review overtime filings.'], 403);
            }

            $request->validate([
                'remarks' => 'nullable|string|max:500',
            ]);

            $this->overtimeService->reviewFiling($id, 'rejected', $request->remarks);
            return response()->json(['message' => 'Overtime filing rejected successfully.']);
        } catch (ValidationException $exception) {
            return response()->json(['errors' => $exception->errors()], 422);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function deleteMyFiling($id)
    {
        try {
            $this->overtimeService->deleteMyFiling($id);
            return response()->json(['message' => 'Overtime filing deleted successfully.']);
        } catch (ValidationException $exception) {
            return response()->json(['errors' => $exception->errors()], 422);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }
}
