<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Builds the period-specific snapshot of a draft cutoff: employee, daily
 * attendance, leave, balances, pay inputs and rule versions, all copied from
 * the live tables in one transaction. Running it again replaces the cutoff's
 * snapshot, so it never duplicates; only a draft can be generated.
 *
 * vl_earned and sl_earned hold the Table III value for this cutoff alone. Credits
 * are monthly and can span several cutoffs, so the amount actually posted lives
 * in leave_credit_ledger; a finalized snapshot is never written to afterwards.
 */
class CutoffSnapshotService
{
    public const VERSION = 1;

    private const SNAPSHOT_TABLES = [
        'hris.cutoff_employee_snapshots',
        'hris.cutoff_leave_snapshots',
        'hris.cutoff_leave_balance_snapshots',
        'hris.cutoff_payroll_components',
        'hris.cutoff_config_snapshots',
    ];

    private PayrollServices $payrollServices;
    private DTRServices $dtrServices;
    private CutoffLifecycleService $lifecycle;
    private LeaveCreditService $leaveCreditService;

    public function __construct(PayrollServices $payrollServices, DTRServices $dtrServices, CutoffLifecycleService $lifecycle, LeaveCreditService $leaveCreditService)
    {
        $this->leaveCreditService = $leaveCreditService;
        $this->payrollServices = $payrollServices;
        $this->dtrServices = $dtrServices;
        $this->lifecycle = $lifecycle;
    }

    /**
     * Draft -> processing -> for review. On any failure the transaction rolls
     * back, leaving no partial snapshot, and the cutoff returns to draft.
     */
    public function generate(int $cutoffId, int $userId): array
    {
        $summaryTable = $this->payrollServices->getCutoffSummaryTableName();
        if (!DB::table($summaryTable)->where('cutoff_id', $cutoffId)->exists()) {
            throw ValidationException::withMessages(['cutoff_id' => 'This cutoff has no employees yet. Add employees first.']);
        }

        $this->lifecycle->beginProcessing($cutoffId, $userId);

        try {
            DB::transaction(function () use ($cutoffId, $userId) {
                $this->build($cutoffId, $userId);
                $this->lifecycle->finishProcessing($cutoffId, $userId, self::VERSION);
            });
        } catch (\Throwable $exception) {
            $this->lifecycle->failProcessing($cutoffId, $userId, 'Snapshot generation failed: ' . $exception->getMessage());
            throw $exception;
        }

        return $this->report($cutoffId);
    }

    // -----------------------------------------------------------------------
    // Generation in batches, driven by the page (no request runs long enough to
    // time out): start -> batch, batch, ... -> finish. While it runs the cutoff
    // is "processing"; each batch refreshes processing_started_at, so only an
    // abandoned run (page closed) goes stale and can be started again after
    // CutoffLifecycleService::STALE_PROCESSING_MINUTES.
    // -----------------------------------------------------------------------

    /** Draft -> processing; clears the old snapshot and saves the rule versions. Returns the employees to process. */
    public function startBatches(int $cutoffId, int $userId): array
    {
        $employeeIds = $this->cutoffEmployeeIds($cutoffId);
        if (empty($employeeIds)) {
            throw ValidationException::withMessages(['cutoff_id' => 'This cutoff has no employees yet. Add employees first.']);
        }

        $this->lifecycle->beginProcessing($cutoffId, $userId);
        try {
            DB::transaction(function () use ($cutoffId) {
                $master = $this->lifecycle->find($cutoffId);
                $this->prepare($cutoffId, (string) $master->period_start, (string) $master->period_end);
            });
        } catch (\Throwable $exception) {
            $this->lifecycle->failProcessing($cutoffId, $userId, 'Snapshot generation failed: ' . $exception->getMessage());
            throw $exception;
        }

        return $employeeIds;
    }

    /** One batch of employees, in its own transaction. Returns how many were processed. */
    public function processBatch(int $cutoffId, int $userId, array $employeeIds): int
    {
        $this->assertProcessing($cutoffId);
        $employeeIds = array_values(array_intersect($this->cutoffEmployeeIds($cutoffId), array_map('intval', $employeeIds)));
        if (empty($employeeIds)) {
            return 0;
        }

        try {
            DB::transaction(function () use ($cutoffId, $userId, $employeeIds) {
                // A batch sent twice replaces its own rows instead of duplicating them.
                foreach (self::SNAPSHOT_TABLES as $table) {
                    if ($table !== 'hris.cutoff_config_snapshots') {
                        DB::table($table)->where('cutoff_id', $cutoffId)->whereIn('employee_id', $employeeIds)->delete();
                    }
                }
                $this->buildEmployees($cutoffId, $userId, $employeeIds, $this->savedPointsTable($cutoffId));
                DB::table($this->payrollServices->getCutoffSummaryMasterTableName())
                    ->where('id', $cutoffId)->update(['processing_started_at' => now()]);
            });
        } catch (\Throwable $exception) {
            $this->lifecycle->failProcessing($cutoffId, $userId, 'Snapshot generation failed: ' . $exception->getMessage());
            throw $exception;
        }

        return count($employeeIds);
    }

