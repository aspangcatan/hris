<?php

namespace App\Http\Controllers;

use App\Services\PassSlipService;

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

    public function list()
    {
        $slips = $this->service->getPendingSlips();
        return response()->json($slips);
    }

    public function approve($id)
    {
        $this->service->updateStatus($id, 'approved');
        return response()->json(['message' => 'Pass slip approved successfully!']);
    }

    public function reject($id)
    {
        $this->service->updateStatus($id, 'rejected');
        return response()->json(['message' => 'Pass slip rejected successfully!']);
    }
}
