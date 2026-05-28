<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveCancellationService
{
    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function canActAsHr(): bool
    {
        return in_array(strtoupper((string) session('access_rights')), ['HR', 'HRH'], true);
    }

    private function canActOnStep(object $step, $user): bool
    {
        $access = strtoupper((string) session('access_rights'));

        if ($step->role === 'SH') {
            return $access === 'ADMIN'
                || ((bool) session('is_section_head')
                    && !empty($user->section)
                    && (int) $step->approver_id === (int) $user->section);
        }
        if ($step->role === 'DH') {
            return (bool) session('is_division_head')
                && !empty($user->division)
                && (int) $step->approver_id === (int) $user->division;
        }
        if ($step->role === 'HR') {
            return $this->canActAsHr();
        }

        return false;
    }

    private function resolveCurrentRole(int $cancellationId): ?string
    {
        $user    = Auth::user();
        $pending = DB::table('hris.leave_cancellation_approvals')
            ->where('leave_cancellation_id', $cancellationId)
            ->where('status', 'pending')
            ->orderBy('order_no')
            ->first(['role', 'approver_id']);

        if (!$pending) return null;

        return $this->canActOnStep($pending, $user) ? $pending->role : null;
    }

    // -----------------------------------------------------------------------
    // Employee: get approved dates for the dropdown
    // -----------------------------------------------------------------------

    public function getApprovedDatesForUser(): \Illuminate\Support\Collection
    {
        return DB::table('hris.leave_date_applied as lda')
            ->join('hris.leave_applications as la', 'lda.leave_application_id', '=', 'la.id')
            ->join('hris.leave_types as lt', 'lt.id', '=', 'lda.leave_type_id')
            ->where('la.user_id', Auth::id())
            ->where('la.status', 'Approved')
            ->where('lda.status', 'active')
            ->whereNotExists(function ($sub) {
                // Exclude dates already pending/approved in an existing cancellation
                $sub->from('hris.leave_cancellation_dates as lcd')
                    ->join('hris.leave_cancellations as lc', 'lc.id', '=', 'lcd.leave_cancellation_id')
                    ->whereColumn('lcd.leave_date_applied_id', 'lda.id')
                    ->whereIn('lc.status', ['Pending', 'Approved']);
            })
            ->select(
                'lda.id',
                'lda.leave_application_id',
                'lda.date_applied',
                'lda.hours',
                DB::raw("GROUP_CONCAT(DISTINCT lt.description ORDER BY lt.id SEPARATOR ', ') as leave_type")
            )
            ->groupBy('lda.id', 'lda.leave_application_id', 'lda.date_applied', 'lda.hours')
            ->orderBy('lda.date_applied')
            ->get();
    }

    // -----------------------------------------------------------------------
    // Employee: file a cancellation
    // -----------------------------------------------------------------------

    public function fileCancellation(array $data): int
    {
        return DB::transaction(function () use ($data) {
            $user = Auth::user();

            // Verify all selected date IDs belong to approved leaves of this user
            $validIds = DB::table('hris.leave_date_applied as lda')
                ->join('hris.leave_applications as la', 'lda.leave_application_id', '=', 'la.id')
                ->where('la.user_id', $user->id)
                ->where('la.status', 'Approved')
                ->whereIn('lda.id', $data['date_ids'])
                ->pluck('lda.id');

            if ($validIds->count() !== count($data['date_ids'])) {
                throw new \RuntimeException('One or more selected dates are invalid.');
            }

            $id = DB::table('hris.leave_cancellations')->insertGetId([
                'user_id'    => $user->id,
                'reason'     => $data['reason'],
                'status'     => 'Pending',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            // Store each selected date (denormalized for display after deletion)
            $dates = DB::table('hris.leave_date_applied')
                ->whereIn('id', $data['date_ids'])
                ->get(['id', 'date_applied', 'hours']);

            foreach ($dates as $d) {
                DB::table('hris.leave_cancellation_dates')->insert([
                    'leave_cancellation_id' => $id,
                    'leave_date_applied_id' => $d->id,
                    'date_applied'          => $d->date_applied,
                    'hours'                 => $d->hours,
                    'created_at'            => Carbon::now(),
                    'updated_at'            => Carbon::now(),
                ]);
            }

            // 3-stage approval chain
            DB::table('hris.leave_cancellation_approvals')->insert([
                ['leave_cancellation_id' => $id, 'approver_id' => $user->section,  'role' => 'SH', 'order_no' => 1, 'status' => 'pending', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
                ['leave_cancellation_id' => $id, 'approver_id' => $user->division, 'role' => 'DH', 'order_no' => 2, 'status' => 'pending', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
                ['leave_cancellation_id' => $id, 'approver_id' => 15,              'role' => 'HR', 'order_no' => 3, 'status' => 'pending', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ]);

            return $id;
        });
    }

    // -----------------------------------------------------------------------
    // Employee: list own cancellations
    // -----------------------------------------------------------------------

    public function getMyCancellations(): \Illuminate\Support\Collection
    {
        $rows = DB::table('hris.leave_cancellations as lc')
            ->where('lc.user_id', Auth::id())
            ->orderByDesc('lc.id')
            ->get(['lc.id', 'lc.reason', 'lc.status', 'lc.created_at']);

        return $rows->map(function ($row) {
            $row->dates = DB::table('hris.leave_cancellation_dates')
                ->where('leave_cancellation_id', $row->id)
                ->orderBy('date_applied')
                ->pluck('date_applied');
            return $row;
        });
    }

    // -----------------------------------------------------------------------
    // Show one cancellation (for modal — employee + approver view)
    // -----------------------------------------------------------------------

    public function getCancellationById(int $id): ?object
    {
        $row = DB::table('hris.leave_cancellations as lc')
            ->join('tdh_user.users as u', 'lc.user_id', '=', 'u.id')
            ->where('lc.id', $id)
            ->select('lc.id', 'lc.reason', 'lc.status', 'lc.created_at',
                     'u.fname', 'u.mname', 'u.lname')
            ->first();

        if (!$row) return null;

        $row->name = trim($row->fname . ' ' . ($row->mname ? $row->mname . ' ' : '') . $row->lname);

        $row->dates = DB::table('hris.leave_cancellation_dates')
            ->where('leave_cancellation_id', $id)
            ->orderBy('date_applied')
            ->get(['date_applied', 'hours']);

        $approvals = DB::table('hris.leave_cancellation_approvals')
            ->where('leave_cancellation_id', $id)
            ->get(['role', 'status', 'remarks', 'approved_at'])
            ->keyBy('role');

        $row->approvals = [
            'sh' => $approvals['SH'] ?? (object)['status' => 'pending', 'remarks' => null],
            'dh' => $approvals['DH'] ?? (object)['status' => 'pending', 'remarks' => null],
            'hr' => $approvals['HR'] ?? (object)['status' => 'pending', 'remarks' => null],
        ];

        $row->can_act = $this->resolveCurrentRole($id) !== null;

        return $row;
    }

    // -----------------------------------------------------------------------
    // Approver: list cancellations pending this user's action
    // -----------------------------------------------------------------------

    public function getCancellationsForApproval(?string $search = null, string $filter = 'pending'): \Illuminate\Support\Collection
    {
        $user           = Auth::user();
        $isAdmin        = strtoupper((string) session('access_rights')) === 'ADMIN';
        $isSectionHead  = (bool) session('is_section_head');
        $isDivisionHead = (bool) session('is_division_head');
        $canActAsHr     = $this->canActAsHr();

        $base = DB::table('hris.leave_cancellations as lc')
            ->join('tdh_user.users as u', 'lc.user_id', '=', 'u.id')
            ->select(
                'lc.id', 'lc.reason', 'lc.status', 'lc.created_at',
                'u.fname', 'u.mname', 'u.lname'
            );

        if ($filter === 'pending') {
            $base->join('hris.leave_cancellation_approvals as lca', 'lc.id', '=', 'lca.leave_cancellation_id')
                 ->where('lca.status', 'pending')
                 ->whereRaw('lca.order_no = (SELECT MIN(p.order_no) FROM hris.leave_cancellation_approvals p WHERE p.leave_cancellation_id = lc.id AND p.status = ?)', ['pending'])
                 ->where(function ($q) use ($isAdmin, $isSectionHead, $isDivisionHead, $canActAsHr, $user) {
                     if ($isAdmin || ($isSectionHead && !empty($user->section))) {
                         $q->orWhere(function ($sq) use ($isAdmin, $isSectionHead, $user) {
                             $sq->where('lca.role', 'SH');
                             if (!$isAdmin) $sq->where('lca.approver_id', $user->section);
                         });
                     }
                     if ($isDivisionHead && !empty($user->division)) {
                         $q->orWhere(function ($sq) use ($user) {
                             $sq->where('lca.role', 'DH')->where('lca.approver_id', $user->division);
                         });
                     }
                     if ($canActAsHr) {
                         $q->orWhere(function ($sq) {
                             $sq->where('lca.role', 'HR')->where('lca.approver_id', 15);
                         });
                     }
                 });
        } elseif ($filter === 'approved_by_stage') {
            if ($isAdmin) {
                $base->whereExists(fn($sq) => $sq->from('hris.leave_cancellation_approvals as lca2')
                    ->whereColumn('lca2.leave_cancellation_id', 'lc.id')
                    ->where('lca2.role', 'SH')->where('lca2.status', 'approved'));
            } else {
                $base->where(function ($q) use ($isSectionHead, $isDivisionHead, $canActAsHr, $user) {
                    if ($isSectionHead && !empty($user->section)) {
                        $q->orWhereExists(fn($sq) => $sq->from('hris.leave_cancellation_approvals as lca2')
                            ->whereColumn('lca2.leave_cancellation_id', 'lc.id')
                            ->where('lca2.role', 'SH')->where('lca2.approver_id', $user->section)->where('lca2.status', 'approved'));
                    }
                    if ($isDivisionHead && !empty($user->division)) {
                        $q->orWhereExists(fn($sq) => $sq->from('hris.leave_cancellation_approvals as lca2')
                            ->whereColumn('lca2.leave_cancellation_id', 'lc.id')
                            ->where('lca2.role', 'DH')->where('lca2.approver_id', $user->division)->where('lca2.status', 'approved'));
                    }
                    if ($canActAsHr) {
                        $q->orWhereExists(fn($sq) => $sq->from('hris.leave_cancellation_approvals as lca2')
                            ->whereColumn('lca2.leave_cancellation_id', 'lc.id')
                            ->where('lca2.role', 'HR')->where('lca2.approver_id', 15)->where('lca2.status', 'approved'));
                    }
                });
            }
        } else { // approved_all
            $base->where('lc.status', 'Approved');
            if (!$isAdmin) {
                $base->where(function ($q) use ($isSectionHead, $isDivisionHead, $canActAsHr, $user) {
                    if ($isSectionHead && !empty($user->section)) {
                        $q->orWhereExists(fn($sq) => $sq->from('hris.leave_cancellation_approvals as lca2')
                            ->whereColumn('lca2.leave_cancellation_id', 'lc.id')
                            ->where('lca2.role', 'SH')->where('lca2.approver_id', $user->section));
                    }
                    if ($isDivisionHead && !empty($user->division)) {
                        $q->orWhereExists(fn($sq) => $sq->from('hris.leave_cancellation_approvals as lca2')
                            ->whereColumn('lca2.leave_cancellation_id', 'lc.id')
                            ->where('lca2.role', 'DH')->where('lca2.approver_id', $user->division));
                    }
                    if ($canActAsHr) {
                        $q->orWhereExists(fn($sq) => $sq->from('hris.leave_cancellation_approvals as lca2')
                            ->whereColumn('lca2.leave_cancellation_id', 'lc.id')
                            ->where('lca2.role', 'HR')->where('lca2.approver_id', 15));
                    }
                });
            }
        }

        if (!empty($search)) {
            $base->where(function ($q) use ($search) {
                $q->where('u.fname', 'like', "%{$search}%")
                  ->orWhere('u.lname', 'like', "%{$search}%");
            });
        }

        return $base->get()->map(function ($row) {
            $row->dates = DB::table('hris.leave_cancellation_dates')
                ->where('leave_cancellation_id', $row->id)
                ->orderBy('date_applied')
                ->pluck('date_applied');
            return $row;
        });
    }

    // -----------------------------------------------------------------------
    // Approver: approve or reject
    // -----------------------------------------------------------------------

    public function updateApprovalStatus(int $id, string $status, ?string $remarks): void
    {
        $normalizedStatus = strtolower(trim($status));
        if (!in_array($normalizedStatus, ['approved', 'rejected'], true)) {
            throw new \InvalidArgumentException('Invalid status.');
        }

        $role = $this->resolveCurrentRole($id);
        if (!$role) {
            throw new \RuntimeException('You are not allowed to act on this cancellation.');
        }

        DB::transaction(function () use ($id, $role, $normalizedStatus, $remarks) {
            DB::table('hris.leave_cancellation_approvals')
                ->where('leave_cancellation_id', $id)
                ->where('role', $role)
                ->update([
                    'status'      => $normalizedStatus,
                    'remarks'     => $remarks,
                    'approved_at' => Carbon::now(),
                    'updated_at'  => Carbon::now(),
                ]);

            if ($normalizedStatus === 'rejected') {
                DB::table('hris.leave_cancellations')
                    ->where('id', $id)
                    ->update(['status' => 'Rejected', 'updated_at' => Carbon::now()]);
                return;
            }

            if ($role === 'HR') {
                $userId = DB::table('hris.leave_cancellations')
                    ->where('id', $id)->value('user_id');

                DB::table('hris.leave_cancellations')
                    ->where('id', $id)
                    ->update(['status' => 'Approved', 'updated_at' => Carbon::now()]);

                $dateIds = DB::table('hris.leave_cancellation_dates')
                    ->where('leave_cancellation_id', $id)
                    ->pluck('leave_date_applied_id');

                // Restore leave credits per leave type
                $cancelledDates = DB::table('hris.leave_date_applied')
                    ->whereIn('id', $dateIds)
                    ->get(['leave_type_id', 'credits_deducted']);

                $creditsByType = $cancelledDates->groupBy('leave_type_id');
                foreach ($creditsByType as $typeId => $rows) {
                    if (!$typeId) continue;
                    DB::table('hris.leave_credits')
                        ->where('user_id', $userId)
                        ->where('leave_id', $typeId)
                        ->increment('based_credit', $rows->sum('credits_deducted'));
                }

                // Mark cancelled dates as cancelled (preserve history)
                DB::table('hris.leave_date_applied')
                    ->whereIn('id', $dateIds)
                    ->update(['status' => 'cancelled']);
            }
        });
    }

    // -----------------------------------------------------------------------
    // Admin: all cancellations
    // -----------------------------------------------------------------------

    public function getAllCancellations(?string $search = null): \Illuminate\Support\Collection
    {
        $query = DB::table('hris.leave_cancellations as lc')
            ->join('tdh_user.users as u', 'lc.user_id', '=', 'u.id')
            ->select('lc.id', 'lc.reason', 'lc.status', 'lc.created_at',
                     'u.fname', 'u.mname', 'u.lname')
            ->orderByDesc('lc.id');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('u.fname', 'like', "%{$search}%")
                  ->orWhere('u.lname', 'like', "%{$search}%");
            });
        }

        return $query->get()->map(function ($row) {
            $row->dates = DB::table('hris.leave_cancellation_dates')
                ->where('leave_cancellation_id', $row->id)
                ->orderBy('date_applied')
                ->pluck('date_applied');
            return $row;
        });
    }
}