    /** Processing -> for review, once every employee of the cutoff has a snapshot. */
    public function finishBatches(int $cutoffId, int $userId): array
    {
        $this->assertProcessing($cutoffId);
        $done = DB::table('hris.cutoff_employee_snapshots')->where('cutoff_id', $cutoffId)->pluck('employee_id')
            ->map(fn($id) => (int) $id)->all();
        $missing = array_diff($this->cutoffEmployeeIds($cutoffId), $done);
        if (!empty($missing)) {
            $this->lifecycle->failProcessing($cutoffId, $userId, count($missing) . ' employee(s) were not processed.');
            throw ValidationException::withMessages(['cutoff_id' => count($missing) . ' employee(s) were not processed. Generate the snapshot again.']);
        }

        DB::transaction(fn() => $this->lifecycle->finishProcessing($cutoffId, $userId, self::VERSION));

        return $this->report($cutoffId);
    }

    /** Puts a run the page gave up on (a failed request) back to draft. */
    public function abandonBatches(int $cutoffId, int $userId, string $reason): void
    {
        $master = $this->lifecycle->find($cutoffId);
        if ($master && strtolower((string) $master->status) === CutoffLifecycleService::PROCESSING) {
            $this->lifecycle->failProcessing($cutoffId, $userId, 'Snapshot generation stopped: ' . mb_substr($reason, 0, 900));
        }
    }

    private function cutoffEmployeeIds(int $cutoffId): array
    {
        return DB::table($this->payrollServices->getCutoffSummaryTableName())->where('cutoff_id', $cutoffId)
            ->orderBy('employee_id')->pluck('employee_id')->map(fn($id) => (int) $id)->all();
    }

    private function assertProcessing(int $cutoffId): void
    {
        $master = $this->lifecycle->find($cutoffId);
        if (!$master || strtolower((string) $master->status) !== CutoffLifecycleService::PROCESSING) {
            throw ValidationException::withMessages(['cutoff_id' => 'This cutoff is not being generated. Start the snapshot again.']);
        }
    }

    /** Table III as saved for this cutoff by prepare(). */
    private function savedPointsTable(int $cutoffId): array
    {
        $payload = DB::table('hris.cutoff_config_snapshots')->where(['cutoff_id' => $cutoffId, 'config_key' => 'table_iii'])->value('payload');
        if ($payload === null) {
            throw ValidationException::withMessages(['cutoff_id' => 'The rule settings of this run are missing. Start the snapshot again.']);
        }

        return json_decode($payload, true) ?: [];
    }

    /** Counts, excluded employees and exceptions of the cutoff's snapshot. */
    public function report(int $cutoffId): array
    {
        $employees = DB::table('hris.cutoff_employee_snapshots')->where('cutoff_id', $cutoffId)->orderBy('employee_name')->get();
        $names = $employees->pluck('employee_name', 'employee_id');

        $exceptions = [];
        $rows = DB::table($this->payrollServices->getCutoffSummaryTableName())
            ->where('cutoff_id', $cutoffId)->whereNotNull('exceptions')->get(['employee_id', 'exceptions']);
        foreach ($rows as $row) {
            foreach (json_decode($row->exceptions, true) ?: [] as $exception) {
                $exceptions[] = $exception + [
                    'employee_id' => (int) $row->employee_id,
                    'employee' => $names[$row->employee_id] ?? ('#' . $row->employee_id),
                ];
            }
        }
        usort($exceptions, fn($a, $b) => [$a['employee'], $a['type']] <=> [$b['employee'], $b['type']]);

        $counts = [];
        foreach ($exceptions as $exception) {
            $counts[$exception['type']] = ($counts[$exception['type']] ?? 0) + 1;
        }

        return [
            'processed' => $employees->count(),
            'payroll_eligible' => $employees->where('payroll_eligible', 1)->count(),
            'credit_eligible' => $employees->where('credit_eligible', 1)->count(),
            'excluded' => $employees->whereNotNull('exclusion_reason')->map(fn($e) => [
                'employee_id' => (int) $e->employee_id,
                'employee' => $e->employee_name,
                'reason' => $e->exclusion_reason,
            ])->values()->all(),
            'exceptions' => $exceptions,
            'counts' => $counts,
        ];
    }

