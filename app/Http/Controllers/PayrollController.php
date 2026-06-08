<?php

namespace App\Http\Controllers;

use App\Services\DTRServices;
use App\Services\PayrollServices;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollController extends Controller
{
    protected $dtrServices, $payrollServices;

    public function __construct(DTRServices $dtrServices, PayrollServices $payrollServices)
    {
        $this->dtrServices = $dtrServices;
        $this->payrollServices = $payrollServices;
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

                DB::table('hris.payroll_components')->updateOrInsert(
                    ['code' => $code],
                    [
                        'name' => $name,
                        'type' => $type,
                        'is_global' => $isGlobal ? 1 : 0,
                        'taxable' => $taxable,
                        'default_amount' => $defaultAmount,
                        'is_active' => $isActive ? 1 : 0,
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

            foreach ($rules as $rule) {
                $purpose = trim((string) ($rule['purpose'] ?? ''));
                if ($purpose === '') {
                    continue;
                }
                $minAnnual = isset($rule['min_annual']) ? (float) $rule['min_annual'] : 0;
                $maxAnnual = array_key_exists('max_annual', $rule) ? $rule['max_annual'] : null;
                $ratePercent = isset($rule['rate_percent']) ? (float) $rule['rate_percent'] : 0;
                $taxMode = strtolower((string) ($rule['tax_mode'] ?? 'flat')) === 'progressive' ? 'progressive' : 'flat';
                $isActive = array_key_exists('is_active', $rule) ? (bool) $rule['is_active'] : true;
                $sortOrder = isset($rule['sort_order']) ? (int) $rule['sort_order'] : 0;
                $remarks = $rule['remarks'] ?? null;

                $payload = [
                    'purpose' => $purpose,
                    'min_annual' => $minAnnual,
                    'max_annual' => $maxAnnual === '' ? null : $maxAnnual,
                    'rate_percent' => $ratePercent,
                    'tax_mode' => $taxMode,
                    'is_active' => $isActive ? 1 : 0,
                    'sort_order' => $sortOrder,
                    'remarks' => $remarks,
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

    public function getPayroll()
    {
        try {
            $payrolls = DB::table('payrolls as p')
                ->leftJoin('tdh_user.users as u', 'u.id', '=', 'p.prepared_by')
                ->select(
                    'p.id',
                    'p.title',
                    'p.start_date',
                    'p.end_date',
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
                    'u.suffix'
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
                $item->can_release = in_array(session('access_rights'), ['admin', 'HR', 'HRH'], true) && $status === 'approved';
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

            $isAdmin = in_array(session('access_rights'), ['admin'], true);
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

            $user = Auth::user();
            $sectionId = $user->section ?? null;
            $divisionId = $user->division ?? null;

            if (empty($sectionId) || empty($divisionId)) {
                return response()->json(['error' => 'Preparer account must have section and division to initialize approvals.'], 422);
            }

            DB::transaction(function () use ($validated, $sectionId, $divisionId) {
                DB::table('payroll_approvals')->where('payroll_id', $validated['payroll_id'])->delete();

                DB::table('payroll_approvals')->insert([
                    [
                        'payroll_id' => $validated['payroll_id'],
                        'approver_id' => $sectionId,
                        'role' => 'SH',
                        'status' => 'pending',
                        'order_no' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'payroll_id' => $validated['payroll_id'],
                        'approver_id' => $divisionId,
                        'role' => 'DH',
                        'status' => 'pending',
                        'order_no' => 2,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'payroll_id' => $validated['payroll_id'],
                        'approver_id' => 15,
                        'role' => 'HR',
                        'status' => 'pending',
                        'order_no' => 3,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ]);

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

            if (!in_array(session('access_rights'), ['admin', 'HR', 'HRH'], true)) {
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
            return response()->json($this->payrollServices->getPayrollEmployee($request->payroll_id));
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function export($payroll_id)
    {
        $employees = $this->payrollServices->getPayrollEmployee($payroll_id);
        if ($employees->isEmpty()) {
            return back()->with('error', 'No employees found for this payroll.');
        }

        $exportPath = storage_path('app/exports/payroll.xlsx');
        $this->payrollServices->exportRegularPayroll($employees, $exportPath);

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

    private function canAssignPayroll($payroll)
    {
        $status = strtolower((string) ($payroll->status ?? 'draft'));
        $isOwner = (int) $payroll->prepared_by === (int) Auth::id();
        $isAdmin = in_array(session('access_rights'), ['admin'], true);

        return ($isOwner || $isAdmin) && in_array($status, ['draft', 'computed', 'rejected'], true);
    }

    private function canSubmitPayroll($payroll)
    {
        $status = strtolower((string) ($payroll->status ?? 'draft'));
        $isOwner = (int) $payroll->prepared_by === (int) Auth::id();
        $isAdmin = in_array(session('access_rights'), ['admin'], true);

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
