<?php

namespace App\Http\Controllers;

use App\Services\LeaveCancellationService;
use Illuminate\Http\Request;

class LeaveCancellationController extends Controller
{
    protected LeaveCancellationService $service;

    public function __construct(LeaveCancellationService $service)
    {
        $this->service = $service;
    }

    // -----------------------------------------------------------------------
    // Employee
    // -----------------------------------------------------------------------

    public function getApprovedDates()
    {
        return response()->json($this->service->getApprovedDatesForUser());
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'date_ids'   => 'required|array|min:1',
                'date_ids.*' => 'required|integer',
                'reason'     => 'required|string|max:1000',
            ]);

            $this->service->fileCancellation($request->only('date_ids', 'reason'));

            return response()->json(['message' => 'Cancellation filed successfully.']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function getMyCancellations(Request $request)
    {
        $perPage    = 10;
        $page       = (int) $request->input('page', 1);
        $collection = $this->service->getMyCancellations();
        $total      = $collection->count();

        return response()->json([
            'data'       => $collection->forPage($page, $perPage)->values(),
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => max(1, ceil($total / $perPage)),
            ],
        ]);
    }

    public function show(int $id)
    {
        if (!$this->service->canViewCancellation($id)) {
            return response()->json(['error' => 'Not found.'], 404);
        }

        $data = $this->service->getCancellationById($id);

        if (!$data) {
            return response()->json(['error' => 'Not found.'], 404);
        }

        return response()->json($data);
    }

    // -----------------------------------------------------------------------
    // Approver
    // -----------------------------------------------------------------------

    public function getForApproval(Request $request)
    {
        $perPage = 10;
        $page    = (int) $request->input('page', 1);
        $filter  = in_array($request->input('filter'), ['pending', 'approved_by_stage', 'approved_all'], true)
            ? $request->input('filter') : 'pending';

        $collection = $this->service->getCancellationsForApproval($request->search_employee, $filter);
        $total      = $collection->count();

        return response()->json([
            'data'       => $collection->forPage($page, $perPage)->values(),
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => max(1, ceil($total / $perPage)),
            ],
        ]);
    }

    public function updateApprovalStatus(Request $request)
    {
        try {
            $request->validate([
                'id'     => 'required|integer',
                'status' => 'required|string|in:Approved,Rejected',
            ]);

            $this->service->updateApprovalStatus(
                $request->id,
                $request->status,
                $request->input('remarks')
            );

            return response()->json(['message' => 'Status updated successfully.']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // -----------------------------------------------------------------------
    // Admin
    // -----------------------------------------------------------------------

    public function getAllForAdmin(Request $request)
    {
        $perPage    = 15;
        $page       = (int) $request->input('page', 1);
        $collection = $this->service->getAllCancellations($request->search_employee);
        $total      = $collection->count();

        return response()->json([
            'data'       => $collection->forPage($page, $perPage)->values(),
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => max(1, ceil($total / $perPage)),
            ],
        ]);
    }
}