    /** Everything saved for one employee in this cutoff, or null if they have no snapshot. */
    public function employeeDetail(int $cutoffId, int $employeeId): ?array
    {
        $keys = ['cutoff_id' => $cutoffId, 'employee_id' => $employeeId];
        $employee = DB::table('hris.cutoff_employee_snapshots')->where($keys)->first();
        if (!$employee) {
            return null;
        }
        $summary = DB::table($this->payrollServices->getCutoffSummaryTableName())->where($keys)->first();
        if ($summary) {
            $summary->exceptions = json_decode((string) $summary->exceptions, true) ?: [];
        }

        return [
            'employee' => $employee,
            'summary' => $summary,
            'days' => DB::table('hris.cutoff_summary_days')->where($keys)->orderBy('date')->get(),
            'leaves' => DB::table('hris.cutoff_leave_snapshots')->where($keys)->orderBy('leave_date')->get(),
            'balances' => DB::table('hris.cutoff_leave_balance_snapshots')->where($keys)->orderBy('leave_type_code')->get(),
            'components' => DB::table('hris.cutoff_payroll_components')->where($keys)->orderBy('type')->orderBy('code')->get(),
        ];
    }

    private function build(int $cutoffId, int $userId): void
    {
        $master = $this->lifecycle->find($cutoffId);
        $pointsTable = $this->prepare($cutoffId, (string) $master->period_start, (string) $master->period_end);
        $this->buildEmployees($cutoffId, $userId, $this->cutoffEmployeeIds($cutoffId), $pointsTable);
    }

    /** Clears the cutoff's snapshot, frees its included late changes and saves the rule versions. Returns Table III. */
    private function prepare(int $cutoffId, string $start, string $end): array
    {
        foreach (self::SNAPSHOT_TABLES as $table) {
            DB::table($table)->where('cutoff_id', $cutoffId)->delete();
        }
        app(CutoffAdjustmentService::class)->release($cutoffId);

        return $this->captureConfig($cutoffId, $start, $end);
    }

    /** Snapshot rows for the given employees of the cutoff. */
    private function buildEmployees(int $cutoffId, int $userId, array $employeeIds, array $pointsTable): void
    {
        $master = $this->lifecycle->find($cutoffId);
        $start = (string) $master->period_start;
        $end = (string) $master->period_end;
        $summaryTable = $this->payrollServices->getCutoffSummaryTableName();
        $adjustments = app(CutoffAdjustmentService::class);

        $people = $this->loadPeople($employeeIds);
        $salaries = DB::table('hris.employee_salary_history')->whereIn('userid', $employeeIds)->get()->groupBy('userid');
        $settings = DB::table('hris.payroll_settings')->whereIn('userid', $employeeIds)->orderBy('id')->get()->keyBy('userid');
        $leaves = $this->loadLeaves($employeeIds, $start, $end)->groupBy('user_id');
        $openings = $this->loadOpenings($employeeIds, $start);
        $movements = $this->loadMovements($employeeIds, $end);
        $earningTypes = DB::table('hris.leave_types')->where('status', 'active')->where('monthly_earned', 1)->get(['id', 'code']);
        $typeCodes = DB::table('hris.leave_types')->pluck('code', 'id');

        foreach ($employeeIds as $employeeId) {
            $this->payrollServices->generateCutoffSummary($employeeId, $start, $end, $master->title, $userId);

            $exceptions = [];
            $person = $people->get($employeeId);
            $salary = $this->resolveSalary($salaries->get($employeeId, collect()), $settings->get($employeeId), $start, $end, $exceptions);
            $creditEligible = $this->insertEmployee($cutoffId, $employeeId, $person, $salary, $start, $end, $exceptions);

            $minuteRate = $this->preserveScheduledRates($cutoffId, $employeeId, $salary);

            $days = DB::table('hris.cutoff_summary_days')->where('cutoff_id', $cutoffId)
                ->where('employee_id', $employeeId)->orderBy('date')->get()->keyBy('date');
            $this->flagAbsences($days, $exceptions);

            $summary = DB::table($summaryTable)->where('cutoff_id', $cutoffId)->where('employee_id', $employeeId)->first();
            $leaveRows = $this->insertLeaves($cutoffId, $employeeId, $leaves->get($employeeId, collect()), $days, $start, $end, $summary, $exceptions);
            $this->insertBalances($cutoffId, $employeeId, $leaveRows, $openings, $movements->get($employeeId, collect()), $earningTypes, $typeCodes, $exceptions);
            $this->insertPayComponents($cutoffId, $employeeId, $salary, $start, $end);
            $carried = $adjustments->includeIn($cutoffId, $employeeId, $start);
            if (!empty($carried)) {
                $exceptions[] = ['type' => 'late_changes_included', 'detail' => count($carried) . ' late change(s) from earlier cutoffs are included in this one.'];
            }

            $basic = (float) ($salary['amount'] ?? 0);
            $charges = $this->payrollServices->scheduledCharges(
                $cutoffId,
                $employeeId,
                (float) $summary->days_absent,
                (float) $summary->lwop_days,
                (int) $summary->late_minutes + (int) $summary->undertime_minutes + (int) $summary->approved_pass_slips_minutes,
                $minuteRate,
                $basic
            );
            $earned = $creditEligible
                ? $this->leaveCreditService->earnedPointsForLwop((float) $summary->credit_without_pay_days, $pointsTable)
                : 0.0;
            DB::table($summaryTable)->where('id', $summary->id)->update([
                'total_paid_leave_days' => round(collect($leaveRows)->sum('paid_days'), 3),
                'total_rendered_minutes' => (int) $days->sum('rendered_minutes'),
                'qualifying_service_days' => $this->qualifyingDays($person, $start, $end),
                'leave_earning_rate' => $earned,
                'vl_earned' => $earned,
                'sl_earned' => $earned,
                'gross_salary_basis' => $salary ? $basic : null,
                'scheduled_absent_minutes' => $charges['absent_minutes'],
                'scheduled_lwop_minutes' => $charges['lwop_minutes'],
                'payroll_absence_deduction_basis' => $salary ? round($charges['absent_amount'], 2) : null,
                'payroll_lwop_deduction_basis' => $salary ? round($charges['lwop_amount'], 2) : null,
                'exceptions' => empty($exceptions) ? null : json_encode($exceptions),
            ]);
        }
    }

