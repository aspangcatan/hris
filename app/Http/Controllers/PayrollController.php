<?php

namespace App\Http\Controllers;

use App\Services\DTRServices;
use App\Services\PayrollServices;
use App\Services\PermissionService;
use Barryvdh\Snappy\Facades\SnappyPdf;
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
                    'epc.amount',
                    'epc.amount_override',
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
            ]);

            $dynamicComponents = $request->input('components', null);
            if ($dynamicComponents !== null && !is_array($dynamicComponents)) {
                $dynamicComponents = [];
            }

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

            foreach ($components as $component) {
                if (empty($component['code']) || empty($component['name']) || empty($component['type'])) {
                    continue;
                }

                $code = strtoupper(trim((string) $component['code']));
                $name = trim((string) $component['name']);
                $type = strtolower(trim((string) $component['type'])) === 'deduction' ? 'deduction' : 'earning';
                $taxable = !empty($component['taxable']);
                $defaultAmount = isset($component['default_amount']) ? (float) $component['default_amount'] : 0;
                $isGlobal = !empty($component['is_global']);
                $isActive = array_key_exists('is_active', $component) ? (bool) $component['is_active'] : true;
                $isCutoffAdjustable = !empty($component['is_cutoff_adjustable']);

                DB::table('hris.payroll_components')->updateOrInsert(
                    ['code' => $code],
                    [
                        'name' => $name,
                        'type' => $type,
                        'is_global' => $isGlobal ? 1 : 0,
                        'taxable' => $taxable,
                        'default_amount' => $defaultAmount,
                        'is_active' => $isActive ? 1 : 0,
                        'is_cutoff_adjustable' => $isCutoffAdjustable ? 1 : 0,
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

    public function createCutoffSummaries(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'employees' => 'required|array|min:1',
                'employees.*' => 'required|integer',
            ]);

            $table = $this->payrollServices->getCutoffSummaryTableName();
            $master = $this->payrollServices->getOrCreateCutoffMaster(
                $validated['start_date'],
                $validated['end_date'],
                $validated['title'],
                Auth::id()
            );
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

            DB::transaction(function () use ($validated) {
                foreach ($validated['employees'] as $employeeId) {
                    $this->payrollServices->generateCutoffSummary(
                        $employeeId,
                        $validated['start_date'],
                        $validated['end_date'],
                        $validated['title'],
                        Auth::id()
                    );
                }
            });

            return response()->json(['success' => true, 'message' => 'Cutoff summaries generated.']);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function getCutoffPeriods()
    {
        try {
            $table = $this->payrollServices->getCutoffSummaryMasterTableName();
            $periods = DB::table($table)
                ->select('id', 'period_start', 'period_end', 'title')
                ->orderByDesc('period_start')
                ->get();

            return response()->json(['success' => true, 'data' => $periods]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function getCutoffSummaryPreview(Request $request)
    {
        try {
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');
            $employees = $request->query('employees', []);

            if (is_string($employees)) {
                $employees = array_filter(explode(',', $employees));
            }

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
                );

            if (!empty($startDate)) {
                $query->where('cm.period_start', $startDate);
            }
            if (!empty($endDate)) {
                $query->where('cm.period_end', $endDate);
            }
            if (!empty($employees)) {
                $query->whereIn('cs.employee_id', $employees);
            }

            $results = $query->orderBy('u.lname')->get();

            return response()->json(['success' => true, 'data' => $results]);
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
                ->where('cs.cutoff_id', $payroll->cutoff_id)
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
                $item->pending_approval_role = $item->can_approve ? $pendingApprovalMap[$item->id]->role : null;
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

            $cutoffStart = $cutoffMaster->period_start;
            $cutoffEnd   = $cutoffMaster->period_end;

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

            $missing = array_diff($employeeIds, $existingCutoffs);
            if (!empty($missing)) {
                return response()->json([
                    'error' => 'Missing cutoff summaries for selected employees.',
                    'missing_employee_ids' => array_values($missing),
                ], 422);
            }

            DB::transaction(function () use ($validated, $period, $cutoffStart, $cutoffEnd, $employeeIds, $cutoffMaster) {
                $this->payrollServices->deletePayrollEmployees($validated['payroll_id']);
                DB::table('payroll_approvals')->where('payroll_id', $validated['payroll_id'])->delete();

                foreach ($validated['employees'] as $employee) {
                    $summary = $this->payrollServices->getOrCreateCutoffSummary(
                        $employee['userid'],
                        $cutoffStart,
                        $cutoffEnd,
                        Auth::id()
                    );

                    $totalLateUndertimeMinutes = ((int) ($summary->late_minutes ?? 0)) + ((int) ($summary->undertime_minutes ?? 0));
                    $totalAbsentDays = (int) ($summary->days_absent ?? 0);
                    $totalWorkingDays = (int) ($summary->total_working_days ?? 0);

                    $this->payrollServices->savePayrollEmployee(
                        $validated['payroll_id'],
                        $employee,
                        $totalLateUndertimeMinutes,
                        $totalAbsentDays,
                        $totalWorkingDays
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

            DB::table('payrolls')
                ->where('id', $validated['payroll_id'])
                ->update([
                    'status' => 'released',
                    'reviewed_by' => Auth::id(),
                    'released_at' => now(),
                    'updated_at' => now(),
                ]);

            return response()->json(['message' => 'Payroll released successfully.']);
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

    private function getComponentBreakdown($employees)
    {
        $components = DB::table('hris.payroll_components')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get(['code', 'name', 'type']);

        $componentAmounts = DB::table('hris.payroll_employee_components')
            ->whereIn('payroll_employee_id', $employees->pluck('id'))
            ->select('payroll_employee_id', 'code', 'amount')
            ->get()
            ->groupBy('payroll_employee_id')
            ->map(function ($rows) {
                return $rows->pluck('amount', 'code');
            });

        return [
            'earning' => $components->where('type', 'earning')->values(),
            'deduction' => $components->where('type', 'deduction')->values(),
            'amounts' => $componentAmounts,
        ];
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

        $exportPath = storage_path('app/exports/payroll.xlsx');
        $this->payrollServices->exportRegularPayroll($employees, $exportPath, $payroll);

        return response()->download($exportPath)->deleteFileAfterSend(true);
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

            $pdf = SnappyPdf::loadView('pdf.payroll_payslip', [
                'payroll' => $payroll,
                'employees' => $employees,
            ])
                ->setPaper('A4', 'portrait')
                ->setOption('margin-top', 6)
                ->setOption('margin-bottom', 6)
                ->setOption('margin-left', 6)
                ->setOption('margin-right', 6)
                ->setOption('disable-javascript', true)
                ->setOption('load-error-handling', 'ignore');

            $safeTitle = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $payroll->title);
            return $pdf->download('payslip_' . $safeTitle . '.pdf');
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function payrollSummary($payroll_id)
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

            $breakdown = $this->getComponentBreakdown($employees);

            $pdf = SnappyPdf::loadView('pdf.payroll_summary', [
                'payroll' => $payroll,
                'employees' => $employees,
                'earningComponents' => $breakdown['earning'],
                'deductionComponents' => $breakdown['deduction'],
                'componentAmounts' => $breakdown['amounts'],
            ])
                ->setOption('page-width', '1000mm')
                ->setOption('page-height', '210mm')
                ->setOption('margin-top', 6)
                ->setOption('margin-bottom', 6)
                ->setOption('margin-left', 6)
                ->setOption('margin-right', 6)
                ->setOption('disable-javascript', true)
                ->setOption('load-error-handling', 'ignore');

            $safeTitle = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $payroll->title);
            return $pdf->download('summary_' . $safeTitle . '.pdf');
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
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
        $user = Auth::user();

        $query = DB::table('payroll_approvals as pa')
            ->where('pa.status', 'pending')
            ->select('pa.id', 'pa.payroll_id', 'pa.role', 'pa.order_no');

        if ($accessRight === 'ADMIN') {
            $query->whereRaw('pa.order_no = (SELECT MIN(px.order_no) FROM payroll_approvals as px WHERE px.payroll_id = pa.payroll_id AND px.status = ?)', ['pending']);
        } elseif ($accessRight === 'SH') {
            if (empty($user->section)) {
                return [];
            }
            $query->where('pa.role', 'SH')->where('pa.approver_id', $user->section);
        } elseif ($accessRight === 'DH') {
            if (empty($user->division)) {
                return [];
            }
            $query->where('pa.role', 'DH')
                ->where('pa.approver_id', $user->division)
                ->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('payroll_approvals as sh')
                        ->whereColumn('sh.payroll_id', 'pa.payroll_id')
                        ->where('sh.role', 'SH')
                        ->where('sh.status', 'approved');
                });
        } elseif (in_array($accessRight, ['HR', 'HRH'], true)) {
            $query->where('pa.role', 'HR')
                ->where('pa.approver_id', 15)
                ->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('payroll_approvals as sh')
                        ->whereColumn('sh.payroll_id', 'pa.payroll_id')
                        ->where('sh.role', 'SH')
                        ->where('sh.status', 'approved');
                })
                ->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('payroll_approvals as dh')
                        ->whereColumn('dh.payroll_id', 'pa.payroll_id')
                        ->where('dh.role', 'DH')
                        ->where('dh.status', 'approved');
                });
        } else {
            return [];
        }

        return $query->get()->keyBy('payroll_id')->toArray();
    }

    private function getUserPendingApproval($payrollId)
    {
        $accessRight = strtoupper((string) session('access_rights'));
        $user = Auth::user();

        $query = DB::table('payroll_approvals as pa')
            ->where('pa.payroll_id', $payrollId)
            ->where('pa.status', 'pending')
            ->select('pa.*');

        if ($accessRight === 'ADMIN') {
            $query->orderBy('pa.order_no', 'asc');
        } elseif ($accessRight === 'SH') {
            if (empty($user->section)) {
                return null;
            }
            $query->where('pa.role', 'SH')->where('pa.approver_id', $user->section);
        } elseif ($accessRight === 'DH') {
            if (empty($user->division)) {
                return null;
            }
            $query->where('pa.role', 'DH')
                ->where('pa.approver_id', $user->division)
                ->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('payroll_approvals as sh')
                        ->whereColumn('sh.payroll_id', 'pa.payroll_id')
                        ->where('sh.role', 'SH')
                        ->where('sh.status', 'approved');
                });
        } elseif (in_array($accessRight, ['HR', 'HRH'], true)) {
            $query->where('pa.role', 'HR')
                ->where('pa.approver_id', 15)
                ->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('payroll_approvals as sh')
                        ->whereColumn('sh.payroll_id', 'pa.payroll_id')
                        ->where('sh.role', 'SH')
                        ->where('sh.status', 'approved');
                })
                ->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('payroll_approvals as dh')
                        ->whereColumn('dh.payroll_id', 'pa.payroll_id')
                        ->where('dh.role', 'DH')
                        ->where('dh.status', 'approved');
                });
        } else {
            return null;
        }

        return $query->first();
    }
}
