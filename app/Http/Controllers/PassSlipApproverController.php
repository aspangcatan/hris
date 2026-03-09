<?php

namespace App\Http\Controllers;

use App\Services\PassSlipService;
use Illuminate\Http\Request;

class PassSlipApproverController extends Controller
{
    protected $service;

    public function __construct(PassSlipService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return view('pass_slip.approver');
    }

    public function list(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $perPage = (int) $request->input('per_page', 10);
        if ($perPage < 1) {
            $perPage = 10;
        }
        if ($perPage > 50) {
            $perPage = 50;
        }

        $paginator = $this->service->getPendingSlips($search, $perPage);

        return response()->json([
            'results' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
        ]);
    }

    public function approve($id)
    {
        $updated = $this->service->updateStatus($id, 'approved');
        if (!$updated) {
            return response()->json(['message' => 'Pass slip not found or not allowed.'], 404);
        }

        return response()->json(['message' => 'Pass slip approved successfully!']);
    }

    public function reject($id)
    {
        $updated = $this->service->updateStatus($id, 'rejected');
        if (!$updated) {
            return response()->json(['message' => 'Pass slip not found or not allowed.'], 404);
        }

        return response()->json(['message' => 'Pass slip rejected successfully!']);
    }
}