    /** Saves the rule versions and returns Table III for computing with. */
    private function captureConfig(int $cutoffId, string $start, string $end): array
    {
        $configs = [
            'table_iii' => DB::table('hris.monthly_earned_points')->orderBy('lwop')->get(['lwop', 'points']),
            'holidays' => DB::table('hris.calendar_holidays')->whereBetween('date', [$start, $end])->orderBy('date')->get(['date', 'type', 'description']),
            'shifts' => DB::table('hris.schedule_master_lists')->orderBy('id')
                ->get(['id', 'code', 'type', 'time_in', 'time_in_latest', 'time_out', 'time_out_latest', 'break_in', 'break_out', 'pay_computation_id']),
            'pay_computations' => DB::table('hris.pay_computations')->orderBy('id')
                ->get(['id', 'name', 'working_days_per_month', 'standard_hours_per_day', 'is_default']),
            'tax_rules' => DB::table('hris.payroll_tax_rules')->where('is_active', 1)->orderBy('purpose')->orderBy('sort_order')
                ->get(['purpose', 'min_annual', 'max_annual', 'rate_percent', 'tax_mode']),
            'leave_types' => DB::table('hris.leave_types')->orderBy('id')->get(['id', 'code', 'monthly_earned', 'deduction_unit']),
            'pay_divisor' => [
                'working_days_per_month' => (float) $this->payrollServices->defaultComputation()->working_days_per_month,
                'standard_hours_per_day' => (float) $this->payrollServices->defaultComputation()->standard_hours_per_day,
            ],
        ];

        $now = now();
        foreach ($configs as $key => $value) {
            $payload = json_encode($value);
            DB::table('hris.cutoff_config_snapshots')->insert([
                'cutoff_id' => $cutoffId,
                'config_key' => $key,
                'payload' => $payload,
                'payload_hash' => hash('sha256', $payload),
                'created_at' => $now,
            ]);
        }

        return json_decode(json_encode($configs['table_iii']), true);
    }

    private function loadPeople(array $employeeIds): Collection
    {
        return DB::table('tdh_user.users as u')
            ->leftJoin('hris.employees as e', 'e.user_id', '=', 'u.id')
            ->leftJoin('tdh_user.designation as de', 'de.id', '=', 'u.designation')
            ->leftJoin('tdh_user.division as dv', 'dv.id', '=', 'u.division')
            ->leftJoin('tdh_user.section as sc', 'sc.id', '=', 'u.section')
            ->whereIn('u.id', $employeeIds)
            ->get([
                'u.id', 'u.fname', 'u.mname', 'u.lname', 'u.suffix', 'u.status', 'u.designation', 'u.division', 'u.section',
                'e.employee_type', 'e.date_hired', 'e.date_resigned',
                'de.description as designation_name', 'dv.description as division_name', 'sc.description as section_name',
            ])
            ->keyBy('id');
    }

    /** Salary in effect on the last day of the cutoff, from history, else payroll_settings. */
    private function resolveSalary(Collection $history, ?object $setting, string $start, string $end, array &$exceptions): ?array
    {
        $inEffect = $history
            ->filter(fn($h) => (!$h->effective_from || $h->effective_from <= $end) && (!$h->effective_to || $h->effective_to >= $end))
            ->sortBy(fn($h) => (string) $h->effective_from)
            ->last();

        if ($history->contains(fn($h) => $h->effective_from && $h->effective_from > $start && $h->effective_from <= $end)) {
            $exceptions[] = ['type' => 'salary_changed_in_period', 'detail' => 'Salary changed during this cutoff. The rate in effect on the last day is used.'];
        }

        if ($inEffect) {
            return [
                'amount' => (float) $inEffect->basic_salary,
                'grade' => (int) $inEffect->salary_grade,
                'step' => (int) $inEffect->step,
                'effective_from' => $inEffect->effective_from,
                'source' => (string) $inEffect->source,
            ];
        }
        if ($setting) {
            return [
                'amount' => (float) $setting->basic_salary,
                'grade' => (int) $setting->salary_grade,
                'step' => (int) $setting->step,
                'effective_from' => null,
                'source' => 'payroll_settings',
            ];
        }

        $exceptions[] = ['type' => 'no_salary', 'detail' => 'No salary on file. Excluded from payroll.'];

        return null;
    }

