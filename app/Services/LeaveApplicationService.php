<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveApplicationService
{
    protected MinuteConversionService $minuteConversionService;

    public function __construct(MinuteConversionService $minuteConversionService)
    {
        $this->minuteConversionService = $minuteConversionService;
    }

    public function updateStatus(int $leave_id, string $status)
    {
        return DB::table('hris.leave_applications')
            ->where('id', '=', $leave_id)
            ->update([
                'status'     => $status,
                'updated_at' => Carbon::now()
            ]);
    }

    /**
     * Keeps the HR_COMPLIANCE approval step in sync with whether the
     * application currently has any attachments - see
     * docs/superpowers/specs/2026-07-21-leave-hr-compliance-step-design.md.
     * Called after every attachment add/remove and once right after
     * filing. Never removes an already-approved compliance step, even if
     * every attachment is later deleted - no retroactive undo.
     */
    public function syncComplianceStep(int $appId): void
    {
        DB::transaction(function () use ($appId) {
            $hasAttachments = DB::table('leave_application_attachments')
                ->where('leave_application_id', $appId)
                ->exists();

            $compliance = DB::table('leave_application_approvals')
                ->where('leave_application_id', $appId)
                ->where('role', 'HR_COMPLIANCE')
                ->first(['id', 'status']);

            if ($hasAttachments && !$compliance) {
                DB::table('leave_application_approvals')
                    ->where('leave_application_id', $appId)
                    ->increment('order_no');

                DB::table('leave_application_approvals')->insert([
                    'leave_application_id' => $appId,
                    'approver_id'          => 15,
                    'role'                 => 'HR_COMPLIANCE',
                    'order_no'             => 1,
                    'status'               => 'pending',
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
            } elseif (!$hasAttachments && $compliance && strtolower($compliance->status) === 'pending') {
                DB::table('leave_application_approvals')
                    ->where('id', $compliance->id)
                    ->delete();

                DB::table('leave_application_approvals')
                    ->where('leave_application_id', $appId)
                    ->where('order_no', '>', 1)
                    ->decrement('order_no');
            }
        });
    }

    /**
     * Earliest date a leave with $daysPrior advance notice can be filed for.
     * Advance notice is counted in working days: weekends are skipped.
     */
    public function earliestLeaveDate(int $daysPrior, ?Carbon $from = null): Carbon
    {
        $date = ($from ?? Carbon::today())->copy()->startOfDay();

        while ($daysPrior > 0) {
            $date->addDay();
            if ($date->isWeekday()) {
                $daysPrior--;
            }
        }

        return $date;
    }

    /**
     * Rejects weekend dates and dates earlier than each leave type's
     * days_prior advance notice (counted in working days).
     *
     * @param iterable $leaveTypes  [leave_type_id => {description, days_prior}]
     * @param iterable $datesByType [leave_type_id => [{date_applied, ...}]]
     */
    public function assertValidLeaveDates($leaveTypes, $datesByType, ?Carbon $today = null): void
    {
        foreach ($leaveTypes as $leaveId => $type) {
            // A negative days_prior (e.g. Sick Leave = -1) means the type can be filed
            // for past dates, so it has no earliest date.
            $daysPrior = (int) ($type->days_prior ?? 0);
            $earliest  = $daysPrior > 0
                ? $this->earliestLeaveDate($daysPrior, $today)->toDateString()
                : null;

            foreach (($datesByType[$leaveId] ?? []) as $date) {
                $dateApplied = $date['date_applied'];

                if (Carbon::parse($dateApplied)->isWeekend()) {
                    throw new \Exception("{$type->description}: {$dateApplied} falls on a weekend. Weekends cannot be selected.");
                }

                if ($earliest !== null && $dateApplied < $earliest) {
                    throw new \Exception(
                        "{$type->description} requires at least {$daysPrior} working day(s) advance notice. Earliest allowed date is {$earliest}."
                    );
                }
            }
        }
    }

    public function insertLeaveApplication(array $data)
    {
        return DB::transaction(function () use ($data) {
            $isLwop   = (bool) ($data['is_lwop'] ?? false);
            $dates    = $data['dates']; // each: { date_applied, hours, leave_type_id }
            $leaveIds = collect($dates)->pluck('leave_type_id')->unique()->filter()->values()->toArray();
            $n        = count($leaveIds);

            if ($n === 0) {
                throw new \Exception('No leave type selected for any date.');
            }

            // Group dates by leave_type for per-type credit calculation
            $datesByType = collect($dates)->groupBy('leave_type_id');

            $leaveTypes = DB::table('leave_types')
                ->whereIn('id', $leaveIds)
                ->get(['id', 'description', 'days_prior'])
                ->keyBy('id');

            $this->assertValidLeaveDates($leaveTypes, $datesByType);

            // Validate credit balance per type (credits proportional to actual days per type)
            // Skipped entirely for LWOP — an LWOP filing never checks or deducts credits.
            if (!$isLwop) {
                foreach ($leaveIds as $leaveId) {
                    $typeHours   = collect($datesByType[$leaveId] ?? [])->sum('hours');
                    $typeCredits = $this->minuteConversionService->computeDeduction($typeHours * 60);

                    $credit    = DB::table('leave_credits')
                        ->where('user_id', Auth::id())
                        ->where('leave_id', $leaveId)
                        ->first(['based_credit', 'used_credit']);
                    $available = $credit ? ($credit->based_credit - $credit->used_credit) : null;

                    $desc = $leaveTypes[$leaveId]->description;

                    if ($available === null) {
                        throw new \Exception("No leave credits found for {$desc}.");
                    }

                    if ($available < $typeCredits) {
                        throw new \Exception("Insufficient {$desc} credits. Need {$typeCredits}, you have {$available}.");
                    }
                }
            }

            // ONE leave_application record
            $appId = DB::table('leave_applications')->insertGetId([
                'user_id'             => Auth::id(),
                'commutation'         => $data['commutation'] ?? null,
                'attachment'          => $data['attachment'] ?? null,
                'applicant_signature' => $data['applicant_signature'],
                'status'              => 'pending',
                'is_lwop'             => $isLwop,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            // Leave details (without leave_id and credits_deducted — now per date)
            $detailsMap = $data['details_map'] ?? [];
            foreach ($leaveIds as $leaveId) {
                $typeDetail = $detailsMap[(string) $leaveId] ?? [];
                if (empty($typeDetail['detail'])) continue;

                DB::table('leave_details')->insert([
                    'leave_application_id' => $appId,
                    'detail'               => $typeDetail['detail'],
                    'extra'                => $typeDetail['extra'] ?? null,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
            }

            // Deduct credits per leave type — skipped entirely for LWOP.
            if (!$isLwop) {
                foreach ($leaveIds as $leaveId) {
                    $typeHours   = collect($datesByType[$leaveId] ?? [])->sum('hours');
                    $typeCredits = $this->minuteConversionService->computeDeduction($typeHours * 60);

                    DB::table('leave_credits')
                        ->where('user_id', Auth::id())
                        ->where('leave_id', $leaveId)
                        ->decrement('based_credit', $typeCredits);
                }
            }

            // Insert dates with leave_type_id, status, and per-date credits_deducted.
            // credits_deducted is always 0 for LWOP — this also makes the downstream
            // approval-rejection credit-restore logic a natural no-op for LWOP rows,
            // since it restores by summing credits_deducted.
            DB::table('leave_date_applied')->insert(
                collect($dates)->map(fn($d) => [
                    'leave_application_id' => $appId,
                    'leave_type_id'        => $d['leave_type_id'],
                    'date_applied'         => $d['date_applied'],
                    'hours'                => $d['hours'],
                    'status'               => 'active',
                    'credits_deducted'     => $isLwop ? 0 : $this->minuteConversionService->computeDeduction($d['hours'] * 60),
                ])->toArray()
            );

            // Approval chain
            DB::table('leave_application_approvals')->insert([
                ['leave_application_id' => $appId, 'approver_id' => Auth::user()->section,  'role' => 'SH', 'order_no' => 1, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
                ['leave_application_id' => $appId, 'approver_id' => Auth::user()->division, 'role' => 'DH', 'order_no' => 2, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
                ['leave_application_id' => $appId, 'approver_id' => 15,                     'role' => 'HR', 'order_no' => 3, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
            ]);

            return ['app_id' => $appId];
        });
    }

    public function updateLeaveApplication(int $appId, array $data): void
    {
        DB::transaction(function () use ($appId, $data) {
            $isLwop = (bool) ($data['is_lwop'] ?? false);

            $app = DB::table('leave_applications')
                ->where('id', $appId)
                ->where('user_id', Auth::id())
                ->first(['id', 'status']);

            if (!$app) {
                throw new \Exception('Leave application not found.');
            }

            $shApproved = DB::table('leave_application_approvals')
                ->where('leave_application_id', $appId)
                ->where('role', 'SH')
                ->whereRaw('LOWER(status) = ?', ['approved'])
                ->exists();

            if ($shApproved) {
                throw new \Exception('Cannot edit: this application has already been forwarded for approval.');
            }

            // Restore credits from existing dates (grouped by leave_type_id).
            // For an LWOP application every existing credits_deducted is already 0,
            // so this naturally restores nothing.
            $oldDates = DB::table('hris.leave_date_applied')
                ->where('leave_application_id', $appId)
                ->get(['leave_type_id', 'credits_deducted']);

            $oldCreditsByType = $oldDates->groupBy('leave_type_id');
            foreach ($oldCreditsByType as $typeId => $rows) {
                if (!$typeId) continue;
                DB::table('leave_credits')
                    ->where('user_id', Auth::id())
                    ->where('leave_id', $typeId)
                    ->increment('based_credit', $rows->sum('credits_deducted'));
            }

            DB::table('leave_details')->where('leave_application_id', $appId)->delete();
            DB::table('leave_date_applied')->where('leave_application_id', $appId)->delete();

            // Recalculate with new data
            $dates    = $data['dates'];
            $leaveIds = collect($dates)->pluck('leave_type_id')->unique()->filter()->values()->toArray();
            $n        = count($leaveIds);

            if ($n === 0) {
                throw new \Exception('No leave type selected for any date.');
            }

            $datesByType = collect($dates)->groupBy('leave_type_id');

            $leaveTypes = DB::table('leave_types')
                ->whereIn('id', $leaveIds)
                ->get(['id', 'description', 'days_prior'])
                ->keyBy('id');

            $this->assertValidLeaveDates($leaveTypes, $datesByType);

            if (!$isLwop) {
                foreach ($leaveIds as $leaveId) {
                    $typeHours   = collect($datesByType[$leaveId] ?? [])->sum('hours');
                    $typeCredits = $this->minuteConversionService->computeDeduction($typeHours * 60);

                    $credit    = DB::table('leave_credits')
                        ->where('user_id', Auth::id())
                        ->where('leave_id', $leaveId)
                        ->first(['based_credit', 'used_credit']);
                    $available = $credit ? ($credit->based_credit - $credit->used_credit) : null;

                    $desc = $leaveTypes[$leaveId]->description;

                    if ($available === null) {
                        throw new \Exception("No leave credits found for {$desc}.");
                    }

                    if ($available < $typeCredits) {
                        throw new \Exception("Insufficient {$desc} credits. Need {$typeCredits}, you have {$available}.");
                    }
                }
            }

            // Insert new leave_details (without leave_id)
            $detailsMap = $data['details_map'] ?? [];
            foreach ($leaveIds as $leaveId) {
                $typeDetail = $detailsMap[(string) $leaveId] ?? [];
                if (empty($typeDetail['detail'])) continue;

                DB::table('leave_details')->insert([
                    'leave_application_id' => $appId,
                    'detail'               => $typeDetail['detail'],
                    'extra'                => $typeDetail['extra'] ?? null,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
            }

            // Deduct credits per type — skipped entirely for LWOP.
            if (!$isLwop) {
                foreach ($leaveIds as $leaveId) {
                    $typeHours   = collect($datesByType[$leaveId] ?? [])->sum('hours');
                    $typeCredits = $this->minuteConversionService->computeDeduction($typeHours * 60);

                    DB::table('leave_credits')
                        ->where('user_id', Auth::id())
                        ->where('leave_id', $leaveId)
                        ->decrement('based_credit', $typeCredits);
                }
            }

            // Insert new dates
            DB::table('leave_date_applied')->insert(
                collect($dates)->map(fn($d) => [
                    'leave_application_id' => $appId,
                    'leave_type_id'        => $d['leave_type_id'],
                    'date_applied'         => $d['date_applied'],
                    'hours'                => $d['hours'],
                    'status'               => 'active',
                    'credits_deducted'     => $isLwop ? 0 : $this->minuteConversionService->computeDeduction($d['hours'] * 60),
                ])->toArray()
            );

            DB::table('leave_applications')->where('id', $appId)->update([
                'commutation'         => $data['commutation'] ?? null,
                'applicant_signature' => $data['applicant_signature'],
                'is_lwop'             => $isLwop,
                'updated_at'          => now(),
            ]);
        });
    }

    /**
     * Leave credit certification (Total Earned / Less this application / Balance) per leave type.
     * The application's credits are already deducted from the employee's balance, so the
     * current balance is the result, and Total Earned is that balance before this
     * application's deduction. A rejected application had its credits restored, so it
     * deducts nothing. Dates that were cancelled or finally disapproved are excluded too.
     * Types the employee has no leave_credits row for are skipped unless $includeMissing.
     *
     * @param  int[] $typeIds
     * @return array<int, array{leave_id: int, type: string, total_earned: float, less: float, balance: float}>
     */
    public function getCreditCertification(object $leaveApplication, array $typeIds, bool $includeMissing = false): array
    {
        $isRejected = strtolower((string) $leaveApplication->status) === 'rejected';
        $typeNames  = DB::table('hris.leave_types')->whereIn('id', $typeIds)->pluck('description', 'id');

        $certification = [];
        foreach ($typeIds as $typeId) {
            $credit = DB::table('hris.leave_credits')
                ->where('user_id', $leaveApplication->user_id)
                ->where('leave_id', $typeId)
                ->first(['based_credit', 'used_credit']);
            if (!$credit && !$includeMissing) {
                continue;
            }
            $balance = $credit ? (float) $credit->based_credit - (float) $credit->used_credit : 0.0;

            $less = $isRejected ? 0.0 : (float) DB::table('hris.leave_date_applied')
                ->where('leave_application_id', $leaveApplication->id)
                ->where('leave_type_id', $typeId)
                ->where('status', '!=', 'cancelled')
                ->where('final_disapproved', 0)
                ->sum('credits_deducted');

            $certification[$typeId] = [
                'leave_id'     => (int) $typeId,
                'type'         => $typeNames[$typeId] ?? '',
                'total_earned' => $balance + $less,
                'less'         => $less,
                'balance'      => $balance,
            ];
        }

        return $certification;
    }

    public function findById(int $id)
    {
        $application = DB::table('hris.leave_applications as la')
            ->join('tdh_user.users as u', 'la.user_id', '=', 'u.id')
            ->where('la.id', $id)
            ->select('la.id', 'la.user_id', 'la.status', 'u.fname', 'u.lname', 'u.mname', 'la.commutation', 'la.attachment', 'la.created_at', 'la.is_lwop')
            ->first();

        if (!$application) {
            return null;
        }

        // Dates with leave type info
        $dateRows = DB::table('hris.leave_date_applied as lda')
            ->leftJoin('hris.leave_types as lt', 'lda.leave_type_id', '=', 'lt.id')
            ->where('lda.leave_application_id', $id)
            ->select(
                'lda.id',
                'lda.date_applied',
                'lda.hours',
                'lda.leave_type_id',
                'lda.status',
                'lt.description as leave_type_name',
                'lda.sh_flagged_disapprove',
                'lda.sh_disapproval_remark',
                'lda.final_disapproved',
                'lda.dh_disapproval_remark'
            )
            ->orderBy('lda.date_applied', 'asc')
            ->get();

        // Unique leave types (in order of first appearance in dates)
        $uniqueTypeIds = $dateRows->pluck('leave_type_id')->unique()->filter()->values();
        $application->leave_type = $dateRows->pluck('leave_type_name')->unique()->filter()->implode(', ');

        // Raw leave_details rows (no leave_id anymore)
        $rawDetails = DB::table('hris.leave_details')
            ->where('leave_application_id', $id)
            ->get(['detail', 'extra']);

        // Full leave type map for descriptions
        $leaveTypesMap = $uniqueTypeIds->isNotEmpty()
            ? DB::table('hris.leave_types')->whereIn('id', $uniqueTypeIds)->pluck('description', 'id')
            : collect();

        // Reconstruct details_per_type: pair each unique type with a detail row by index
        $details = $uniqueTypeIds->map(function ($typeId, $index) use ($rawDetails, $leaveTypesMap) {
            $detail = $rawDetails->get($index);
            return (object) [
                'leave_id'   => $typeId,
                'leave_type' => $leaveTypesMap[$typeId] ?? '',
                'detail'     => $detail ? $detail->detail : null,
                'extra'      => $detail ? $detail->extra  : null,
            ];
        })->values();

        $application->detail  = $details->isNotEmpty() ? $details->first()->detail : null;
        $application->extra   = $details->isNotEmpty() ? $details->first()->extra  : null;
        $application->details = $details;

        $approvals = DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $id)
            ->select('role', 'status', 'remarks')
            ->get()
            ->mapWithKeys(fn($item) => [
                $item->role => ['status' => $item->status, 'remarks' => $item->remarks]
            ])->toArray();

        $application->dates    = $dateRows;
        $application->approvals = $approvals;

        return $application;
    }
}
