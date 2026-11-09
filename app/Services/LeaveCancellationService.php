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

    private OrgHierarchyService $org;
    private PermissionService $permissions;
    private array $headed = [];

    public function __construct(OrgHierarchyService $org, PermissionService $permissions)
    {
        $this->org         = $org;
        $this->permissions = $permissions;
    }

    /** Same HR rule as leave approval: HR/HRH access level or the leave.approve_hr permission. */
    private function canActAsHr(): bool
    {
        if (in_array(strtoupper((string) session('access_rights')), ['HR', 'HRH'], true)) {
            return true;
        }
        $userId = Auth::id();
        return $userId && $this->permissions->can($userId, 'leave.approve_hr');
    }

    /** Sections/divisions the current user actually heads. */
    private function headed(): array
    {
        $id = (int) Auth::id();
        if (!isset($this->headed[$id])) {
            $this->headed[$id] = [
                'sections'  => $id ? $this->org->headedSectionIds($id) : [],
                'divisions' => $id ? $this->org->headedDivisionIds($id) : [],
            ];
        }
        return $this->headed[$id];
    }

    /** SH/DH steps match the sections/divisions the user actually heads. */
    private function canActOnStep(object $step, $user): bool
    {
        $access = strtoupper((string) session('access_rights'));
        $headed = $this->headed();

        if ($step->role === 'SH') {
            return $access === 'ADMIN'
                || in_array((int) $step->approver_id, $headed['sections'], true);
        }
        if ($step->role === 'DH') {
            return in_array((int) $step->approver_id, $headed['divisions'], true);
        }
        if ($step->role === 'HR') {
            return $this->canActAsHr();
        }

        return false;
    }

    /** Owner, ADMIN, HR, or a head named in this cancellation's approval chain. */
    public function canViewCancellation(int $id): bool
    {
        $ownerId = DB::table('hris.leave_cancellations')->where('id', $id)->value('user_id');
        if ($ownerId === null) {
            return false;
        }
        if ((int) $ownerId === (int) Auth::id()
            || strtoupper((string) session('access_rights')) === 'ADMIN'
            || $this->canActAsHr()) {
            return true;
        }

        $headed = $this->headed();
        return DB::table('hris.leave_cancellation_approvals')
            ->where('leave_cancellation_id', $id)
            ->where(function ($q) use ($headed) {
                $q->where(fn($s) => $s->where('role', 'SH')->whereIn('approver_id', $headed['sections'] ?: [0]))
                  ->orWhere(fn($d) => $d->where('role', 'DH')->whereIn('approver_id', $headed['divisions'] ?: [0]));
            })
            ->exists();
    }

    private function resolveCurrentRole(int $cancellationId): ?string
    {
        // A rejected (or already approved) cancellation is closed: nobody acts on it again.
        $row = DB::table('hris.leave_cancellations')->where('id', $cancellationId)->first(['status', 'user_id']);
        if (!$row || strtolower((string) $row->status) !== 'pending') return null;

        // Nobody acts on their own cancellation at any step.
        if ((int) $row->user_id === (int) Auth::id()) return null;

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

    /**
     * Dates the user may still cancel: on one of their Approved leaves, still active,
     * not disapproved by the Division Head (its credit was already given back), and not
     * already in a Pending or Approved cancellation.
     */
    private function cancellableDatesQuery(int $userId): \Illuminate\Database\Query\Builder
    {
        return DB::table('hris.leave_date_applied as lda')
            ->join('hris.leave_applications as la', 'lda.leave_application_id', '=', 'la.id')
            ->where('la.user_id', $userId)
            ->where('la.status', 'Approved')
            ->where('lda.status', 'active')
            ->where('lda.final_disapproved', 0)
            ->whereNotExists(function ($sub) {
                $sub->from('hris.leave_cancellation_dates as lcd')
                    ->join('hris.leave_cancellations as lc', 'lc.id', '=', 'lcd.leave_cancellation_id')
                    ->whereColumn('lcd.leave_date_applied_id', 'lda.id')
                    ->whereIn('lc.status', ['Pending', 'Approved']);
            });
    }

    public function getApprovedDatesForUser(): \Illuminate\Support\Collection
    {
        return $this->cancellableDatesQuery((int) Auth::id())
            ->join('hris.leave_types as lt', 'lt.id', '=', 'lda.leave_type_id')
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

            $dateIds = array_values(array_unique(array_map('intval', $data['date_ids'])));

            // Lock this user's leave date rows so two submissions cannot both pass the check
            DB::table('hris.leave_date_applied as lda')
                ->join('hris.leave_applications as la', 'lda.leave_application_id', '=', 'la.id')
                ->where('la.user_id', $user->id)
                ->whereIn('lda.id', $dateIds)
                ->lockForUpdate()
                ->pluck('lda.id');

            // Every selected date must still be cancellable (same rule as the dropdown)
            $validIds = $this->cancellableDatesQuery((int) $user->id)
                ->whereIn('lda.id', $dateIds)
                ->pluck('lda.id');

            if ($validIds->count() !== count($dateIds)) {
                throw new \RuntimeException('One or more selected dates can no longer be cancelled (already cancelled, disapproved, or in another cancellation request).');
            }
            $data['date_ids'] = $dateIds;

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

            // Approval chain - a head's own step goes to the head above (see OrgHierarchyService)
            DB::table('hris.leave_cancellation_approvals')->insert(
                $this->org->approvalRowsFor($user, 'leave_cancellation_id', $id)
            );

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
            // A head's own cancellation has no SH (or, for the Medical Center Chief, no DH) step
            'sh' => $approvals['SH'] ?? (object)['status' => 'skipped', 'remarks' => null],
            'dh' => $approvals['DH'] ?? (object)['status' => 'skipped', 'remarks' => null],
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
        $isAdmin    = strtoupper((string) session('access_rights')) === 'ADMIN';
        $canActAsHr = $this->canActAsHr();
        $headed     = $this->headed();
        $sections   = $headed['sections'];
        $divisions  = $headed['divisions'];

        // Restricts $q (approval alias $a) to this user's steps; always-false when the user
        // has no approving role, so an empty group never widens the query to everything.
        $mySteps = function ($q, string $a, bool $adminAllSh) use ($isAdmin, $canActAsHr, $sections, $divisions) {
            $any = false;
            $q->where(function ($w) use ($a, $adminAllSh, $isAdmin, $canActAsHr, $sections, $divisions, &$any) {
                if (($isAdmin && $adminAllSh) || !empty($sections)) {
                    $any = true;
                    $w->orWhere(function ($s) use ($a, $adminAllSh, $isAdmin, $sections) {
                        $s->where("$a.role", 'SH');
                        if (!($isAdmin && $adminAllSh)) {
                            $s->whereIn("$a.approver_id", $sections);
                        }
                    });
                }
                if (!empty($divisions)) {
                    $any = true;
                    $w->orWhere(fn($d) => $d->where("$a.role", 'DH')->whereIn("$a.approver_id", $divisions));
                }
                if ($canActAsHr) {
                    $any = true;
                    $w->orWhere(fn($h) => $h->where("$a.role", 'HR')->where("$a.approver_id", 15));
                }
            });
            if (!$any) {
                $q->whereRaw('1 = 0');
            }
        };

        $base = DB::table('hris.leave_cancellations as lc')
            ->join('tdh_user.users as u', 'lc.user_id', '=', 'u.id')
            ->where('lc.user_id', '!=', (int) Auth::id())
            ->select(
                'lc.id', 'lc.reason', 'lc.status', 'lc.created_at',
                'u.fname', 'u.mname', 'u.lname'
            );

        if ($filter === 'pending') {
            $base->join('hris.leave_cancellation_approvals as lca', 'lc.id', '=', 'lca.leave_cancellation_id')
                 ->where('lc.status', 'Pending')
                 ->where('lca.status', 'pending')
                 ->whereRaw('lca.order_no = (SELECT MIN(p.order_no) FROM hris.leave_cancellation_approvals p WHERE p.leave_cancellation_id = lc.id AND p.status = ?)', ['pending']);
            $mySteps($base, 'lca', true);
        } elseif ($filter === 'approved_by_stage') {
            $base->whereExists(function ($sq) use ($mySteps) {
                $sq->from('hris.leave_cancellation_approvals as lca2')
                   ->whereColumn('lca2.leave_cancellation_id', 'lc.id')
                   ->where('lca2.status', 'approved');
                $mySteps($sq, 'lca2', true);
            });
        } else { // approved_all
            $base->where('lc.status', 'Approved');
            if (!$isAdmin) {
                $base->whereExists(function ($sq) use ($mySteps) {
                    $sq->from('hris.leave_cancellation_approvals as lca2')
                       ->whereColumn('lca2.leave_cancellation_id', 'lc.id');
                    $mySteps($sq, 'lca2', false);
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
            // Re-check under lock: the cancellation must still be Pending
            $current = DB::table('hris.leave_cancellations')->where('id', $id)->lockForUpdate()->value('status');
            if (strtolower((string) $current) !== 'pending') {
                throw new \RuntimeException('This cancellation is already ' . strtolower((string) $current) . ' and can no longer be acted on.');
            }

            // HR's final approval: the leave must still be Approved (HR may have undone its
            // approval since the cancellation was filed). Checked before anything is written.
            if ($role === 'HR' && $normalizedStatus === 'approved') {
                $notApproved = DB::table('hris.leave_cancellation_dates as lcd')
                    ->join('hris.leave_date_applied as lda', 'lda.id', '=', 'lcd.leave_date_applied_id')
                    ->join('hris.leave_applications as la', 'la.id', '=', 'lda.leave_application_id')
                    ->where('lcd.leave_cancellation_id', $id)
                    ->where('la.status', '!=', 'Approved')
                    ->exists();
                if ($notApproved) {
                    throw new \RuntimeException('The leave for these dates is no longer approved. Re-approve the leave first, or reject this cancellation.');
                }
            }

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

                // Only dates still active and not DH-disapproved hold a deduction; anything
                // else already had its credit given back and must not be refunded again.
                $dateIds = DB::table('hris.leave_date_applied')
                    ->whereIn('id', $dateIds)
                    ->where('status', 'active')
                    ->where('final_disapproved', 0)
                    ->lockForUpdate()
                    ->pluck('id');

                // Restore leave credits per leave type
                $cancelledDates = DB::table('hris.leave_date_applied')
                    ->whereIn('id', $dateIds)
                    ->get(LeaveDateCharges::COLUMNS);

                // Each part goes back to the balance it came from (SL -> VL fallback included).
                foreach (LeaveDateCharges::byType($cancelledDates) as $typeId => $amount) {
                    DB::table('hris.leave_credits')
                        ->where('user_id', $userId)
                        ->where('leave_id', $typeId)
                        ->increment('based_credit', $amount);
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