    /** @return bool whether the employee earns leave credits */
    private function insertEmployee(int $cutoffId, int $employeeId, ?object $person, ?array $salary, string $start, string $end, array &$exceptions): bool
    {
        $type = $person->employee_type ?? null;
        $isJobOrder = $type === 'Job Order' || ($person->status ?? null) === 'JO';

        if (!$person) {
            $exceptions[] = ['type' => 'no_employee_record', 'detail' => 'No matching user account was found.'];
        } elseif (!$type) {
            $exceptions[] = ['type' => 'missing_employee_type', 'detail' => 'Employee type is blank.'];
        }

        $separation = $person->date_resigned ?? null;
        if ($separation && $separation < $end) {
            $exceptions[] = [
                'type' => 'separated_in_period',
                'detail' => 'Separated on ' . Carbon::parse($separation)->format('M j, Y') . ', before this cutoff ends. Days after that count as absent unless the cutoff ends on that date.',
            ];
        }

        $reason = !$salary ? 'No salary on file' : ($isJobOrder ? 'Job Order: does not earn leave credits' : null);
        $rates = $salary ? $this->payrollServices->payRates($salary['amount']) : null;
        $now = now();

        DB::table('hris.cutoff_employee_snapshots')->insert([
            'cutoff_id' => $cutoffId,
            'employee_id' => $employeeId,
            'employee_name' => $person ? $this->formatName($person) : ('#' . $employeeId),
            'employment_type' => $type,
            'employment_status' => $person->status ?? null,
            'designation_id' => $person->designation ?? null,
            'position' => $person->designation_name ?? null,
            'salary_grade' => $salary['grade'] ?? null,
            'salary_step' => $salary['step'] ?? null,
            'monthly_salary' => $salary['amount'] ?? null,
            'salary_effective_date' => $salary['effective_from'] ?? null,
            'salary_source' => $salary['source'] ?? null,
            'daily_base_rate' => $rates['daily'] ?? null,
            'hourly_rate' => $rates['hourly'] ?? null,
            'minute_rate' => $rates['minute'] ?? null,
            'division_id' => $person->division ?? null,
            'division_name' => $person->division_name ?? null,
            'section_id' => $person->section ?? null,
            'section_name' => $person->section_name ?? null,
            'appointment_date' => $person->date_hired ?? null,
            'separation_date' => $separation,
            'payroll_eligible' => $salary ? 1 : 0,
            'credit_eligible' => $isJobOrder ? 0 : 1,
            'exclusion_reason' => $reason,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return !$isJobOrder;
    }

    /**
     * Saves each day's rates from its own shift's computation, and the employee's
     * rates from their usual shift. Returns the employee's minute rate.
     */
    private function preserveScheduledRates(int $cutoffId, int $employeeId, ?array $salary): float
    {
        if (!$salary) {
            return 0.0;
        }
        $computations = DB::table('hris.pay_computations')->get()->keyBy('id');
        $default = $this->payrollServices->defaultComputation();
        $ratesOf = fn($computation) => $this->payrollServices->payRates(
            $salary['amount'], (float) $computation->working_days_per_month, (float) $computation->standard_hours_per_day
        );
        $days = fn() => DB::table('hris.cutoff_summary_days')->where('cutoff_id', $cutoffId)->where('employee_id', $employeeId);

        foreach ($days()->distinct()->pluck('pay_computation_id') as $computationId) {
            $rates = $ratesOf($computations->get($computationId) ?? $default);
            $query = $days();
            $computationId === null ? $query->whereNull('pay_computation_id') : $query->where('pay_computation_id', $computationId);
            $query->update([
                'minute_rate' => $rates['minute'],
                'scheduled_daily_rate' => DB::raw(sprintf('ROUND(%.8F * COALESCE(required_minutes, 0) / 60, 4)', $rates['hourly'])),
            ]);
        }

        $usual = $days()->where('required_minutes', '>', 0)
            ->groupBy('required_minutes', 'pay_computation_id')
            ->orderByRaw('COUNT(*) DESC')
            ->orderByDesc('required_minutes')
            ->first(['required_minutes', 'pay_computation_id']);
        $computation = ($usual ? $computations->get($usual->pay_computation_id) : null) ?? $default;
        $rates = $ratesOf($computation);

        DB::table('hris.cutoff_employee_snapshots')->where('cutoff_id', $cutoffId)->where('employee_id', $employeeId)->update([
            'pay_computation_id' => $computation->id,
            'pay_computation_name' => $computation->name,
            'daily_base_rate' => $rates['daily'],
            'hourly_rate' => $rates['hourly'],
            'minute_rate' => $rates['minute'],
            'scheduled_hours_per_day' => $usual ? round($usual->required_minutes / 60, 2) : null,
            'scheduled_daily_rate' => $usual ? round($rates['hourly'] * $usual->required_minutes / 60, 4) : null,
        ]);

        return $rates['minute'];
    }

    private function flagAbsences(Collection $days, array &$exceptions): void
    {
        $absent = $days->filter(fn($d) => (int) $d->is_absent === 1 && $d->exemption_reason === null);
        if ($absent->isNotEmpty()) {
            $dates = $absent->map(fn($d) => Carbon::parse($d->date)->format('M j'))->implode(', ');
            $exceptions[] = ['type' => 'unfiled_absence', 'detail' => $absent->count() . ' day(s) absent with nothing filed: ' . $dates];
        }
    }

    /** Approved and pending leave dates in the period (pending kept for review, counted as 0 days). */
    private function loadLeaves(array $employeeIds, string $start, string $end): Collection
    {
        $rows = DB::table('hris.leave_date_applied as lda')
            ->join('hris.leave_applications as la', 'la.id', '=', 'lda.leave_application_id')
            ->leftJoin('hris.leave_types as lt', 'lt.id', '=', 'lda.leave_type_id')
            ->whereIn('la.user_id', $employeeIds)
            ->whereRaw('LOWER(la.status) IN (?, ?)', ['approved', 'pending'])
            ->where('lda.status', 'active')
            ->where('lda.final_disapproved', 0)
            ->whereBetween('lda.date_applied', [$start, $end])
            ->orderBy('lda.date_applied')
            ->get([
                'lda.id as date_id', 'lda.leave_application_id', 'lda.leave_type_id', 'lda.date_applied', 'lda.hours', 'lda.status as date_status',
                'lda.final_disapproved', 'lda.credits_deducted', 'lda.fallback_leave_type_id', 'lda.fallback_credits',
                'la.user_id', 'la.status as application_status', 'la.is_lwop',
                'lt.code as type_code', 'lt.description as type_name', 'lt.deduction_unit',
            ]);

        $ranges = DB::table('hris.leave_date_applied')
            ->whereIn('leave_application_id', $rows->pluck('leave_application_id')->unique())
            ->where('status', 'active')
            ->where('final_disapproved', 0)
            ->groupBy('leave_application_id')
            ->selectRaw('leave_application_id, MIN(date_applied) AS first_date, MAX(date_applied) AS last_date, COUNT(*) AS total_days')
            ->get()
            ->keyBy('leave_application_id');

        return $rows->map(function ($row) use ($ranges) {
            $range = $ranges->get($row->leave_application_id);
            $row->first_date = $range->first_date ?? null;
            $row->last_date = $range->last_date ?? null;
            $row->total_days = $range->total_days ?? null;

            return $row;
        });
    }

    private function insertLeaves(int $cutoffId, int $employeeId, Collection $rows, Collection $days, string $start, string $end, object $summary, array &$exceptions): array
    {
        $lwop = $this->dtrServices->getLwopDaysByRange($employeeId, $start, $end);
        $inserted = [];
        $now = now();

        foreach ($rows as $row) {
            $date = Carbon::parse($row->date_applied)->toDateString();
            $approved = strtolower((string) $row->application_status) === 'approved';
            $isLwop = (int) $row->is_lwop === 1;

            $counted = 0.0;
            if ($approved) {
                $counted = $isLwop
                    ? (float) ($lwop[$date]['days'] ?? 0)
                    : $this->paidDays($row, $days->get($date));
            } else {
                $exceptions[] = ['type' => 'pending_leave', 'detail' => "Leave application #{$row->leave_application_id} is still pending for {$date}."];
            }
            if ($approved && $isLwop && !isset($lwop[$date])) {
                $exceptions[] = ['type' => 'lwop_non_working_day', 'detail' => "LWOP filed on {$date}, a holiday or rest day. It is not counted."];
            }

            $record = [
                'cutoff_id' => $cutoffId,
                'employee_id' => $employeeId,
                'leave_application_id' => $row->leave_application_id,
                'leave_date_applied_id' => $row->date_id,
                'leave_type_id' => $row->leave_type_id,
                'leave_type_code' => $row->type_code,
                'leave_type_name' => $row->type_name,
                'application_start_date' => $row->first_date,
                'application_end_date' => $row->last_date,
                'application_total_days' => $row->total_days,
                'leave_date' => $date,
                'hours' => (int) $row->hours,
                'approved_days' => $counted,
                'paid_days' => $isLwop ? 0 : $counted,
                'unpaid_days' => $isLwop ? $counted : 0,
                'is_lwop' => $isLwop ? 1 : 0,
                'approval_status' => strtolower((string) $row->application_status),
                'date_status' => $row->date_status,
                'final_disapproved' => (int) $row->final_disapproved,
                'charged_to_leave_credit' => (!$isLwop && ((float) $row->credits_deducted > 0 || (float) $row->fallback_credits > 0)) ? 1 : 0,
                'credits_deducted' => (float) $row->credits_deducted,
                'fallback_leave_type_id' => $row->fallback_leave_type_id,
                'fallback_credits' => (float) $row->fallback_credits,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            DB::table('hris.cutoff_leave_snapshots')->insert($record);
            $inserted[] = $record;
        }

        $unpaid = round(collect($inserted)->sum('unpaid_days'), 3);
        if (abs($unpaid - round((float) $summary->lwop_days, 3)) > 0.0005) {
            $exceptions[] = ['type' => 'lwop_mismatch', 'detail' => "Leave records show {$unpaid} LWOP day(s), but attendance shows {$summary->lwop_days}."];
        }

        return $inserted;
    }

    private function paidDays(object $row, ?object $day): float
    {
        $hours = (int) $row->hours;
        if (strtolower((string) $row->deduction_unit) === 'days') {
            return $hours >= 8 ? 1.0 : 0.5;
        }
        $required = (int) ($day->required_minutes ?? 0);

        return $required > 0 ? min(1.0, round(($hours * 60) / $required, 3)) : min(1.0, round($hours / 8, 3));
    }

    /**
     * [employee_id][leave_type_id] => ['balance', 'source']: the most recent of the
     * last finalized ending balance and a certified opening balance, plus credits
     * posted since then. The live leave_credits balance is deliberately not used.
     */
    private function loadOpenings(array $employeeIds, string $start): array
    {
        $openings = [];

        $ledger = DB::table('hris.leave_credit_ledger')
            ->whereIn('employee_id', $employeeIds)
            ->where('transaction_type', 'opening')
            ->where('effective_date', '<=', $start)
            ->orderBy('effective_date')
            ->orderBy('id')
            ->get(['id', 'employee_id', 'leave_type_id', 'ending_balance', 'effective_date']);
        foreach ($ledger as $row) {
            $openings[$row->employee_id][$row->leave_type_id] = ['balance' => (float) $row->ending_balance, 'source' => 'opening_balance', 'as_of' => (string) $row->effective_date, 'ledger_id' => (int) $row->id];
        }

        $previous = DB::table('hris.cutoff_leave_balance_snapshots as b')
            ->join('hris.cutoff_summaries_master as m', 'm.id', '=', 'b.cutoff_id')
            ->whereIn('b.employee_id', $employeeIds)
            ->whereIn('m.status', [CutoffLifecycleService::FINALIZED, CutoffLifecycleService::CLOSED])
            ->where('m.period_end', '<', $start)
            ->where('b.opening_source', '<>', 'unavailable')
            ->orderBy('m.period_end')
            ->orderBy('b.id')
            ->get(['b.employee_id', 'b.leave_type_id', 'b.ending_balance', 'm.period_end']);
        foreach ($previous as $row) {
            $current = $openings[$row->employee_id][$row->leave_type_id] ?? null;
            if (!$current || $current['as_of'] <= (string) $row->period_end) {
                $openings[$row->employee_id][$row->leave_type_id] = ['balance' => (float) $row->ending_balance, 'source' => 'previous_cutoff', 'as_of' => (string) $row->period_end, 'ledger_id' => 0];
            }
        }

        // Credits are posted after a cutoff is finalized, so they aren't in its ending balance.
        $earned = DB::table('hris.leave_credit_ledger')
            ->whereIn('employee_id', $employeeIds)
            ->where('transaction_type', LeaveLedgerService::EARNED)
            ->where('source_type', LeaveLedgerService::SOURCE_EARNED_POSTING)
            ->where('effective_date', '<', $start)
            ->get(['id', 'employee_id', 'leave_type_id', 'earned_credits', 'effective_date']);
        foreach ($earned as $row) {
            $opening = $openings[$row->employee_id][$row->leave_type_id] ?? null;
            // Anything posted before a certified opening is already inside it.
            if ($opening && (int) $row->id > $opening['ledger_id'] && (string) $row->effective_date >= $opening['as_of']) {
                $openings[$row->employee_id][$row->leave_type_id]['balance'] = round($opening['balance'] + (float) $row->earned_credits, 3);
            }
        }

        return $openings;
    }

    /** Balance changes other than leave dates and monthly credits: bulk credits, CTO, monetization, manual edits. */
    private function loadMovements(array $employeeIds, string $end): Collection
    {
        return DB::table('hris.leave_credit_ledger')
            ->whereIn('employee_id', $employeeIds)
            ->where('transaction_type', '<>', LeaveLedgerService::OPENING)
            ->whereNotIn('source_type', [LeaveLedgerService::SOURCE_LEAVE_DATE, LeaveLedgerService::SOURCE_EARNED_POSTING])
            ->where('effective_date', '<=', $end)
            ->get(['id', 'employee_id', 'leave_type_id', 'earned_credits', 'consumed_credits', 'adjustments', 'effective_date'])
            ->groupBy('employee_id');
    }

    private function insertBalances(int $cutoffId, int $employeeId, array $leaveRows, array $openings, Collection $movements, Collection $earningTypes, Collection $typeCodes, array &$exceptions): void
    {
        $used = [];
        foreach ($leaveRows as $row) {
            if ($row['approval_status'] !== 'approved') {
                continue;
            }
            if ($row['leave_type_id'] && $row['credits_deducted'] > 0) {
                $used[$row['leave_type_id']] = ($used[$row['leave_type_id']] ?? 0) + $row['credits_deducted'];
            }
            if ($row['fallback_leave_type_id'] && $row['fallback_credits'] > 0) {
                $used[$row['fallback_leave_type_id']] = ($used[$row['fallback_leave_type_id']] ?? 0) + $row['fallback_credits'];
            }
        }

        $adjusted = [];
        foreach ($movements as $movement) {
            $typeId = (int) $movement->leave_type_id;
            $opening = $openings[$employeeId][$typeId] ?? null;
            if ($opening && (int) $movement->id > $opening['ledger_id'] && (string) $movement->effective_date > $opening['as_of']) {
                $adjusted[$typeId] = ($adjusted[$typeId] ?? 0)
                    + (float) $movement->earned_credits - (float) $movement->consumed_credits + (float) $movement->adjustments;
            }
        }

        $typeIds = array_unique(array_merge($earningTypes->pluck('id')->map(fn($id) => (int) $id)->all(), array_map('intval', array_keys($used)), array_keys($adjusted)));
        $missingOpening = false;
        $now = now();

        foreach ($typeIds as $typeId) {
            $opening = $openings[$employeeId][$typeId] ?? null;
            $missingOpening = $missingOpening || !$opening;
            $spent = round((float) ($used[$typeId] ?? 0), 3);
            $change = round((float) ($adjusted[$typeId] ?? 0), 3);

            DB::table('hris.cutoff_leave_balance_snapshots')->insert([
                'cutoff_id' => $cutoffId,
                'employee_id' => $employeeId,
                'leave_type_id' => $typeId,
                'leave_type_code' => $typeCodes[$typeId] ?? null,
                'beginning_balance' => $opening['balance'] ?? 0,
                'leave_earned' => 0,
                'leave_used' => $spent,
                'adjustments' => $change,
                'ending_balance' => $opening ? round($opening['balance'] - $spent + $change, 3) : 0,
                'opening_source' => $opening['source'] ?? 'unavailable',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($missingOpening) {
            $exceptions[] = ['type' => 'no_opening_balance', 'detail' => 'No previous finalized balance or certified opening balance yet.'];
        }
    }

    private function insertPayComponents(int $cutoffId, int $employeeId, ?array $salary, string $start, string $end): void
    {
        $components = $this->payrollServices->getEmployeeDynamicComponents($employeeId, $start, $end, (float) ($salary['amount'] ?? 0));
        $now = now();

        foreach ($components->keyBy('code') as $component) {
            DB::table('hris.cutoff_payroll_components')->insert([
                'cutoff_id' => $cutoffId,
                'employee_id' => $employeeId,
                'payroll_component_id' => $component->payroll_component_id,
                'code' => $component->code,
                'name' => $component->name,
                'type' => $component->type,
                'taxable' => $component->taxable ? 1 : 0,
                'amount' => round((float) $component->amount, 2),
                'source_reference_id' => $component->payroll_component_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** Calendar days of the cutoff within the employee's service. */
    private function qualifyingDays(?object $person, string $start, string $end): float
    {
        $from = Carbon::parse($start);
        $to = Carbon::parse($end);
        if (!empty($person->date_hired) && Carbon::parse($person->date_hired)->gt($from)) {
            $from = Carbon::parse($person->date_hired);
        }
        if (!empty($person->date_resigned) && Carbon::parse($person->date_resigned)->lt($to)) {
            $to = Carbon::parse($person->date_resigned);
        }

        return $to->gte($from) ? (float) ($from->diffInDays($to) + 1) : 0.0;
    }

    private function formatName(object $person): string
    {
        $middle = !empty($person->mname) ? ' ' . mb_substr($person->mname, 0, 1) . '.' : '';
        $suffix = !empty($person->suffix) ? ' ' . $person->suffix : '';

        return strtoupper(trim(($person->lname ?? '') . ', ' . ($person->fname ?? '') . $middle . $suffix));
    }
}
