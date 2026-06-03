<?php

namespace App\Http\Controllers;

use App\Services\LeaveMonetizationService;
use Illuminate\Http\Request;

class LeaveMonetizationApprovalController extends Controller
{
    private LeaveMonetizationService $service;

    public function __construct(LeaveMonetizationService $service)
    {
        $this->service = $service;
    }

    public function getForApproval(Request $request)
    {
        try {
            $filter = in_array($request->input('filter'), ['pending', 'approved_by_stage', 'approved_all'], true)
                ? $request->input('filter')
                : 'pending';

            $perPage = 10;
            $page    = max(1, (int) $request->input('page', 1));

            $rows  = $this->service->getForApproval($request->input('search_employee'), $filter);
            $total = $rows->count();

            return response()->json([
                'data'       => $rows->forPage($page, $perPage)->values(),
                'pagination' => [
                    'current_page' => $page,
                    'per_page'     => $perPage,
                    'total'        => $total,
                    'last_page'    => max(1, (int) ceil($total / $perPage)),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function updateApprovalStatus(Request $request)
    {
        try {
            $this->service->updateApprovalStatus(
                (int) $request->input('id'),
                $request->input('remarks'),
                $request->input('status')
            );
            return response()->json(['message' => 'Approval status updated.']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
