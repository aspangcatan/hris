<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveMonetizationService
{
    const CREDIT_RATE_PER_DAY = 1.25;

    public function getMonetizableTypes(int $userId): array
    {
        $types = DB::table('leave_types')
            ->where('status', 'active')
            ->where('monetizable', 1)
            ->get(['id', 'description', 'code']);

        $result = [];
        foreach ($types as $type) {
            $credit = DB::table('leave_credits')
                ->where('user_id', $userId)
                ->where('leave_id', $type->id)
                ->first(['based_credit', 'used_credit']);

            $balance      = $credit ? ($credit->based_credit - $credit->used_credit) : 0;
            $balanceDays  = $balance > 0 ? round($balance / self::CREDIT_RATE_PER_DAY, 3) : 0;

            if ($balanceDays <= 0) continue;

            $result[] = [
                'id'           => $type->id,
                'description'  => $type->description,
                'code'         => $type->code,
                'balance'      => number_format($balance, 3),
                'balance_days' => number_format($balanceDays, 3),
            ];
        }

        return $result;
    }

    public function storeMany(array $items): array
    {
        return DB::transaction(function () use ($items) {
            $ids = [];
            foreach ($items as $item) {
                $ids[] = $this->store($item);
            }
            return $ids;
        });
    }

    public function store(array $data): int
    {
        return DB::transaction(function () use ($data) {
            $userId      = Auth::id();
            $leaveTypeId = (int) $data['leave_type_id'];
            $days        = round((float) $data['days_requested'], 3);

            if ($days <= 0) {
                throw new \Exception('Days requested must be greater than zero.');
            }

            // Validate leave type is monetizable
            $leaveType = DB::table('leave_types')
                ->where('id', $leaveTypeId)
                ->where('monetizable', 1)
                ->where('status', 'active')
                ->first(['id', 'description', 'min_monetize_days', 'min_retained_days']);

            if (!$leaveType) {
                throw new \Exception('Selected leave type is not eligible for monetization.');
            }

            // Validate sufficient balance. The row is locked so two submissions at once (double-click)
            // are handled one after the other - the second sees the first one's deduction.
            $credit    = DB::table('leave_credits')
                ->where('user_id', $userId)
                ->where('leave_id', $leaveTypeId)
                ->lockForUpdate()
                ->first(['based_credit', 'used_credit']);
            $available = $credit ? round($credit->based_credit - $credit->used_credit, 3) : null;

            if ($available === null) {
                throw new \Exception("No leave credits found for {$leaveType->description}.");
            }

            $creditsNeeded = round($days * self::CREDIT_RATE_PER_DAY, 3);
            $availableDays = round($available / self::CREDIT_RATE_PER_DAY, 3);

            if ($days > $availableDays) {
                throw new \Exception(
                    "Insufficient {$leaveType->description} balance. You have {$availableDays} days available but requested {$days} days."
                );
            }

            $minMonetizeDays = (float) ($leaveType->min_monetize_days ?? 0);
            if ($minMonetizeDays > 0 && $days < $minMonetizeDays) {
                throw new \Exception(
                    "{$leaveType->description} monetization requires a minimum of {$minMonetizeDays} day(s) per request."
                );
            }

            $minRetainedDays = (float) ($leaveType->min_retained_days ?? 0);
            if ($minRetainedDays > 0 && ($availableDays - $days) < $minRetainedDays) {
                throw new \Exception(
                    "{$leaveType->description} monetization must retain at least {$minRetainedDays} day(s) after this request."
                );
            }

            // Deduct credits
            DB::table('leave_credits')
                ->where('user_id', $userId)
                ->where('leave_id', $leaveTypeId)
                ->decrement('based_credit', $creditsNeeded);

            // Create monetization record
            $monetizationId = DB::table('leave_monetizations')->insertGetId([
                'user_id'          => $userId,
                'leave_type_id'    => $leaveTypeId,
                'days_requested'   => $days,
                'credits_deducted' => $creditsNeeded,
                'status'           => 'pending',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            app(LeaveLedgerService::class)->record(
                (int) $userId, (int) $leaveTypeId, LeaveLedgerService::MONETIZED, -$creditsNeeded,
                now()->toDateString(), 'leave_monetizations', (int) $monetizationId
            );

            // Build SH → DH → HR approval chain
            $user = Auth::user();
            DB::table('leave_monetization_approvals')->insert([
                ['leave_monetization_id' => $monetizationId, 'approver_id' => $user->section,  'role' => 'SH', 'order_no' => 1, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
                ['leave_monetization_id' => $monetizationId, 'approver_id' => $user->division, 'role' => 'DH', 'order_no' => 2, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
                ['leave_monetization_id' => $monetizationId, 'approver_id' => 15,              'role' => 'HR', 'order_no' => 3, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
            ]);

            return $monetizationId;
        });
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            // Locked: a double-clicked delete must not give the credits back twice.
            $record = DB::table('leave_monetizations')
                ->where('id', $id)
                ->where('user_id', Auth::id())
                ->lockForUpdate()
                ->first();

            if (!$record) {
                throw new \Exception('Monetization request not found.');
            }

            if (strtolower($record->status) === 'approved') {
                throw new \Exception('Cannot delete an approved monetization request.');
            }

            // Restore credits only if not already restored on rejection
            if (strtolower($record->status) !== 'rejected') {
                DB::table('leave_credits')
                    ->where('user_id', $record->user_id)
                    ->where('leave_id', $record->leave_type_id)
                    ->increment('based_credit', $record->credits_deducted);
                app(LeaveLedgerService::class)->record(
                    (int) $record->user_id, (int) $record->leave_type_id, LeaveLedgerService::RESTORED, (float) $record->credits_deducted,
                    now()->toDateString(), 'leave_monetizations', (int) $id
                );
            }

            DB::table('leave_monetization_approvals')->where('leave_monetization_id', $id)->delete();
            DB::table('leave_monetizations')->where('id', $id)->delete();
        });
    }

    public function getMyMonetizations(int $page = 1, int $perPage = 10): array
    {
        $userId = Auth::id();

        $rows = DB::table('leave_monetizations as lm')
            ->join('leave_types as lt', 'lt.id', '=', 'lm.leave_type_id')
            ->where('lm.user_id', $userId)
            ->orderBy('lm.created_at', 'desc')
            ->get([
                'lm.id',
                'lm.days_requested',
                'lm.credits_deducted',
                'lm.status',
                'lm.created_at',
                'lt.description as leave_type',
                'lt.code as leave_code',
            ]);

        $total    = $rows->count();
        $items    = $rows->forPage($page, $perPage)->values()->map(function ($row) {
            return [
                'id'           => $row->id,
                'leave_type'   => $row->leave_type,
                'leave_code'   => $row->leave_code,
                'days_requested' => number_format($row->days_requested, 3),
                'status'       => ucfirst($row->status),
                'is_deletable' => strtolower($row->status) !== 'approved',
                'filed_date'   => Carbon::parse($row->created_at)->format('m/d/Y'),
            ];
        });

        return [
            'results'    => $items,
            'pagination' => [
                'current_page'   => $page,
                'last_page'      => max(1, (int) ceil($total / $perPage)),
                'per_page'       => $perPage,
                'total'          => $total,
                'from'           => $total > 0 ? ($page - 1) * $perPage + 1 : null,
                'to'             => $total > 0 ? min($page * $perPage, $total) : null,
                'has_more_pages' => $page < max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    public function getForApproval(?string $searchEmployee = null, string $filter = 'pending'): \Illuminate\Support\Collection
    {
        $user        = Auth::user();
        $accessRight = strtoupper((string) session('access_rights'));
        $isSH        = $accessRight === 'ADMIN' || ((bool) session('is_section_head') && !empty($user->section));
        $isDH        = (bool) session('is_division_head') && !empty($user->division);
        $isHR        = in_array($accessRight, ['HR', 'HRH'], true);

        $query = DB::table('leave_monetizations as lm')
            ->join('leave_types as lt', 'lt.id', '=', 'lm.leave_type_id')
            ->join('tdh_user.users as u', 'u.id', '=', 'lm.user_id')
            ->join('leave_monetization_approvals as lma', function ($join) {
                $join->on('lma.leave_monetization_id', '=', 'lm.id');
            });

        if ($filter === 'pending') {
            // A rejected request is finished - later steps are not asked to act on it.
            $query->where('lm.status', 'pending')
                ->where('lma.status', 'pending')
                ->whereRaw('lma.order_no = (
                    SELECT MIN(order_no) FROM leave_monetization_approvals
                    WHERE leave_monetization_id = lm.id AND status = ?
                )', ['pending']);

            if ($isSH && !$isHR) {
                $query->where('lma.role', 'SH')->where('lma.approver_id', $user->section);
            } elseif ($isDH && !$isHR) {
                $query->where('lma.role', 'DH')->where('lma.approver_id', $user->division);
            } elseif ($isHR) {
                $query->where('lma.role', 'HR');
            } else {
                return collect();
            }
        } elseif ($filter === 'approved_by_stage') {
            $query->where('lma.status', 'approved');

            if ($isSH && !$isHR) {
                $query->where('lma.role', 'SH')->where('lma.approver_id', $user->section);
            } elseif ($isDH && !$isHR) {
                $query->where('lma.role', 'DH')->where('lma.approver_id', $user->division);
            } elseif ($isHR) {
                $query->where('lma.role', 'HR');
            } else {
                return collect();
            }
        } elseif ($filter === 'approved_all') {
            $query->where('lm.status', 'approved');
            if (!$isSH && !$isDH && !$isHR) return collect();
        }

        if ($searchEmployee) {
            $query->where(function ($q) use ($searchEmployee) {
                $q->where('u.fname', 'like', "%{$searchEmployee}%")
                  ->orWhere('u.lname', 'like', "%{$searchEmployee}%");
            });
        }

        return $query->orderBy('lm.created_at', 'desc')
            ->distinct()
            ->get([
                'lm.id',
                'lm.days_requested',
                'lm.status as monetization_status',
                'lm.created_at',
                'lt.description as leave_type',
                'lt.code as leave_code',
                'u.fname',
                'u.mname',
                'u.lname',
                'lma.role as approval_role',
                'lma.status as approval_status',
            ])
            ->map(function ($row) {
                return [
                    'id'             => $row->id,
                    'employee'       => trim("{$row->lname}, {$row->fname}" . ($row->mname ? ' ' . strtoupper(substr($row->mname, 0, 1)) . '.' : '')),
                    'leave_type'     => $row->leave_type,
                    'leave_code'     => $row->leave_code,
                    'days_requested' => number_format($row->days_requested, 3),
                    'status'         => ucfirst($row->monetization_status),
                    'filed_date'     => Carbon::parse($row->created_at)->format('m/d/Y'),
                    'approval_role'   => $row->approval_role,
                    'approval_status' => $row->approval_status,
                ];
            });
    }

    public function updateApprovalStatus(int $monetizationId, ?string $remarks, string $status): void
    {
        $record = DB::table('leave_monetizations')->where('id', $monetizationId)->first();
        if (!$record) {
            throw new \Exception('Leave monetization request not found.');
        }

        $normalizedStatus = strtolower(trim($status));
        if (!in_array($normalizedStatus, ['approved', 'rejected'], true)) {
            throw new \InvalidArgumentException('Invalid approval status.');
        }

        $user        = Auth::user();
        $accessRight = strtoupper((string) session('access_rights'));

        // Resolve current approver role
        $pendingApproval = DB::table('leave_monetization_approvals')
            ->where('leave_monetization_id', $monetizationId)
            ->where('status', 'pending')
            ->orderBy('order_no')
            ->first(['role', 'approver_id', 'order_no']);

        if (!$pendingApproval) {
            throw new \RuntimeException('No pending approval step found.');
        }

        $canApprove = match ($pendingApproval->role) {
            'SH' => $accessRight === 'ADMIN' || ((bool) session('is_section_head') && (int) $pendingApproval->approver_id === (int) $user->section),
            'DH' => (bool) session('is_division_head') && (int) $pendingApproval->approver_id === (int) $user->division,
            'HR' => in_array($accessRight, ['HR', 'HRH'], true),
            default => false,
        };

        if (!$canApprove) {
            throw new \RuntimeException('You are not authorized to approve this request.');
        }

        DB::transaction(function () use ($monetizationId, $pendingApproval, $normalizedStatus, $remarks, $record) {
            // Lock and re-check. Only a request that is still pending can be acted on: once
            // rejected, its credits are already back, so a later step (or a double-click)
            // must not refund them again or approve it.
            $current = DB::table('leave_monetizations')->where('id', $monetizationId)->lockForUpdate()->value('status');
            if (strtolower((string) $current) !== 'pending') {
                throw new \RuntimeException('This monetization request is already ' . strtolower((string) $current) . ' and can no longer be acted on.');
            }
            $stepStatus = DB::table('leave_monetization_approvals')
                ->where('leave_monetization_id', $monetizationId)
                ->where('role', $pendingApproval->role)
                ->value('status');
            if (strtolower((string) $stepStatus) !== 'pending') {
                throw new \RuntimeException('This step has already been acted on. Please refresh the page.');
            }

            DB::table('leave_monetization_approvals')
                ->where('leave_monetization_id', $monetizationId)
                ->where('role', $pendingApproval->role)
                ->update([
                    'status'      => $normalizedStatus,
                    'remarks'     => $remarks,
                    'approved_at' => Carbon::now(),
                    'updated_at'  => Carbon::now(),
                ]);

            if ($normalizedStatus === 'rejected') {
                // Restore credits
                DB::table('leave_credits')
                    ->where('user_id', $record->user_id)
                    ->where('leave_id', $record->leave_type_id)
                    ->increment('based_credit', $record->credits_deducted);
                app(LeaveLedgerService::class)->record(
                    (int) $record->user_id, (int) $record->leave_type_id, LeaveLedgerService::RESTORED, (float) $record->credits_deducted,
                    now()->toDateString(), 'leave_monetizations', (int) $monetizationId
                );

                DB::table('leave_monetizations')
                    ->where('id', $monetizationId)
                    ->update(['status' => 'rejected', 'updated_at' => Carbon::now()]);
                return;
            }

            // If HR approved — mark fully approved
            if ($pendingApproval->role === 'HR') {
                DB::table('leave_monetizations')
                    ->where('id', $monetizationId)
                    ->update(['status' => 'approved', 'updated_at' => Carbon::now()]);
            }
        });
    }
}
