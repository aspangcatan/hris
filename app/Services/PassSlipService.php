<?php

namespace App\Services;

use App\Models\PassSlip;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PassSlipService
{
    private function getManagedSectionIds(): array
    {
        return DB::table('tdh_user.section')
            ->where('head', Auth::id())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function isAdmin(): bool
    {
        return in_array(session('access_rights'), ['admin'], true);
    }

    public function store(array $data)
    {
        $payload = [
            'user_id' => Auth::id(),
            'slip_date' => $data['slip_date'],
            'time_out' => $data['time_out'],
            'time_in' => $data['time_in'] ?? null,
            'type' => $data['type'],
        ];

        if (config('hris.pass_slip_auto_approve')) {
            $payload['status'] = 'approved';
            $payload['approved_at'] = now();
        }

        return PassSlip::create($payload);
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
        $managedSectionIds = $this->getManagedSectionIds();

        if ($isAdmin && $search === '') {
            return PassSlip::with('user')
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->paginate($perPage);
        }

        $userQuery = User::query();
        if (!$isAdmin) {
            if (empty($managedSectionIds)) {
                return PassSlip::query()->whereRaw('1 = 0')->paginate($perPage);
            }
            $userQuery->whereIn('section', $managedSectionIds);
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

        $managedSectionIds = $this->getManagedSectionIds();
        if (empty($managedSectionIds)) {
            return null;
        }

        $slip = PassSlip::find($id);
        if (!$slip) {
            return null;
        }

        $slipOwner = User::query()->select('id', 'section')->find($slip->user_id);
        if (!$slipOwner || !in_array((int) $slipOwner->section, $managedSectionIds, true)) {
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
