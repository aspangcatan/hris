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

    /**
     * Removes a leave's attachment records (table rows and the legacy `attachment` column) and
     * returns the stored file paths. Call inside the caller's transaction, then pass the result
     * to deleteAttachmentFiles() once it has committed, so a rollback never loses a file.
     *
     * @return string[] paths on the public disk
     */
    public function detachAttachments(int $appId): array
    {
        $paths = DB::table('hris.leave_application_attachments')
            ->where('leave_application_id', $appId)
            ->pluck('file_path')
            ->all();

        $legacy = DB::table('hris.leave_applications')->where('id', $appId)->value('attachment');
        if ($legacy) {
            $paths[] = $legacy;
        }

        DB::table('hris.leave_application_attachments')->where('leave_application_id', $appId)->delete();
        DB::table('hris.leave_applications')->where('id', $appId)->update(['attachment' => null]);

        return array_values(array_unique(array_filter($paths)));
    }

    /** Deletes stored attachment files (paths from detachAttachments). Missing files are ignored. */
    public function deleteAttachmentFiles(array $paths): void
    {
        foreach ($paths as $path) {
            $path = ltrim(preg_replace('#^public/#', '', str_replace('\\', '/', (string) $path)), '/');
            if ($path !== '' && strpos($path, '..') === false) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
            }
        }
    }

    /**
     * An application can be deleted by its owner only when it is not under way:
     *  - still Pending and no approver (HR compliance, SH, DH, HR) has acted yet, or
     *  - completely rejected (Rejected with no step left pending).
     * Anything in between - including an SH rejection the Division Head can still overturn -
     * stays, and an Approved leave is withdrawn through the leave cancellation form instead.
     */
    public function canDelete(int $appId): bool
    {
        $status = strtolower((string) DB::table('hris.leave_applications')->where('id', $appId)->value('status'));

        $steps = DB::table('hris.leave_application_approvals')
            ->where('leave_application_id', $appId)
            ->pluck('status')
            ->map(fn($s) => strtolower((string) $s));

        if ($status === 'pending') {
            return $steps->every(fn($s) => $s === 'pending');
        }
        if ($status === 'rejected') {
            return !$steps->contains('pending');
        }
        return false;
    }

    /**
     * A leave is completely rejected when it is Rejected and no approval step is still pending
     * (HR acknowledged a Division Head rejection, or HR rejected it). An SH rejection does not
     * count - the Division Head can still overturn it. Its attachments are then deleted.
     */
    public function purgeAttachmentsIfCompletelyRejected(int $appId): void
    {
        $paths = DB::transaction(function () use ($appId) {
            $status = DB::table('hris.leave_applications')->where('id', $appId)->lockForUpdate()->value('status');
            if (strtolower((string) $status) !== 'rejected') {
                return [];
            }
            $stillPending = DB::table('hris.leave_application_approvals')
                ->where('leave_application_id', $appId)
                ->whereRaw('LOWER(status) = ?', ['pending'])
                ->exists();
            return $stillPending ? [] : $this->detachAttachments($appId);
        });

        $this->deleteAttachmentFiles($paths);
    }

    /**
     * Section 6.B details, mirroring LEAVE_DETAIL_CONFIG in resources/views/js/leave_application.blade.php
     * (keep the two in sync). Leave types matching none of these need no details.
     *  keywords       : matched against the leave type description (lowercase)
     *  options        : allowed detail labels => whether the option needs the extra text
     *  classification : allowed classifications that must appear in the extra text, if any
     */
    public const DETAIL_RULES = [
        [
            'keywords' => ['vacation leave', 'special privilege'],
            'options'  => ['Within the Philippines' => true, 'Abroad (Specify)' => true],
        ],
        [
            'keywords'       => ['sick leave'],
            'options'        => ['In Hospital (Specify Illness)' => true, 'Out Patient (Specify Illness)' => true],
            'classification' => ['Work-Related', 'Non-Work Related'],
        ],
        [
            'keywords' => ['special leave benefits for women'],
            'options'  => ['(Specify Illness)' => true],
        ],
        [
            'keywords' => ['study leave'],
            'options'  => ["Completion of Master's Degree" => false, 'BAR/Board Examination Review' => false],
        ],
    ];

    /** Every leave type that has 6.B details must come with a valid one (LWOP included). */
    private function assertValidDetails(array $leaveIds, $leaveTypes, array $detailsMap): void
    {
        foreach ($leaveIds as $leaveId) {
            $desc = (string) $leaveTypes[$leaveId]->description;
            $rule = null;
            foreach (self::DETAIL_RULES as $r) {
                if ($this->descriptionMatches($desc, $r['keywords'])) { $rule = $r; break; }
            }
            if ($rule === null) {
                continue;
            }

            $given  = $detailsMap[(string) $leaveId] ?? [];
            $detail = trim((string) ($given['detail'] ?? ''));
            $extra  = trim((string) ($given['extra'] ?? ''));

            if (!array_key_exists($detail, $rule['options'])) {
                throw new \Exception("Please provide the details for {$desc}.");
            }

            // Text typed into the option's box (the classification is appended as "(...)").
            $typed = trim(preg_replace('/\s*\((Work-Related|Non-Work Related)\)\s*$/', '', $extra));
            if ($rule['options'][$detail] && $typed === '') {
                throw new \Exception("Please fill in the required detail text for {$desc} ({$detail}).");
            }

            if (!empty($rule['classification'])) {
                $hasClass = false;
                foreach ($rule['classification'] as $c) {
                    if (substr($extra, -strlen("({$c})")) === "({$c})") { $hasClass = true; break; }
                }
                if (!$hasClass) {
                    throw new \Exception("Please specify the classification (Work-Related/Non-Work Related) for {$desc}.");
                }
            }
        }
    }

    /** Leave types that require a supporting document (matched on description, like the form). */
    public const ATTACHMENT_REQUIRED_KEYWORDS = ['maternity leave', 'paternity leave'];

    /** Leave types limited to one sex (matched on description, like the form). */
    public const FEMALE_ONLY_KEYWORDS = ['maternity leave', 'gyne leave', 'vawc leave', 'special leave benefits for women'];
    public const MALE_ONLY_KEYWORDS   = ['paternity leave'];

    private function descriptionMatches(string $description, array $keywords): bool
    {
        $d = strtolower($description);
        foreach ($keywords as $kw) {
            if (strpos($d, $kw) !== false) return true;
        }
        return false;
    }

    /** True when any of the given leave types needs an attachment. */
    public function requiresAttachment(array $leaveTypeIds): bool
    {
        foreach (DB::table('hris.leave_types')->whereIn('id', $leaveTypeIds)->pluck('description') as $desc) {
            if ($this->descriptionMatches((string) $desc, self::ATTACHMENT_REQUIRED_KEYWORDS)) return true;
        }
        return false;
    }

    /**
     * Checks that apply to both filing and editing. Runs inside the transaction, before
     * anything is written:
     *  - locks this employee's credit rows, so two submissions at once are handled one after
     *    the other (the second sees the first's deduction and dates)
     *  - every leave type exists and is active
     *  - the same date is not filed twice for the same type in one application
     *  - no date is already on another pending/approved leave of this employee
     *  - sex-restricted types match the employee's sex (unknown sex is not blocked)
     */
    private function assertValidFiling(array $leaveIds, $leaveTypes, array $dates, ?int $ignoreAppId): void
    {
        $userId = (int) Auth::id();

        DB::table('leave_credits')->where('user_id', $userId)->lockForUpdate()->get(['id']);

        foreach ($leaveIds as $leaveId) {
            $type = $leaveTypes[$leaveId] ?? null;
            if (!$type || strtolower((string) $type->status) !== 'active') {
                throw new \Exception('One of the selected leave types does not exist or is no longer available.');
            }
        }

        // One row per date: no splitting a day into several half-day rows, even across types.
        $seen = [];
        foreach ($dates as $d) {
            if (isset($seen[$d['date_applied']])) {
                throw new \Exception("{$d['date_applied']} is listed more than once. A date can only be filed once.");
            }
            $seen[$d['date_applied']] = true;
        }

        $taken = DB::table('hris.leave_date_applied as lda')
            ->join('hris.leave_applications as la', 'la.id', '=', 'lda.leave_application_id')
            ->where('la.user_id', $userId)
            ->when($ignoreAppId, fn($q) => $q->where('la.id', '!=', $ignoreAppId))
            ->whereRaw('LOWER(la.status) IN (?, ?)', ['pending', 'approved'])
            ->where('lda.status', 'active')
            ->where('lda.final_disapproved', 0)
            ->whereIn('lda.date_applied', collect($dates)->pluck('date_applied')->unique()->values()->all())
            ->orderBy('lda.date_applied')
            ->distinct()
            ->pluck('lda.date_applied')
            ->all();
        if (!empty($taken)) {
            throw new \Exception('You already have a pending or approved leave on: ' . implode(', ', $taken) . '.');
        }

        // Sex-restricted types need a recorded sex that matches; an unrecorded sex is refused.
        $sex = strtoupper(trim((string) \App\Models\Employee::where('user_id', $userId)->value('sex')));
        foreach ($leaveIds as $leaveId) {
            $desc       = (string) $leaveTypes[$leaveId]->description;
            $femaleOnly = $this->descriptionMatches($desc, self::FEMALE_ONLY_KEYWORDS);
            $maleOnly   = $this->descriptionMatches($desc, self::MALE_ONLY_KEYWORDS);
            if (!$femaleOnly && !$maleOnly) {
                continue;
            }
            if (!in_array($sex, ['MALE', 'FEMALE'], true)) {
                throw new \Exception("{$desc} requires your sex to be recorded in your employee profile. Please ask HR to update it.");
            }
            if ($femaleOnly && $sex !== 'FEMALE') {
                throw new \Exception("{$desc} can only be filed by female employees.");
            }
            if ($maleOnly && $sex !== 'MALE') {
                throw new \Exception("{$desc} can only be filed by male employees.");
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
                ->get(['id', 'description', 'days_prior', 'status', 'deduction_unit'])
                ->keyBy('id');

            $this->assertValidFiling($leaveIds, $leaveTypes, $dates, $appId ?? null);
            $this->assertValidDetails($leaveIds, $leaveTypes, $data['details_map'] ?? []);
            $this->assertValidLeaveDates($leaveTypes, $datesByType);

            // Works out what each date charges (SL running out falls back to VL) and checks
            // every balance. Nothing is charged for LWOP.
            $plan = $this->planCharges($dates, $leaveTypes, $isLwop);

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
                    'leave_type_id'        => (int) $leaveId,
                    'detail'               => $typeDetail['detail'],
                    'extra'                => $typeDetail['extra'] ?? null,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
            }

            // Deduct credits per leave balance (an SL shortfall is deducted from VL).
            $this->deductPlannedCharges($plan['byType']);

            // Insert dates with leave_type_id, status, and per-date charges. For LWOP every
            // charge is 0, so the downstream credit-restore logic is a natural no-op.
            $this->insertDateRows($appId, $dates, $plan['rows']);

            // Approval chain - a head's own step goes to the head above (see OrgHierarchyService)
            DB::table('leave_application_approvals')->insert(
                app(OrgHierarchyService::class)->approvalRowsFor(Auth::user(), 'leave_application_id', $appId)
            );

            return ['app_id' => $appId];
        });
    }

    public function updateLeaveApplication(int $appId, array $data): void
    {
        DB::transaction(function () use ($appId, $data) {
            $app = DB::table('leave_applications')
                ->where('id', $appId)
                ->where('user_id', Auth::id())
                ->lockForUpdate()
                ->first(['id', 'status', 'is_lwop']);

            if (!$app) {
                throw new \Exception('Leave application not found.');
            }

            // LWOP and regular leaves are filed from separate tabs, so an edit never switches
            // between them: the application keeps what it was filed as, whatever the request says.
            $isLwop = (bool) $app->is_lwop;

            // Editable only while Pending and before any approver (HR compliance, SH, DH, HR)
            // has acted. A rejected leave already had its credits returned, so it is never
            // edited - the employee files a new one instead.
            if (strtolower((string) $app->status) !== 'pending') {
                throw new \Exception('Cannot edit: only pending leave applications can be edited. File a new application instead.');
            }

            $anyStepActed = DB::table('leave_application_approvals')
                ->where('leave_application_id', $appId)
                ->whereRaw('LOWER(status) <> ?', ['pending'])
                ->exists();

            if ($anyStepActed) {
                throw new \Exception('Cannot edit: this application has already been acted on by an approver.');
            }

            // Credits this application currently holds, per leave type. They are given back
            // before the new dates are deducted. For LWOP every credits_deducted is 0.
            // (Includes any part of a sick leave date that was charged to VL.)
            $oldCreditsByType = LeaveDateCharges::byType(
                DB::table('hris.leave_date_applied')
                    ->where('leave_application_id', $appId)
                    ->get(LeaveDateCharges::COLUMNS)
            );

            // Validate everything BEFORE writing anything, so a rejected edit leaves the
            // application and its credits exactly as they were (even on a non-transactional engine).
            $dates    = $data['dates'];
            $leaveIds = collect($dates)->pluck('leave_type_id')->unique()->filter()->values()->toArray();
            $n        = count($leaveIds);

            if ($n === 0) {
                throw new \Exception('No leave type selected for any date.');
            }

            $datesByType = collect($dates)->groupBy('leave_type_id');

            $leaveTypes = DB::table('leave_types')
                ->whereIn('id', $leaveIds)
                ->get(['id', 'description', 'days_prior', 'status', 'deduction_unit'])
                ->keyBy('id');

            $this->assertValidFiling($leaveIds, $leaveTypes, $dates, $appId ?? null);
            $this->assertValidDetails($leaveIds, $leaveTypes, $data['details_map'] ?? []);
            $this->assertValidLeaveDates($leaveTypes, $datesByType);

            // Balances are checked as if this application's current deduction were given back.
            $plan = $this->planCharges($dates, $leaveTypes, $isLwop, $oldCreditsByType);

            // All checks passed - now give back the old deduction and replace the dates.
            foreach ($oldCreditsByType as $typeId => $amount) {
                if (!$typeId || $amount <= 0) continue;
                DB::table('leave_credits')
                    ->where('user_id', Auth::id())
                    ->where('leave_id', $typeId)
                    ->increment('based_credit', $amount);
            }

            DB::table('leave_details')->where('leave_application_id', $appId)->delete();
            DB::table('leave_date_applied')->where('leave_application_id', $appId)->delete();

            // Insert new leave_details, one per leave type that has 6.B details
            $detailsMap = $data['details_map'] ?? [];
            foreach ($leaveIds as $leaveId) {
                $typeDetail = $detailsMap[(string) $leaveId] ?? [];
                if (empty($typeDetail['detail'])) continue;

                DB::table('leave_details')->insert([
                    'leave_application_id' => $appId,
                    'leave_type_id'        => (int) $leaveId,
                    'detail'               => $typeDetail['detail'],
                    'extra'                => $typeDetail['extra'] ?? null,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
            }

            // Deduct credits per balance (an SL shortfall is deducted from VL).
            $this->deductPlannedCharges($plan['byType']);

            // Insert new dates
            $this->insertDateRows($appId, $dates, $plan['rows']);

            DB::table('leave_applications')->where('id', $appId)->update([
                'commutation'         => $data['commutation'] ?? null,
                'applicant_signature' => $data['applicant_signature'],
                'is_lwop'             => $isLwop,
                'updated_at'          => now(),
            ]);
        });
    }

    /**
     * What each date charges, and to which balance, plus the total per balance - after
     * checking every balance covers it. Throws with a user-facing message if not.
     *
     * Sick leave falls back to vacation leave when SL runs out (CSC MC No. 41, s. 1998,
     * Sec. 56: "When an employee had already exhausted his sick leave credits, he can use
     * his vacation leave credits but not vice versa."). Dates are charged in date order,
     * so the earliest days use SL first; a date can be split (e.g. 0.5 SL + 0.5 VL). If VL
     * can't cover the rest either, nothing is filed - the employee files it as LWOP.
     *
     * @param  array<int, array{date_applied: string, hours: int, leave_type_id: int}> $dates
     * @param  array<int, float> $giveBack credits this application already holds (edit only)
     * @return array{rows: array<int, array{credits_deducted: float, fallback_leave_type_id: ?int, fallback_credits: float}>, byType: array<int, float>}
     */
    private function planCharges(array $dates, $leaveTypes, bool $isLwop, array $giveBack = []): array
    {
        // Day-based types are filed per whole day (8) or half day (4) only.
        foreach ($dates as $d) {
            $type = $leaveTypes[(int) $d['leave_type_id']] ?? null;
            if ($this->isDayBased($type) && !in_array((int) $d['hours'], [4, 8], true)) {
                throw new \Exception("{$type->description} is filed per day: choose Whole day or Half day for each date.");
            }
        }

        $rows = [];
        if ($isLwop) {
            foreach (array_keys($dates) as $i) {
                $rows[$i] = ['credits_deducted' => 0, 'fallback_leave_type_id' => null, 'fallback_credits' => 0];
            }
            return ['rows' => $rows, 'byType' => []];
        }

        $codes = DB::table('leave_types')->whereIn('code', ['SL', 'VL'])->pluck('id', 'code');
        $slId = isset($codes['SL']) ? (int) $codes['SL'] : null;
        $vlId = isset($codes['VL']) ? (int) $codes['VL'] : null;

        $available = function (int $typeId) use ($giveBack): ?float {
            $credit = DB::table('leave_credits')
                ->where('user_id', Auth::id())
                ->where('leave_id', $typeId)
                ->first(['based_credit', 'used_credit']);

            return $credit
                ? round($credit->based_credit - $credit->used_credit + ($giveBack[$typeId] ?? 0), 3)
                : null;
        };
        $description = fn(int $typeId) => $leaveTypes[$typeId]->description
            ?? DB::table('leave_types')->where('id', $typeId)->value('description')
            ?? 'leave';

        $order = array_keys($dates);
        usort($order, fn($a, $b) => strcmp((string) $dates[$a]['date_applied'], (string) $dates[$b]['date_applied']));

        $slAvailable = $slId ? $available($slId) : null;
        $slLeft = max(0.0, (float) ($slAvailable ?? 0));
        $slNeeded = 0.0;
        $fallbackTotal = 0.0;
        $byType = [];

        foreach ($order as $i) {
            $typeId = (int) $dates[$i]['leave_type_id'];
            $credits = $this->creditsForDate($leaveTypes[$typeId] ?? null, (int) $dates[$i]['hours']);
            $own = $credits;
            $fallback = 0.0;

            if ($slId && $vlId && $typeId === $slId) {
                $slNeeded += $credits;
                $own = round(min($credits, $slLeft), 3);
                $fallback = round($credits - $own, 3);
                $slLeft = round($slLeft - $own, 3);
            }

            $rows[$i] = [
                'credits_deducted' => $own,
                'fallback_leave_type_id' => $fallback > 0 ? $vlId : null,
                'fallback_credits' => $fallback,
            ];
            $byType[$typeId] = round(($byType[$typeId] ?? 0) + $own, 3);
            if ($fallback > 0) {
                $byType[$vlId] = round(($byType[$vlId] ?? 0) + $fallback, 3);
                $fallbackTotal = round($fallbackTotal + $fallback, 3);
            }
        }

        foreach ($byType as $typeId => $needed) {
            if ($needed <= 0) {
                continue;
            }
            $have = $available($typeId);

            if ($typeId === $vlId && $fallbackTotal > 0 && ($have === null || $have < $needed)) {
                $sl = number_format((float) ($slAvailable ?? 0), 3);
                $vl = number_format((float) ($have ?? 0), 3);
                throw new \Exception(
                    "Not enough Sick Leave credits: you have {$sl}, this leave needs " . number_format($slNeeded, 3) . '. '
                    . 'The rest would be charged to Vacation Leave, but your Vacation Leave balance (' . $vl . ') '
                    . 'cannot cover ' . number_format($needed, 3) . '. File the remaining days as leave without pay.'
                );
            }
            if ($have === null) {
                throw new \Exception('No leave credits found for ' . $description($typeId) . '.');
            }
            if ($have < $needed) {
                throw new \Exception('Insufficient ' . $description($typeId) . " credits. Need {$needed}, you have {$have}.");
            }
        }

        return ['rows' => $rows, 'byType' => array_filter($byType, fn($amount) => $amount > 0)];
    }

    /** Day-based leave types are deducted per day, not by hours (leave_types.deduction_unit). */
    private function isDayBased($leaveType): bool
    {
        return $leaveType && strtolower((string) ($leaveType->deduction_unit ?? 'hours')) === 'days';
    }

    /**
     * Credits one filed date costs. Hour-based types use the CSC conversion (8 hours = 1.000,
     * 10 hours = 1.250); day-based types cost 1.000 per whole day (stored as 8) and 0.500 per
     * half day (stored as 4), whatever the employee's shift length.
     */
    private function creditsForDate($leaveType, int $hours): float
    {
        if ($this->isDayBased($leaveType)) {
            return $hours >= 8 ? 1.0 : 0.5;
        }

        return $this->minuteConversionService->computeDeduction($hours * 60);
    }

    /** @param array<int, float> $byType */
    private function deductPlannedCharges(array $byType): void
    {
        foreach ($byType as $typeId => $amount) {
            DB::table('leave_credits')
                ->where('user_id', Auth::id())
                ->where('leave_id', $typeId)
                ->decrement('based_credit', $amount);
        }
    }

    private function insertDateRows(int $appId, array $dates, array $planRows): void
    {
        $rows = [];
        foreach ($dates as $i => $d) {
            $rows[] = [
                'leave_application_id'   => $appId,
                'leave_type_id'          => $d['leave_type_id'],
                'date_applied'           => $d['date_applied'],
                'hours'                  => $d['hours'],
                'status'                 => 'active',
                'credits_deducted'       => $planRows[$i]['credits_deducted'],
                'fallback_leave_type_id' => $planRows[$i]['fallback_leave_type_id'],
                'fallback_credits'       => $planRows[$i]['fallback_credits'],
            ];
        }
        DB::table('leave_date_applied')->insert($rows);
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

            // Includes the part of sick leave dates charged to this type (SL -> VL fallback).
            $less = $isRejected ? 0.0 : (float) (LeaveDateCharges::byType(
                DB::table('hris.leave_date_applied')
                    ->where('leave_application_id', $leaveApplication->id)
                    ->where('status', '!=', 'cancelled')
                    ->where('final_disapproved', 0)
                    ->get(LeaveDateCharges::COLUMNS)
            )[(int) $typeId] ?? 0);

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
                'lt.deduction_unit',
                'lda.sh_flagged_disapprove',
                'lda.sh_disapproval_remark',
                'lda.final_disapproved',
                'lda.dh_disapproval_remark',
                'lda.credits_deducted',
                'lda.fallback_leave_type_id',
                'lda.fallback_credits'
            )
            ->orderBy('lda.date_applied', 'asc')
            ->get();

        // Unique leave types (in order of first appearance in dates)
        $uniqueTypeIds = $dateRows->pluck('leave_type_id')->unique()->filter()->values();
        $application->leave_type = $dateRows->pluck('leave_type_name')->unique()->filter()->implode(', ');

        // Each detail row carries its own leave type
        $detailsByType = DB::table('hris.leave_details')
            ->where('leave_application_id', $id)
            ->whereNotNull('leave_type_id')
            ->get(['leave_type_id', 'detail', 'extra'])
            ->keyBy('leave_type_id');

        // Full leave type map for descriptions
        $leaveTypesMap = $uniqueTypeIds->isNotEmpty()
            ? DB::table('hris.leave_types')->whereIn('id', $uniqueTypeIds)->pluck('description', 'id')
            : collect();

        $details = $uniqueTypeIds->map(function ($typeId) use ($detailsByType, $leaveTypesMap) {
            $detail = $detailsByType->get($typeId);
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
