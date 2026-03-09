<?php

namespace App\Services;

use App\Models\PassSlip;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class PassSlipService
{
    private function isAdmin(): bool
    {
        return in_array(session('access_rights'), ['admin'], true);
    }

    public function store(array $data)
    {
        return PassSlip::create([
            'user_id' => Auth::id(),
            'slip_date' => $data['slip_date'],
            'time_out' => $data['time_out'],
            'time_in' => $data['time_in'] ?? null,
            'type' => $data['type'],
        ]);
    }

    public function getUserPassSlips()
    {
        return PassSlip::where('user_id', Auth::id())
            ->orderByDesc('slip_date')
            ->get();
    }

    public function destroy($id)
    {
        $passSlip = PassSlip::where('user_id', Auth::id())->find($id);

        if ($passSlip) {
            return $passSlip->delete();
        }

        return false;
    }

    public function getPendingSlips(string $search = '', int $perPage = 10)
    {
        $isAdmin = $this->isAdmin();
        $user = Auth::user();
        $sectionId = $user->section ?? null;

        if ($isAdmin && $search === '') {
            return PassSlip::with('user')
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->paginate($perPage);
        }

        $userQuery = User::query();
        if (!$isAdmin) {
            if (empty($sectionId)) {
                return PassSlip::query()->whereRaw('1 = 0')->paginate($perPage);
            }
            $userQuery->where('section', $sectionId);
        }

        if ($search !== '') {
            $userQuery->where(function ($q) use ($search) {
                $q->where('fname', 'like', "%{$search}%")
                    ->orWhere('mname', 'like', "%{$search}%")
                    ->orWhere('lname', 'like', "%{$search}%")
                    ->orWhere('suffix', 'like', "%{$search}%");
            });
        }

        $userIds = $userQuery->pluck('id')->all();
        if (empty($userIds)) {
            return PassSlip::query()->whereRaw('1 = 0')->paginate($perPage);
        }

        return PassSlip::with('user')
            ->where('status', 'pending')
            ->whereIn('user_id', $userIds)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function updateStatus($id, $status)
    {
        if ($this->isAdmin()) {
            $slip = PassSlip::find($id);
            if (!$slip) {
                return null;
            }

            $slip->status = $status;
            $slip->approver_id = Auth::id();
            $slip->approved_at = now();
            $slip->save();

            return $slip;
        }

        $user = Auth::user();
        $sectionId = $user->section ?? null;

        if (empty($sectionId)) {
            return null;
        }

        $slip = PassSlip::find($id);
        if (!$slip) {
            return null;
        }

        $slipOwner = User::query()->select('id', 'section')->find($slip->user_id);
        if (!$slipOwner || (int) $slipOwner->section !== (int) $sectionId) {
            return null;
        }

        if ($slip) {
            $slip->status = $status;
            $slip->approver_id = Auth::id();
            $slip->approved_at = now();
            $slip->save();
        }

        return $slip;
    }


}
