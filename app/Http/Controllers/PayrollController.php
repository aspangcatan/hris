<?php

namespace App\Http\Controllers;

use App\Services\DTRServices;
use App\Services\PayrollServices;
use App\Services\PermissionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollController extends Controller
{
    protected $dtrServices, $payrollServices;
    protected PermissionService $permissions;

    public function __construct(DTRServices $dtrServices, PayrollServices $payrollServices)
    {
        $this->dtrServices = $dtrServices;
        $this->payrollServices = $payrollServices;
        $this->permissions = app(PermissionService::class);
    }

    public function getPayrollSettings(Request $request)
    {
        try {
            $payroll = DB::table('payroll_settings')->where('userid', $request->userid)->first();
            $components = DB::table('hris.employee_payroll_components as epc')
                ->join('hris.payroll_components as pc', 'pc.id', '=', 'epc.payroll_component_id')
                ->where('epc.userid', $request->userid)
                ->orderBy('pc.sort_order')
                ->select(
                    'pc.code',
                    'pc.name',
                    'pc.type',
                    'pc.taxable',
                    'pc.is_global',
                    'pc.default_amount',
                    'pc.calculation_type',
                    'epc.amount',
                    'epc.amount_override',
                    'epc.rate_percent_override',
                    'epc.is_active',
                    'epc.is_excluded',
                    'epc.effective_from',
                    'epc.effective_to'
                )
                ->get();

            return response()->json([
                'success' => true,
                'data' => $payroll ? (array) $payroll : [],
                'components' => $components ?? []
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function savePayrollSettings(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'salary_grade' => 'required|integer|min:1',
                'step' => 'required|integer|min:1',
                'basic_salary' => 'required|numeric|min:0',
                'effective_date' => 'nullable|date',
            ]);

            $dynamicComponents = $request->input('components', null);
            if ($dynamicComponents !== null && !is_array($dynamicComponents)) {
                $dynamicComponents = [];
            }

            if ($dynamicComponents !== null) {
                $rateError = $this->payrollServices->validateRatePercentOverrides($dynamicComponents);
                if ($rateError) {
                    return response()->json(['message' => $rateError], 422);
                }
            }

            DB::transaction(function () use ($request, $validatedData) {
                DB::table('payroll_settings')->updateOrInsert(
                    ['userid' => $request->userid],
                    [
                        'salary_grade' => $validatedData['salary_grade'],
                        'step' => $validatedData['step'],
                        'basic_salary' => $validatedData['basic_salary'],
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );

                $this->payrollServices->recordSalaryChange(
                    (int) $request->userid,
                    (int) $validatedData['salary_grade'],
                    (int) $validatedData['step'],
                    (float) $validatedData['basic_salary'],
                    $validatedData['effective_date'] ?? null,
                    Auth::id()
                );
            });

            if ($dynamicComponents !== null) {
                $this->syncDynamicComponents($request->userid, $dynamicComponents);
            }

            return response()->json([
                'message' => 'Payroll settings saved successfully.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    private function syncDynamicComponents($userid, array $components): void
    {
        DB::transaction(function () use ($userid, $components) {
            DB::table('hris.employee_payroll_components')
                ->where('userid', $userid)
                ->update([
                    'is_active' => 0,
                    'updated_at' => now(),
                ]);

            foreach ($components as $component) {
                if (empty($component['code']) || empty($component['name']) || empty($component['type'])) {
                    continue;
                }

                $code = strtoupper(trim((string) $component['code']));
                $name = trim((string) $component['name']);
                $type = strtolower(trim((string) $component['type'])) === 'deduction' ? 'deduction' : 'earning';
                $taxable = !empty($component['taxable']);
                $amount = isset($component['amount']) ? (float) $component['amount'] : 0;
                $amountOverride = array_key_exists('amount_override', $component) ? $component['amount_override'] : null;
                $rateOverrideRaw = array_key_exists('rate_percent_override', $component) ? $component['rate_percent_override'] : null;
                $rateOverride = ($rateOverrideRaw === null || $rateOverrideRaw === '') ? null : (float) $rateOverrideRaw;
                $isExcluded = !empty($component['is_excluded']);
                $effectiveFrom = $component['effective_from'] ?? null;
                $effectiveTo = $component['effective_to'] ?? null;
                $isGlobal = !empty($component['is_global']);
                $defaultAmount = isset($component['default_amount']) ? (float) $component['default_amount'] : 0;

                $componentId = DB::table('hris.payroll_components')
                    ->where('code', $code)
                    ->value('id');

                if (!$componentId) {
                    $componentId = DB::table('hris.payroll_components')->insertGetId([
                        'code' => $code,
                        'name' => $name,
                        'type' => $type,
                        'is_global' => $isGlobal ? 1 : 0,
                        'taxable' => $taxable,
                        'default_amount' => $defaultAmount,
                        'is_active' => 1,
                        'sort_order' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('hris.payroll_components')
                        ->where('id', $componentId)
                        ->update([
                            'name' => $name,
                            'type' => $type,
                            'is_global' => $isGlobal ? 1 : 0,
                            'taxable' => $taxable,
                            'default_amount' => $defaultAmount,
                            'updated_at' => now(),
                        ]);
                }

                DB::table('hris.employee_payroll_components')->updateOrInsert(
                    [
                        'payroll_component_id' => $componentId,
                        'userid' => $userid,
                    ],
                    [
                        'amount' => $amount,
                        'amount_override' => $amountOverride,
                        'rate_percent_override' => $rateOverride,
                        'is_active' => $isExcluded ? 0 : 1,
                        'is_excluded' => $isExcluded ? 1 : 0,
                        'effective_from' => $effectiveFrom,
                        'effective_to' => $effectiveTo,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        });
    }

    public function getPayrollComponents()
    {
        try {
            $components = DB::table('hris.payroll_components')
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            return response()->json(['success' => true, 'data' => $components]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function savePayrollComponents(Request $request)
    {
        try {
            $components = $request->input('components', []);
            if (!is_array($components)) {
                return response()->json(['success' => false, 'message' => 'Invalid payload.'], 422);
            }

            $normalized = [];
            foreach ($components as $component) {
                if (empty($component['code']) || empty($component['name']) || empty($component['type'])) {
                    continue;
                }

                $code = strtoupper(trim((string) $component['code']));
                $calculationType = strtolower((string) ($component['calculation_type'] ?? 'fixed')) === 'percentage' ? 'percentage' : 'fixed';
                $baseType = $component['base_type'] ?? null;
                $baseType = in_array($baseType, ['basic_salary', 'component'], true) ? $baseType : null;
                $baseComponentCode = !empty($component['base_component_code']) ? strtoupper(trim((string) $component['base_component_code'])) : null;
                $ratePercent = isset($component['rate_percent']) && $component['rate_percent'] !== '' ? (float) $component['rate_percent'] : null;

                $normalized[$code] = [
                    'name' => trim((string) $component['name']),
                    'type' => strtolower(trim((string) $component['type'])) === 'deduction' ? 'deduction' : 'earning',
                    'taxable' => !empty($component['taxable']),
                    'default_amount' => isset($component['default_amount']) ? (float) $component['default_amount'] : 0,
                    'is_global' => !empty($component['is_global']),
                    'is_active' => array_key_exists('is_active', $component) ? (bool) $component['is_active'] : true,
                    'is_cutoff_adjustable' => !empty($component['is_cutoff_adjustable']),
                    'calculation_type' => $calculationType,
                    'base_type' => $calculationType === 'percentage' ? $baseType : null,
                    'base_component_code' => $calculationType === 'percentage' && $baseType === 'component' ? $baseComponentCode : null,
                    'rate_percent' => $calculationType === 'percentage' ? $ratePercent : null,
                ];
            }

            // Validate the full resulting set (every existing component, with
            // this save's changes overlaid) before writing anything, so a bad
            // formula never lands half-saved. Inactive existing components are
            // included too - deactivated-but-still-referenced bases are valid
            // (they resolve to 0), only truly unknown codes should be rejected.
            $existing = DB::table('hris.payroll_components')
                ->get(['code', 'calculation_type', 'base_type', 'base_component_code', 'rate_percent'])
                ->keyBy('code')
                ->map(fn($row) => (array) $row)
                ->all();

            $componentsByCode = array_merge($existing, $normalized);

            $error = $this->payrollServices->validateComponentFormulas($componentsByCode);
            if ($error) {
                return response()->json(['success' => false, 'message' => $error], 422);
            }

            foreach ($normalized as $code => $component) {
                DB::table('hris.payroll_components')->updateOrInsert(
                    ['code' => $code],
                    [
                        'name' => $component['name'],
                        'type' => $component['type'],
                        'is_global' => $component['is_global'] ? 1 : 0,
                        'taxable' => $component['taxable'],
                        'default_amount' => $component['default_amount'],
                        'calculation_type' => $component['calculation_type'],
                        'base_type' => $component['base_type'],
                        'base_component_code' => $component['base_component_code'],
                        'rate_percent' => $component['rate_percent'],
                        'is_active' => $component['is_active'] ? 1 : 0,
                        'is_cutoff_adjustable' => $component['is_cutoff_adjustable'] ? 1 : 0,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            return response()->json(['success' => true, 'message' => 'Components saved successfully.']);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function savePayroll(Request $request)
    {
        try {
            $validated = $request->validate([
                'title'        => 'required|string|max:255',
                'cutoff_id'    => 'required|integer',
                'payroll_type' => 'nullable|string|max:50',
                'rate_type'    => 'nullable|string|max:50',
                'remarks'      => 'nullable|string',
                'tax_purpose'  => 'nullable|string|max:150',
            ]);

            $masterTable = $this->payrollServices->getCutoffSummaryMasterTableName();
            $master = DB::table($masterTable)->where('id', $validated['cutoff_id'])->first();

            if (!$master) {
                throw ValidationException::withMessages([
                    'cutoff_id' => ['The selected cutoff period does not exist.'],
                ]);
            }
            // Payroll reads a finalized cutoff only (a draft can still change).
            if (strtolower((string) ($master->status ?? 'draft')) !== 'finalized') {
                throw ValidationException::withMessages([
                    'cutoff_id' => [$this->cutoffNotFinalizedMessage($master)],
                ]);
            }
            // Payroll is always a whole month and pays the full monthly basic, so a
            // shorter cutoff would overpay. Partial periods are not handled yet.
            if (!$this->coversWholeMonth($master)) {
                throw ValidationException::withMessages([
                    'cutoff_id' => ["Payroll needs a whole-month cutoff. This one covers only {$master->period_start} to {$master->period_end}."],
                ]);
            }

            DB::table('hris.payrolls')->insert([
                'cutoff_id'         => $master->id,
                'title'             => $validated['title'],
                'start_date'        => $master->period_start,
                'end_date'          => $master->period_end,
                'cutoff_start_date' => $master->period_start,
                'cutoff_end_date'   => $master->period_end,
                'cutoff_title'      => $master->title,
                'payroll_type'      => $validated['payroll_type'] ?? 'Regular',
                'rate_type'         => $validated['rate_type'] ?? 'Non Pro-rated',
                'remarks'           => $validated['remarks'] ?? null,
                'tax_purpose'       => $validated['tax_purpose'] ?? null,
                'status'            => 'draft',
                'prepared_by'       => Auth::id(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            return response()->json(['message' => 'Payroll created successfully.']);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    private function coversWholeMonth(object $master): bool
    {
        $start = \Carbon\Carbon::parse($master->period_start);

        return $start->day === 1 && $start->copy()->endOfMonth()->toDateString() === \Carbon\Carbon::parse($master->period_end)->toDateString();
    }

    private function cutoffNotFinalizedMessage(object $master): string
    {
        $status = strtolower((string) ($master->status ?? 'draft')) ?: 'draft';

        return $status === 'closed'
            ? 'This cutoff is closed - its payroll was already released.'
            : 'This cutoff is still a draft. Finalize it on the Cutoff Summary page before using it for payroll.';
    }

    public function getPayrollTaxRules()
    {
        try {
            $rules = DB::table('hris.payroll_tax_rules')
                ->where('is_active', 1)
                ->orderBy('purpose')
                ->orderBy('min_annual')
                ->get();

            return response()->json(['success' => true, 'data' => $rules]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function savePayrollTaxRules(Request $request)
    {
        try {
            $rules = $request->input('rules', []);
            if (!is_array($rules)) {
                return response()->json(['success' => false, 'message' => 'Invalid payload.'], 422);
            }

            $normalized = [];
            foreach ($rules as $rule) {
                $purpose = trim((string) ($rule['purpose'] ?? ''));
                if ($purpose === '') {
                    continue;
                }
                $maxAnnual = array_key_exists('max_annual', $rule) ? $rule['max_annual'] : null;

                $normalized[] = [
                    'id' => !empty($rule['id']) ? (int) $rule['id'] : null,
                    'purpose' => $purpose,
                    'min_annual' => isset($rule['min_annual']) ? (float) $rule['min_annual'] : 0,
                    'max_annual' => $maxAnnual === '' || $maxAnnual === null ? null : (float) $maxAnnual,
                    'rate_percent' => isset($rule['rate_percent']) ? (float) $rule['rate_percent'] : 0,
                    'tax_mode' => strtolower((string) ($rule['tax_mode'] ?? 'flat')) === 'progressive' ? 'progressive' : 'flat',
                    'is_active' => array_key_exists('is_active', $rule) ? (bool) $rule['is_active'] : true,
                    'sort_order' => isset($rule['sort_order']) ? (int) $rule['sort_order'] : 0,
                    'remarks' => $rule['remarks'] ?? null,
                ];
            }

            // Validate every touched purpose's full resulting active-rule set
            // before writing anything, so a bad entry never lands half-saved.
            $errors = [];
            foreach (collect($normalized)->groupBy('purpose') as $purpose => $incomingForPurpose) {
                $incomingIds = collect($incomingForPurpose)->pluck('id')->filter()->all();

                $existingActive = DB::table('hris.payroll_tax_rules')
                    ->where('purpose', $purpose)
                    ->where('is_active', 1)
                    ->when(!empty($incomingIds), fn($q) => $q->whereNotIn('id', $incomingIds))
                    ->get(['min_annual', 'max_annual', 'tax_mode'])
                    ->map(fn($row) => (array) $row)
                    ->all();

                $incomingActive = collect($incomingForPurpose)
                    ->where('is_active', true)
                    ->map(fn($rule) => [
                        'min_annual' => $rule['min_annual'],
                        'max_annual' => $rule['max_annual'],
                        'tax_mode' => $rule['tax_mode'],
                    ])
                    ->all();

                $resultingSet = array_merge($existingActive, $incomingActive);
                $error = $this->payrollServices->validateTaxRuleSet($purpose, $resultingSet);
                if ($error) {
                    $errors[$purpose] = $error;
                }
            }

            if (!empty($errors)) {
                return response()->json([
                    'success' => false,
                    'message' => implode(' ', $errors),
                    'errors' => $errors,
                ], 422);
            }

            foreach ($normalized as $rule) {
                $payload = [
                    'purpose' => $rule['purpose'],
                    'min_annual' => $rule['min_annual'],
                    'max_annual' => $rule['max_annual'],
                    'rate_percent' => $rule['rate_percent'],
                    'tax_mode' => $rule['tax_mode'],
                    'is_active' => $rule['is_active'] ? 1 : 0,
                    'sort_order' => $rule['sort_order'],
                    'remarks' => $rule['remarks'],
                    'updated_at' => now(),
                ];

                if (!empty($rule['id'])) {
                    DB::table('hris.payroll_tax_rules')
                        ->where('id', $rule['id'])
                        ->update($payload);
                } else {
                    $payload['created_at'] = now();
                    DB::table('hris.payroll_tax_rules')->insert($payload);
                }
            }

            return response()->json(['success' => true, 'message' => 'Tax rules saved successfully.']);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function getPayrollAssignatories()
    {
        try {
            $assignatories = DB::table('payroll_assignatories as pa')
                ->leftJoin('tdh_user.users as u', 'u.id', '=', 'pa.user_id')
                ->where('pa.is_active', 1)
                ->orderBy('pa.order_no')
                ->select('pa.id', 'pa.order_no', 'pa.label', 'pa.user_id', 'pa.is_active', 'u.fname', 'u.lname')
                ->get();

            return response()->json(['success' => true, 'data' => $assignatories]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function savePayrollAssignatories(Request $request)
    {
        try {
            $rows = $request->input('assignatories', []);
            if (!is_array($rows)) {
                return response()->json(['success' => false, 'message' => 'Invalid payload.'], 422);
            }

            $normalized = [];
            $errors = [];
            foreach ($rows as $index => $row) {
                $label = trim((string) ($row['label'] ?? ''));
                $userId = isset($row['user_id']) ? (int) $row['user_id'] : null;

                if ($label === '') {
                    $errors[] = 'Row ' . ($index + 1) . ': label is required.';
                    continue;
                }
                if (!$userId || !DB::table('tdh_user.users')->where('id', $userId)->exists()) {
                    $errors[] = 'Row ' . ($index + 1) . ': a valid assigned user is required.';
                    continue;
                }

                $normalized[] = [
                    'id' => !empty($row['id']) ? (int) $row['id'] : null,
                    'label' => $label,
                    'user_id' => $userId,
                    'order_no' => isset($row['order_no']) ? (int) $row['order_no'] : 0,
                    'is_active' => array_key_exists('is_active', $row) ? (bool) $row['is_active'] : true,
                ];
            }

            if (!empty($errors)) {
                return response()->json(['success' => false, 'message' => implode(' ', $errors)], 422);
            }

            foreach ($normalized as $row) {
                $payload = [
                    'label' => $row['label'],
                    'user_id' => $row['user_id'],
                    'order_no' => $row['order_no'],
                    'is_active' => $row['is_active'] ? 1 : 0,
                    'updated_at' => now(),
                ];

                if (!empty($row['id'])) {
                    DB::table('payroll_assignatories')->where('id', $row['id'])->update($payload);
                } else {
                    $payload['created_at'] = now();
                    DB::table('payroll_assignatories')->insert($payload);
                }
            }

            return response()->json(['success' => true, 'message' => 'Assignatories saved successfully.']);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function searchPayrollAssignatoryUsers(Request $request)
    {
        try {
            $search = trim((string) $request->query('q', ''));
            if ($search === '') {
                return response()->json(['success' => true, 'data' => []]);
            }

            $users = DB::table('tdh_user.users as u')
                ->select('u.id', 'u.fname', 'u.lname')
                ->where(function ($q) use ($search) {
                    $q->where('u.fname', 'like', "%{$search}%")
                        ->orWhere('u.lname', 'like', "%{$search}%");
                })
                ->orderBy('u.lname')
                ->orderBy('u.fname')
                ->limit(10)
                ->get();

            return response()->json(['success' => true, 'data' => $users]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function getCutoffSummaries(Request $request)
    {
        try {
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');

            $table = $this->payrollServices->getCutoffSummaryTableName();
            $masterTable = $this->payrollServices->getCutoffSummaryMasterTableName();
            $query = DB::table($table . ' as cs')
                ->leftJoin($masterTable . ' as cm', 'cm.id', '=', 'cs.cutoff_id')
                ->leftJoin('tdh_user.users as u', 'u.id', '=', 'cs.employee_id')
                ->select(
                    'cs.*',
                    'cm.title as remarks',
                    'cm.period_start',
                    'cm.period_end',
                    'u.fname',
                    'u.mname',
                    'u.lname',
                    'u.suffix'
                )
                ->orderByDesc('cm.period_start')
                ->orderByDesc('cs.created_at');

            if (!empty($startDate) && !empty($endDate)) {
                $query->whereDate('cm.period_start', '>=', $startDate)
                    ->whereDate('cm.period_end', '<=', $endDate);
            } elseif (!empty($startDate)) {
                $query->whereDate('cm.period_start', '>=', $startDate);
            } elseif (!empty($endDate)) {
                $query->whereDate('cm.period_end', '<=', $endDate);
            }

            $results = $query->get();

            return response()->json(['success' => true, 'data' => $results]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    /**
     * Shared Create/Edit Cutoff input rules. A cutoff covers one calendar month
     * at most (half-month ranges are fine), and only the last two closed months
     * can be generated: payroll for a month is computed from the DTR of the
     * month before it (October pay uses September's cutoff, made in November),
     * so HR may pick last month or the one before it. Resigned and retired
     * employees are never included.
     *
     * @return array{0: array, 1: int[]} [validated input, left-out separated ids]
     */
    private function validateCutoffInput(Request $request): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'payroll_date' => 'nullable|date',
            'remarks' => 'nullable|string|max:1000',
            'employees' => 'required|array|min:1',
            'employees.*' => 'required|integer',
        ]);
        $validated['title'] = trim($validated['title']);

        $start = \Carbon\Carbon::parse($validated['start_date']);
        $end = \Carbon\Carbon::parse($validated['end_date']);
        if ($start->format('Y-m') !== $end->format('Y-m')) {
            throw ValidationException::withMessages([
                'end_date' => 'The start and end dates must be in the same month.',
            ]);
        }
        $previousMonth = now()->startOfMonth()->subMonth();
        $earliestMonth = $previousMonth->copy()->subMonth();
        if (!in_array($start->format('Y-m'), [$earliestMonth->format('Y-m'), $previousMonth->format('Y-m')], true)) {
            throw ValidationException::withMessages([
                'start_date' => 'Cutoffs can only be generated for the previous two months ('
                    . $earliestMonth->format('F Y') . ' or ' . $previousMonth->format('F Y') . ').',
            ]);
        }

        // Separated before this cutoff starts, or with no separation date on file.
        // Someone who left during or after the period stays in (early exit).
        $separated = DB::table('hris.employees')
            ->whereIn('user_id', $validated['employees'])
            ->whereIn('employee_type', ['Resigned', 'Retired'])
            ->where(function ($query) use ($validated) {
                $query->whereNull('date_resigned')->orWhere('date_resigned', '<', $validated['start_date']);
            })
            ->pluck('user_id')
            ->map(fn($id) => (int) $id)
            ->all();
        $validated['employees'] = array_values(array_unique(array_diff(array_map('intval', $validated['employees']), $separated)));
        if (empty($validated['employees'])) {
            throw ValidationException::withMessages([
                'employees' => 'All selected employees are resigned or retired. Select at least one active employee.',
            ]);
        }

        return [$validated, $separated];
    }

    /**
     * The employees this request should generate: the "batch" sent by the page
     * (limited to the validated, non-separated employees), or all of them.
     */
    private function cutoffBatch(Request $request, array $employees): array
    {
        $batch = $request->input('batch');
        if (!is_array($batch)) {
            return $employees;
        }

        return array_values(array_intersect($employees, array_map('intval', $batch)));
    }

    /** Payroll date, remarks, and whether the period is a whole month. */
    private function cutoffPeriodSettings(array $validated): array
    {
        $isWholeMonth = $this->coversWholeMonth((object) ["period_start" => $validated["start_date"], "period_end" => $validated["end_date"]]);

        return [
            "payroll_date" => $validated["payroll_date"] ?? null,
            "remarks" => isset($validated["remarks"]) ? trim($validated["remarks"]) : null,
            "cutoff_type" => $isWholeMonth ? "whole_month" : "partial",
        ];
    }

    private function cutoffSavedMessage(string $verb, int $count, array $separated): string
    {
        $message = "Cutoff summaries {$verb} for {$count}" . ($count === 1 ? ' employee.' : ' employees.');
        if (!empty($separated)) {
            $message .= ' ' . count($separated) . ' resigned/retired '
                . (count($separated) === 1 ? 'employee was' : 'employees were') . ' left out.';
        }

        return $message;
    }

    private function cutoffValidationResponse(ValidationException $e)
    {
        return response()->json([
            'success' => false,
            // Show the actual reason (e.g. "previous month only") on the page.
            'message' => collect($e->errors())->flatten()->first() ?? 'Validation failed.',
            'errors' => $e->errors(),
        ], 422);
    }

    /** A draft cutoff and its employees, to fill the Create Cutoff modal for editing. */
    public function editCutoffSummaries($cutoffId)
    {
        try {
            $masterTable = $this->payrollServices->getCutoffSummaryMasterTableName();
            $master = DB::table($masterTable)->where('id', (int) $cutoffId)->first();
            if (!$master) {
                return response()->json(['success' => false, 'message' => 'Cutoff not found.'], 404);
            }

            $employees = DB::table($this->payrollServices->getCutoffSummaryTableName() . ' as cs')
                ->leftJoin('tdh_user.users as u', 'u.id', '=', 'cs.employee_id')
                ->leftJoin('tdh_user.designation as d', 'u.designation', '=', 'd.id')
                ->leftJoin('hris.employees as e', 'e.user_id', '=', 'cs.employee_id')
                ->where('cs.cutoff_id', $master->id)
                ->orderBy('u.lname')
                ->get(['cs.employee_id as user_id', 'u.fname', 'u.mname', 'u.lname', 'u.suffix',
                    'd.description as designation', 'e.employee_type']);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => (int) $master->id,
                    'title' => $master->title,
                    'period_start' => $master->period_start,
                    'period_end' => $master->period_end,
                    'payroll_date' => $master->payroll_date ?? null,
                    'remarks' => $master->remarks ?? null,
                    'status' => strtolower((string) ($master->status ?? 'draft')) ?: 'draft',
                    'employees' => $employees,
                ],
            ]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    /**
     * Edit a DRAFT cutoff: new title/dates and employee list. Ticked employees
     * are regenerated for the (possibly new) period; unticked ones are removed.
     */
    public function updateCutoffSummaries(Request $request, $cutoffId)
    {
        try {
            $masterTable = $this->payrollServices->getCutoffSummaryMasterTableName();
            $table = $this->payrollServices->getCutoffSummaryTableName();
            $master = DB::table($masterTable)->where('id', (int) $cutoffId)->first();
            if (!$master) {
                return response()->json(['success' => false, 'message' => 'Cutoff not found.'], 404);
            }
            if ((strtolower((string) ($master->status ?? 'draft')) ?: 'draft') !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only a draft cutoff can be edited. Reopen it first if it really has to change.',
                ], 422);
            }

            [$validated, $separated] = $this->validateCutoffInput($request);

            // Title + dates identify a cutoff; another one can't have the same.
            $duplicate = DB::table($masterTable)
                ->where('id', '<>', $master->id)
                ->where('period_start', $validated['start_date'])
                ->where('period_end', $validated['end_date'])
                ->where('title', $validated['title'])
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages([
                    'title' => 'Another cutoff already has this title and these dates. Use a different title.',
                ]);
            }

            $batch = $this->cutoffBatch($request, $validated['employees']);
            @set_time_limit(300);

            DB::transaction(function () use ($validated, $master, $masterTable, $table, $batch) {
                DB::table($masterTable)->where('id', $master->id)->update([
                    'title' => $validated['title'],
                    'period_start' => $validated['start_date'],
                    'period_end' => $validated['end_date'],
                    'updated_at' => now(),
                ] + $this->cutoffPeriodSettings($validated));

                // Employees taken out of the cutoff.
                $removed = DB::table($table)->where('cutoff_id', $master->id)
                    ->whereNotIn('employee_id', $validated['employees'])->pluck('employee_id');
                if ($removed->isNotEmpty()) {
                    DB::table('hris.cutoff_summary_days')->where('cutoff_id', $master->id)
                        ->whereIn('employee_id', $removed)->delete();
                    DB::table($table)->where('cutoff_id', $master->id)
                        ->whereIn('employee_id', $removed)->delete();
                }

                // Regenerate everyone kept or added for the saved period
                // (generation finds this cutoff by its new title + dates).
                // The page sends large cutoffs in batches; each request generates its batch only.
                foreach ($batch as $employeeId) {
                    $this->payrollServices->generateCutoffSummary(
                        $employeeId,
                        $validated['start_date'],
                        $validated['end_date'],
                        $validated['title'],
                        Auth::id()
                    );
                }
            });

            return response()->json([
                'success' => true,
                'message' => $this->cutoffSavedMessage('updated', count($validated['employees']), $separated),
                'processed' => count($batch),
                'cutoff_id' => (int) $master->id,
            ]);
        } catch (ValidationException $e) {
            return $this->cutoffValidationResponse($e);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function createCutoffSummaries(Request $request)
    {
        try {
            [$validated, $separated] = $this->validateCutoffInput($request);

            $table = $this->payrollServices->getCutoffSummaryTableName();
            $master = $this->payrollServices->getOrCreateCutoffMaster(
                $validated['start_date'],
                $validated['end_date'],
                $validated['title'],
                Auth::id()
            );
            // Only a draft cutoff can be regenerated; a finalized one has to be
            // reopened first, and a closed one never changes.
            $status = strtolower((string) ($master->status ?? 'draft')) ?: 'draft';
            if ($status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => $status === 'closed'
                        ? 'This cutoff is closed (its payroll was released) and can no longer be regenerated.'
                        : ($status === 'finalized'
                            ? 'This cutoff is finalized. Reopen it first if it really has to be regenerated.'
                            : 'This cutoff is under review or being processed. Send it back to draft first.'),
                ], 422);
            }
            $locked = DB::table($table)
                ->where('cutoff_id', $master->id)
                ->whereIn('employee_id', $validated['employees'])
                ->where('is_locked', 1)
                ->pluck('employee_id')
                ->toArray();

            if (!empty($locked)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cutoff period is locked for some employees.',
                    'locked_employee_ids' => $locked,
                ], 422);
            }

            $batch = $this->cutoffBatch($request, $validated['employees']);
            @set_time_limit(300);

            DB::transaction(function () use ($validated, $master, $batch) {
                DB::table($this->payrollServices->getCutoffSummaryMasterTableName())->where('id', $master->id)
                    ->update($this->cutoffPeriodSettings($validated));

                // The page sends large cutoffs in batches; each request generates its batch only.
                foreach ($batch as $employeeId) {
                    $this->payrollServices->generateCutoffSummary(
                        $employeeId,
                        $validated['start_date'],
                        $validated['end_date'],
                        $validated['title'],
                        Auth::id()
                    );
                }
            });

            return response()->json([
                'success' => true,
                'message' => $this->cutoffSavedMessage('generated', count($validated['employees']), $separated),
                'processed' => count($batch),
                'cutoff_id' => (int) $master->id,
            ]);
        } catch (ValidationException $e) {
            return $this->cutoffValidationResponse($e);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function getCutoffPeriods()
    {
        try {
            $table = $this->payrollServices->getCutoffSummaryMasterTableName();
            $summaryTable = $this->payrollServices->getCutoffSummaryTableName();
            $periods = DB::table($table . ' as cm')
                ->select('cm.id', 'cm.period_start', 'cm.period_end', 'cm.title', 'cm.status')
                ->selectSub(
                    DB::table($summaryTable . ' as cs')->selectRaw('COUNT(*)')->whereColumn('cs.cutoff_id', 'cm.id'),
                    'employees'
                )
                ->orderByDesc('cm.period_start')
                ->orderByDesc('cm.id')
                ->get();

            return response()->json(['success' => true, 'data' => $periods]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function getCutoffSummaryPreview(Request $request)
    {
        try {
            // Rows are looked up by cutoff ID: two cutoffs can share the same
            // dates (e.g. one per office), so dates alone would mix them.
            $cutoffId = (int) $request->query('cutoff_id');
            if ($cutoffId <= 0) {
                return response()->json(['success' => false, 'message' => 'Cutoff ID is required.'], 422);
            }
            $employees = $request->query('employees', []);

            if (is_string($employees)) {
                $employees = array_filter(explode(',', $employees));
            }

            $table = $this->payrollServices->getCutoffSummaryTableName();
            $masterTable = $this->payrollServices->getCutoffSummaryMasterTableName();
            $query = DB::table($table . ' as cs')
                ->leftJoin($masterTable . ' as cm', 'cm.id', '=', 'cs.cutoff_id')
                ->leftJoin('tdh_user.users as u', 'u.id', '=', 'cs.employee_id')
                ->leftJoin('hris.cutoff_employee_snapshots as ces', function ($join) {
                    $join->on('ces.cutoff_id', '=', 'cs.cutoff_id')->on('ces.employee_id', '=', 'cs.employee_id');
                })
                ->select(
                    'cs.*',
                    'ces.id as snapshot_id',
                    'ces.payroll_eligible',
                    'ces.credit_eligible',
                    'ces.exclusion_reason',
                    'cm.title as remarks',
                    'cm.period_start',
                    'cm.period_end',
                    'u.fname',
                    'u.mname',
                    'u.lname',
                    'u.suffix'
                );

            $query->where('cs.cutoff_id', $cutoffId);
            if (!empty($employees)) {
                $query->whereIn('cs.employee_id', $employees);
            }

            $results = $query->orderBy('u.lname')->get();

            return response()->json(['success' => true, 'data' => $results]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    /** Earnings and deductions set for one employee, as a reference before entering adjustments. */
    public function getEmployeeComponentReference($payroll_id, $employee_id)
    {
        try {
            $payroll = $this->payrollServices->getPayrollPeriod($payroll_id);
            if (!$payroll) {
                return response()->json(['success' => false, 'message' => 'Payroll not found.'], 404);
            }

            return response()->json(['success' => true] + $this->payrollServices->employeeComponentReference($payroll_id, (int) $employee_id));
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function getCutoffEmployeesForPayroll($payroll_id)
    {
        try {
            $payroll = $this->payrollServices->getPayrollPeriod($payroll_id);
            if (!$payroll || !$payroll->cutoff_id) {
                return response()->json(['success' => false, 'message' => 'Payroll has no cutoff assigned.'], 422);
            }

            $cutoffTable = $this->payrollServices->getCutoffSummaryTableName();

            $employees = DB::table($cutoffTable . ' as cs')
                ->join('tdh_user.users as u', 'u.id', '=', 'cs.employee_id')
                ->leftJoin('hris.cutoff_employee_snapshots as ces', function ($join) {
                    $join->on('ces.cutoff_id', '=', 'cs.cutoff_id')->on('ces.employee_id', '=', 'cs.employee_id');
                })
                ->where('cs.cutoff_id', $payroll->cutoff_id)
                ->where(function ($q) {
                    $q->whereNull('ces.id')->orWhere('ces.payroll_eligible', 1);
                })
                ->orderBy('u.lname')
                ->get(['cs.employee_id', 'u.fname', 'u.mname', 'u.lname', 'u.suffix']);

            // Basic Pay is a fixed, non-component adjustment; every other
            // column is whatever is currently flagged is_cutoff_adjustable.
            $adjustableComponents = $this->payrollServices->getCutoffAdjustableComponents();
            $adjustmentCodes = $adjustableComponents
                ->map(fn($component) => $component->code . '_ADJUSTMENT')
                ->push('SALARY_ADJUSTMENT')
                ->all();

            $savedAdjustments = DB::table('hris.payroll_employees as pe')
                ->join('hris.payroll_employee_components as pec', 'pec.payroll_employee_id', '=', 'pe.id')
                ->where('pe.payroll_id', $payroll_id)
                ->whereIn('pec.code', $adjustmentCodes)
                ->get(['pe.employee_id', 'pec.code', 'pec.amount'])
                ->groupBy('employee_id');

            $employees = $employees->map(function ($item) use ($savedAdjustments, $adjustableComponents) {
                $byCode = $savedAdjustments->get($item->employee_id, collect())->pluck('amount', 'code');
                $item->salary_adjustment = (float) ($byCode['SALARY_ADJUSTMENT'] ?? 0);

                $adjustments = [];
                foreach ($adjustableComponents as $component) {
                    $adjustments[$component->code] = (float) ($byCode[$component->code . '_ADJUSTMENT'] ?? 0);
                }
                $item->adjustments = $adjustments;

                return $item;
            });

            return response()->json([
                'success' => true,
                'columns' => $adjustableComponents->values(),
                'data' => $employees,
            ]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function getPayroll()
    {
        try {
            $masterTable = $this->payrollServices->getCutoffSummaryMasterTableName();

            $payrolls = DB::table('payrolls as p')
                ->leftJoin('tdh_user.users as u', 'u.id', '=', 'p.prepared_by')
                ->leftJoin($masterTable . ' as cm', 'cm.id', '=', 'p.cutoff_id')
                ->select(
                    'p.id',
                    'p.title',
                    'p.start_date',
                    'p.end_date',
                    'p.cutoff_id',
                    'p.cutoff_start_date',
                    'p.cutoff_end_date',
                    'p.cutoff_title',
                    'p.payroll_type',
                    'p.rate_type',
                    'p.remarks',
                    'p.status',
                    'p.prepared_by',
                    'p.created_at',
                    'u.fname',
                    'u.mname',
                    'u.lname',
                    'u.suffix',
                    DB::raw('COALESCE(cm.title, p.cutoff_title) as cutoff_label')
                )
                ->orderByDesc('p.created_at')
                ->get();

            $pendingApprovalMap = $this->getPendingApprovalMapForCurrentUser();

            $payrolls = $payrolls->map(function ($item) use ($pendingApprovalMap) {
                $status = strtolower((string) ($item->status ?? 'draft'));
                $middleInitial = !empty($item->mname) ? strtoupper(substr($item->mname, 0, 1)) . '.' : '';
                $item->prepared_by_name = trim(implode(' ', array_filter([
                    $item->lname ? $item->lname . ',' : null,
                    $item->fname,
                    $middleInitial,
                    $item->suffix,
                ])));

                $item->status = $status;
                $item->payroll_type = $item->payroll_type ?: 'Regular';
                $item->rate_type = $item->rate_type ?: 'Non Pro-rated';
                $item->can_assign = $this->canAssignPayroll($item);
                $item->can_submit = $this->canSubmitPayroll($item);
                $item->can_approve = isset($pendingApprovalMap[$item->id]);
                $item->pending_approval_label = $item->can_approve ? $pendingApprovalMap[$item->id]->label : null;
                $item->can_release = $this->permissions->can(Auth::id(), 'payroll.manage') && $status === 'approved';
                $item->can_generate_payslip = in_array($status, ['approved', 'released'], true);

                return $item;
            });

            return response()->json($payrolls);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function savePayrollEmployee(Request $request)
    {
        try {
            $validated = $request->validate([
                'payroll_id' => 'required|integer|exists:payrolls,id',
                'employees' => 'required|array|min:1',
                'employees.*.userid' => 'required|integer',
                'employees.*.salary_adjustment' => 'nullable|numeric',
                'employees.*.adjustments' => 'nullable|array',
            ]);

            $period = $this->payrollServices->getPayrollPeriod($validated['payroll_id']);
            if (!$period) {
                return response()->json(['error' => "Payroll doesn't exist."], 404);
            }

            $masterTable = $this->payrollServices->getCutoffSummaryMasterTableName();

            if ($period->cutoff_id) {
                $cutoffMaster = DB::table($masterTable)->where('id', $period->cutoff_id)->first();
            } else {
                // Backward compat: old payrolls without cutoff_id use date matching
                $cutoffStart = $period->cutoff_start_date ?? $period->start_date;
                $cutoffEnd   = $period->cutoff_end_date ?? $period->end_date;
                $cutoffTitle = $period->cutoff_title ?? null;
                $cutoffMaster = $this->payrollServices->getCutoffMasterByPeriod($cutoffStart, $cutoffEnd, $cutoffTitle);
            }

            if (!$cutoffMaster) {
                return response()->json([
                    'error' => 'Cutoff master record not found for this payroll.',
                ], 422);
            }

            if (strtolower((string) ($cutoffMaster->status ?? 'draft')) !== 'finalized') {
                return response()->json(['error' => $this->cutoffNotFinalizedMessage($cutoffMaster)], 422);
            }

            $currentStatus = strtolower((string) ($period->status ?? 'draft'));
            if (in_array($currentStatus, ['for_approval', 'approved', 'released'], true)) {
                return response()->json(['error' => 'Payroll is already in approval/released state and cannot be recomputed.'], 422);
            }

            $employeeIds = array_map(function ($employee) {
                return $employee['userid'];
            }, $validated['employees']);

            $cutoffTable = $this->payrollServices->getCutoffSummaryTableName();
            $existingCutoffs = DB::table($cutoffTable)
                ->where('cutoff_id', $cutoffMaster->id)
                ->whereIn('employee_id', $employeeIds)
                ->pluck('employee_id')
                ->toArray();

            // Excluded at snapshot time (no salary on file): can't be paid from this cutoff.
            $ineligible = DB::table('hris.cutoff_employee_snapshots')
                ->where('cutoff_id', $cutoffMaster->id)
                ->whereIn('employee_id', $employeeIds)
                ->where('payroll_eligible', 0)
                ->pluck('employee_name');
            if ($ineligible->isNotEmpty()) {
                return response()->json([
                    'error' => 'These employees have no salary in this cutoff and cannot be computed: ' . $ineligible->implode('; ') . '. Remove them, or add the salary and regenerate the cutoff.',
                ], 422);
            }

            $missing = array_diff($employeeIds, $existingCutoffs);
            if (!empty($missing)) {
                return response()->json([
                    'error' => 'Missing cutoff summaries for selected employees.',
                    'missing_employee_ids' => array_values($missing),
                ], 422);
            }

            // The finalized rows of THIS cutoff (by cutoff_id - another cutoff
            // can share the same dates). All of them exist: checked above.
            $summaries = DB::table($cutoffTable)
                ->where('cutoff_id', $cutoffMaster->id)
                ->whereIn('employee_id', $employeeIds)
                ->get()
                ->keyBy('employee_id');

            DB::transaction(function () use ($validated, $employeeIds, $cutoffMaster, $summaries) {
                $this->payrollServices->deletePayrollEmployees($validated['payroll_id']);
                DB::table('payroll_approvals')->where('payroll_id', $validated['payroll_id'])->delete();

                foreach ($validated['employees'] as $employee) {
                    $summary = $summaries->get($employee['userid']);

                    // Late + undertime + personal pass slip minutes inside working
                    // hours are all charged to salary (client decision 2026-11-11);
                    // leave credits are reduced by LWOP only. Slip minutes never
                    // overlap late/undertime (counted inside the worked span only).
                    $totalLateUndertimeMinutes = ((int) ($summary->late_minutes ?? 0))
                        + ((int) ($summary->undertime_minutes ?? 0))
                        + ((int) ($summary->approved_pass_slips_minutes ?? 0));
                    $totalAbsentDays = (int) ($summary->days_absent ?? 0);
                    $totalWorkingDays = (int) ($summary->total_working_days ?? 0);

                    $this->payrollServices->savePayrollEmployee(
                        $validated['payroll_id'],
                        $employee,
                        $totalLateUndertimeMinutes,
                        $totalAbsentDays,
                        $totalWorkingDays,
                        (float) ($summary->lwop_days ?? 0)
                    );
                }

                DB::table('payrolls')
                    ->where('id', $validated['payroll_id'])
                    ->update([
                        'status' => 'computed',
                        'reviewed_by' => null,
                        'approved_at' => null,
                        'released_at' => null,
                        'updated_at' => now(),
                    ]);

                $cutoffTable = $this->payrollServices->getCutoffSummaryTableName();
                DB::table($cutoffTable)
                    ->where('cutoff_id', $cutoffMaster->id)
                    ->whereIn('employee_id', $employeeIds)
                    ->update([
                        'is_locked' => 1,
                        'locked_by' => Auth::id(),
                        'locked_at' => now(),
                        'updated_at' => now(),
                    ]);
            });

            return response()->json(['message' => 'Payroll computation completed.']);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function submitForApproval(Request $request)
    {
        try {
            $validated = $request->validate([
                'payroll_id' => 'required|integer|exists:payrolls,id',
            ]);

            $payroll = DB::table('payrolls')->where('id', $validated['payroll_id'])->first();
            if (!$payroll) {
                return response()->json(['error' => 'Payroll not found.'], 404);
            }

            $isAdmin = $this->permissions->can(Auth::id(), 'payroll.manage');
            if ((int) $payroll->prepared_by !== (int) Auth::id() && !$isAdmin) {
                return response()->json(['error' => 'Only the preparer can submit this payroll for approval.'], 403);
            }

            $status = strtolower((string) ($payroll->status ?? 'draft'));
            if (!in_array($status, ['draft', 'computed', 'rejected'], true)) {
                return response()->json(['error' => 'Payroll cannot be submitted in its current status.'], 422);
            }

            if ($this->payrollServices->getPayrollEmployeeCount($validated['payroll_id']) === 0) {
                return response()->json(['error' => 'Please compute payroll employees before submitting for approval.'], 422);
            }

            $assignatories = DB::table('payroll_assignatories')
                ->where('is_active', 1)
                ->orderBy('order_no')
                ->get(['label', 'user_id']);

            if ($assignatories->isEmpty()) {
                return response()->json(['error' => 'No approval assignatories are configured. Configure them in Payroll Assignatories settings before submitting.'], 422);
            }

            DB::transaction(function () use ($validated, $assignatories) {
                DB::table('payroll_approvals')->where('payroll_id', $validated['payroll_id'])->delete();

                $rows = [];
                foreach ($assignatories->values() as $index => $assignatory) {
                    $rows[] = [
                        'payroll_id' => $validated['payroll_id'],
                        'approver_id' => $assignatory->user_id,
                        'label' => $assignatory->label,
                        'status' => 'pending',
                        'order_no' => $index + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                DB::table('payroll_approvals')->insert($rows);

                DB::table('payrolls')
                    ->where('id', $validated['payroll_id'])
                    ->update([
                        'status' => 'for_approval',
                        'reviewed_by' => null,
                        'approved_at' => null,
                        'released_at' => null,
                        'updated_at' => now(),
                    ]);
            });

            return response()->json(['message' => 'Payroll submitted for approval.']);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function approvalAction(Request $request)
    {
        try {
            $validated = $request->validate([
                'payroll_id' => 'required|integer|exists:payrolls,id',
                'status' => 'required|string|in:approved,rejected',
                'remarks' => 'nullable|string',
            ]);

            if ($validated['status'] === 'rejected' && empty(trim((string) ($validated['remarks'] ?? '')))) {
                return response()->json(['error' => 'Remarks are required when rejecting payroll.'], 422);
            }

            $payroll = DB::table('payrolls')->where('id', $validated['payroll_id'])->first();
            if (!$payroll) {
                return response()->json(['error' => 'Payroll not found.'], 404);
            }

            if (strtolower((string) $payroll->status) !== 'for_approval') {
                return response()->json(['error' => 'Payroll is not currently for approval.'], 422);
            }

            $pendingApproval = $this->getUserPendingApproval($validated['payroll_id']);
            if (!$pendingApproval) {
                return response()->json(['error' => 'No pending approval action is assigned to your account.'], 403);
            }

            DB::transaction(function () use ($validated, $pendingApproval) {
                DB::table('payroll_approvals')
                    ->where('id', $pendingApproval->id)
                    ->update([
                        'status' => $validated['status'],
                        'remarks' => $validated['remarks'] ?? null,
                        'acted_at' => now(),
                        'updated_at' => now(),
                    ]);

                if ($validated['status'] === 'rejected') {
                    DB::table('payroll_approvals')
                        ->where('payroll_id', $validated['payroll_id'])
                        ->where('status', 'pending')
                        ->update([
                            'status' => 'rejected',
                            'remarks' => 'Skipped due to prior rejection.',
                            'acted_at' => now(),
                            'updated_at' => now(),
                        ]);

                    DB::table('payrolls')
                        ->where('id', $validated['payroll_id'])
                        ->update([
                            'status' => 'rejected',
                            'reviewed_by' => Auth::id(),
                            'updated_at' => now(),
                        ]);

                    return;
                }

                $pendingCount = DB::table('payroll_approvals')
                    ->where('payroll_id', $validated['payroll_id'])
                    ->where('status', 'pending')
                    ->count();

                if ($pendingCount === 0) {
                    DB::table('payrolls')
                        ->where('id', $validated['payroll_id'])
                        ->update([
                            'status' => 'approved',
                            'reviewed_by' => Auth::id(),
                            'approved_at' => now(),
                            'updated_at' => now(),
                        ]);
                }
            });

            $message = $validated['status'] === 'approved'
                ? 'Payroll approval recorded.'
                : 'Payroll rejected successfully.';

            return response()->json(['message' => $message]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function releasePayroll(Request $request)
    {
        try {
            $validated = $request->validate([
                'payroll_id' => 'required|integer|exists:payrolls,id',
            ]);

            if (!$this->permissions->can(Auth::id(), 'payroll.manage')) {
                return response()->json(['error' => 'You are not allowed to release payroll.'], 403);
            }

            $payroll = DB::table('payrolls')->where('id', $validated['payroll_id'])->first();
            if (!$payroll) {
                return response()->json(['error' => 'Payroll not found.'], 404);
            }

            if (strtolower((string) ($payroll->status ?? '')) !== 'approved') {
                return response()->json(['error' => 'Only approved payroll can be released.'], 422);
            }

            $closed = DB::transaction(function () use ($validated, $payroll) {
                DB::table('payrolls')
                    ->where('id', $validated['payroll_id'])
                    ->update([
                        'status' => 'released',
                        'reviewed_by' => Auth::id(),
                        'released_at' => now(),
                        'updated_at' => now(),
                    ]);

                // The cutoff closes once every payroll that uses it is released.
                return $payroll->cutoff_id
                    ? app(\App\Services\CutoffLifecycleService::class)->closeIfAllPayrollsReleased((int) $payroll->cutoff_id, (int) Auth::id())
                    : false;
            });

            return response()->json(['message' => 'Payroll released successfully.' . ($closed ? ' Its cutoff is now closed.' : '')]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function getPayrollEmployee(Request $request)
    {
        try {
            $employees = $this->payrollServices->getPayrollEmployee($request->payroll_id);
            $breakdown = $this->getComponentBreakdown($employees);

            return response()->json([
                'employees' => $employees,
                'earningComponents' => $breakdown['earning'],
                'deductionComponents' => $breakdown['deduction'],
                'componentAmounts' => $breakdown['amounts'],
            ]);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    /** Earning and deduction columns from the lines saved with the payroll (see PayrollServices::payrollComponentColumns). */
    private function getComponentBreakdown($employees)
    {
        return $this->payrollServices->payrollComponentColumns($employees);
    }

    public function export($payroll_id)
    {
        $payroll = $this->payrollServices->getPayrollPeriod($payroll_id);
        if (!$payroll) {
            return back()->with('error', 'Payroll not found.');
        }

        $employees = $this->payrollServices->getPayrollEmployee($payroll_id);
        if ($employees->isEmpty()) {
            return back()->with('error', 'No employees found for this payroll.');
        }

        // The folder is created when missing, and every export gets its own file so two
        // people exporting at the same time never overwrite each other's download.
        $directory = storage_path('app' . DIRECTORY_SEPARATOR . 'exports');
        \Illuminate\Support\Facades\File::ensureDirectoryExists($directory);
        $exportPath = $directory . DIRECTORY_SEPARATOR . 'payroll_' . $payroll->id . '_' . uniqid() . '.xlsx';

        try {
            $this->payrollServices->exportRegularPayroll($employees, $exportPath, $payroll);
        } catch (\Throwable $exception) {
            @unlink($exportPath);
            return back()->with('error', 'Export failed: ' . $exception->getMessage());
        }

        $downloadName = 'payroll_' . trim(preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $payroll->title), '_') . '.xlsx';

        return response()->download($exportPath, $downloadName)->deleteFileAfterSend(true);
    }

    public function payslip($payroll_id)
    {
        try {
            $payroll = $this->payrollServices->getPayrollPeriod($payroll_id);
            if (!$payroll) {
                return back()->with('error', 'Payroll not found.');
            }

            $employees = $this->payrollServices->getPayrollEmployee($payroll_id);
            if ($employees->isEmpty()) {
                return back()->with('error', 'No employees found for this payroll.');
            }

            // DomPDF, like the app's other PDFs: it runs inside PHP, with no program to install.
            $pdf = Pdf::loadView('pdf.payroll_payslip', [
                'payroll' => $payroll,
                'employees' => $employees,
                'letterhead' => $this->letterheadDataUri(),
            ])->setPaper('a4', 'portrait');

            $safeTitle = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $payroll->title);
            return $pdf->download('payslip_' . $safeTitle . '.pdf');
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    /** The letterhead embedded in the page, so the PDF never has to fetch it over the web. */
    private function letterheadDataUri(): ?string
    {
        $path = public_path('img' . DIRECTORY_SEPARATOR . 'letter_head_banner.png');

        return is_file($path) ? 'data:image/png;base64,' . base64_encode(file_get_contents($path)) : null;
    }

    private function canAssignPayroll($payroll)
    {
        $status = strtolower((string) ($payroll->status ?? 'draft'));
        $isOwner = (int) $payroll->prepared_by === (int) Auth::id();
        $isAdmin = $this->permissions->can(Auth::id(), 'payroll.manage');

        return ($isOwner || $isAdmin) && in_array($status, ['draft', 'computed', 'rejected'], true);
    }

    private function canSubmitPayroll($payroll)
    {
        $status = strtolower((string) ($payroll->status ?? 'draft'));
        $isOwner = (int) $payroll->prepared_by === (int) Auth::id();
        $isAdmin = $this->permissions->can(Auth::id(), 'payroll.manage');

        return ($isOwner || $isAdmin) && in_array($status, ['draft', 'computed', 'rejected'], true);
    }

    private function getPendingApprovalMapForCurrentUser()
    {
        $accessRight = strtoupper((string) session('access_rights'));
        $userId = Auth::id();

        $query = DB::table('payroll_approvals as pa')
            ->where('pa.status', 'pending')
            ->whereRaw('pa.order_no = (SELECT MIN(px.order_no) FROM payroll_approvals as px WHERE px.payroll_id = pa.payroll_id AND px.status = ?)', ['pending'])
            ->select('pa.id', 'pa.payroll_id', 'pa.label', 'pa.order_no');

        if ($accessRight !== 'ADMIN') {
            $query->where('pa.approver_id', $userId);
        }

        return $query->get()->keyBy('payroll_id')->toArray();
    }

    private function getUserPendingApproval($payrollId)
    {
        $accessRight = strtoupper((string) session('access_rights'));
        $userId = Auth::id();

        $query = DB::table('payroll_approvals as pa')
            ->where('pa.payroll_id', $payrollId)
            ->where('pa.status', 'pending')
            ->whereRaw('pa.order_no = (SELECT MIN(px.order_no) FROM payroll_approvals as px WHERE px.payroll_id = pa.payroll_id AND px.status = ?)', ['pending'])
            ->select('pa.*');

        if ($accessRight !== 'ADMIN') {
            $query->where('pa.approver_id', $userId);
        }

        return $query->first();
    }
}
