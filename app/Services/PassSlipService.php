<?php

namespace App\Services;

use App\Models\PassSlip;
use Illuminate\Support\Facades\Auth;

class PassSlipService
{
    public function store(array $data)
    {
        return PassSlip::create([
            'userid' => Auth::id(),
            'slip_date' => $data['slip_date'],
            'time_out' => $data['time_out'],
            'time_in' => $data['time_in'] ?? null,
            'type' => $data['type'],
        ]);
    }

    public function getUserPassSlips()
    {
        return PassSlip::where('userid', Auth::id())
            ->orderByDesc('slip_date')
            ->get();
    }

    public function destroy($id)
    {
        $passSlip = PassSlip::where('userid', Auth::id())->find($id);

        if ($passSlip) {
            return $passSlip->delete();
        }

        return false;
    }

    public function getPendingSlips()
    {
        return \App\Models\PassSlip::with('user')
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get();
    }

    public function updateStatus($id, $status)
    {
        $slip = \App\Models\PassSlip::find($id);

        if ($slip) {
            $slip->status = $status;
            $slip->approver_id = Auth::id();
            $slip->approved_at = now();
            $slip->save();
        }

        return $slip;
    }


}
