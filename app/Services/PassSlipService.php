<?php

namespace App\Services;

use App\Models\PassSlip;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PassSlipService
{
    private OrgHierarchyService $orgHierarchy;

    public function __construct(OrgHierarchyService $orgHierarchy)
    {
        $this->orgHierarchy = $orgHierarchy;
    }

    private function isAdmin(): bool
    {
        return in_array(session('access_rights'), ['admin'], true);
    }

    public function store(array $data, ?int $targetUserId = null)
    {
        $actingUserId = Auth::id();
        $subjectUserId = $actingUserId;
        $filedBy = null;

        if ($targetUserId !== null && $targetUserId !== $actingUserId) {
            $managedIds = $this->orgHierarchy->getManagedEmployeeIds($actingUserId);
            if (!in_array($targetUserId, $managedIds, true)) {
                throw ValidationException::withMessages([
                    'target_user_id' => 'You are not authorized to file a pass slip for this employee.',
                ]);
            }
            $subjectUserId = $targetUserId;
            $filedBy = $actingUserId;
        }

        $payload = [
            'user_id' => $subjectUserId,
            'slip_date' => $data['slip_date'],
            'time_out' => $data['time_out'],
            'time_in' => $data['time_in'] ?? null,
            'type' => $data['type'],
            'filed_by' => $filedBy,
        ];

        if ($filedBy !== null) {
            $payload['status'] = 'approved';
            $payload['approver_id'] = $filedBy;
            $payload['approved_at'] = now();
        } elseif (config('hris.pass_slip_auto_approve')) {
            $payload['status'] = 'approved';
            $payload['approved_at'] = now();
        }

        return PassSlip::create($payload);
    }

    public function getUserPassSlips()
    {
        return PassSlip::with('filedBy')
            ->where('user_id', Auth::id())
            ->orderByDesc('slip_date')
            ->get();
    }

    public function destroy($id)
    {
        $passSlip = PassSlip::where('id', $id)
            ->where(function ($q) {
                $q->where(function ($self) {
                    $self->where('user_id', Auth::id())->whereNull('filed_by');
                })->orWhere('filed_by', Auth::id());
            })
            ->first();

        if (!$passSlip && $this->isAdmin()) {
            $passSlip = PassSlip::find($id);
        }

        if ($passSlip) {
            return $passSlip->delete();
        }

        return false;
    }

    public function getPendingSlips(string $search = '', int $perPage = 10)
    {
        $isAdmin = $this->isAdmin();
        $managedIds = $this->orgHierarchy->getManagedEmployeeIds(Auth::id());

        if ($isAdmin && $search === '') {
            return PassSlip::with(['user', 'filedBy'])
                ->where(function ($q) {
                    $q->where('status', 'pending')->orWhere('filed_by', Auth::id());
                })
                ->orderByDesc('created_at')
                ->paginate($perPage);
        }

        $userQuery = User::query();
        if (!$isAdmin) {
            if (empty($managedIds)) {
                return PassSlip::query()->whereRaw('1 = 0')->paginate($perPage);
            }
            $userQuery->whereIn('id', $managedIds);
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

        return PassSlip::with(['user', 'filedBy'])
            ->where(function ($q) use ($userIds) {
                $q->where('status', 'pending')->orWhere('filed_by', Auth::id());
            })
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

        $managedIds = $this->orgHierarchy->getManagedEmployeeIds(Auth::id());
        if (empty($managedIds)) {
            return null;
        }

        $slip = PassSlip::find($id);
        if (!$slip) {
            return null;
        }

        if (!in_array((int) $slip->user_id, $managedIds, true)) {
            return null;
        }

        $slip->status = $status;
        $slip->approver_id = Auth::id();
        $slip->approved_at = now();
        $slip->save();

        return $slip;
    }
}
