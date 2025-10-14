<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PassSlipService;

class PassSlipController extends Controller
{
    protected $service;

    public function __construct(PassSlipService $service)
    {
        $this->service = $service;
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
