<?php

namespace App\Http\Controllers;

use App\Services\PermissionService;
use App\Services\PassSlipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PassSlipController extends Controller
{
    protected $service;
    protected PermissionService $permissions;

    public function __construct(PassSlipService $service)
    {
        $this->service = $service;
        $this->permissions = app(PermissionService::class);
    }

    public function index()
    {
        return view('pass_slip.index');
    }

    public function list()
    {
        $data = $this->service->getUserPassSlips();
        return response()->json($data);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'slip_date' => 'required|date',
            'time_out' => 'required',
            'time_in' => 'nullable',
            'type' => 'required|in:Personal,Official',
        ]);

        $this->service->store($validated);

        return response()->json(['message' => 'Pass slip submitted successfully!']);
    }

    public function destroy($id)
    {
        $deleted = $this->service->destroy($id);

        if ($deleted) {
            return response()->json(['message' => 'Pass slip deleted successfully!']);
        }

        return response()->json(['message' => 'Pass slip not found.'], 404);
    }

}
