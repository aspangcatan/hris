<?php

namespace App\Http\Controllers;

use App\Services\LeaveMonetizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeaveMonetizationController extends Controller
{
    private LeaveMonetizationService $service;

    public function __construct(LeaveMonetizationService $service)
    {
        $this->service = $service;
    }

    public function monetizableTypes()
    {
        try {
            $types = $this->service->getMonetizableTypes(Auth::id());
            return response()->json($types);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $page = max(1, (int) $request->input('page', 1));
            $data = $this->service->getMyMonetizations($page);
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'items'                    => 'required|array|min:1',
            'items.*.leave_type_id'    => 'required|integer',
            'items.*.days_requested'   => 'required|numeric|min:0.001',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        try {
            $ids = $this->service->storeMany($request->input('items'));
            $count = count($ids);
            $msg = $count === 1
                ? 'Monetization request filed successfully.'
                : "{$count} monetization requests filed successfully.";
            return response()->json(['message' => $msg, 'ids' => $ids]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function delete(Request $request, $id)
    {
        try {
            $this->service->delete((int) $id);
            return response()->json(['message' => 'Monetization request deleted. Credits have been restored.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
