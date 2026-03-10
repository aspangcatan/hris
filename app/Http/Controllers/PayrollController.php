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
            $benefits = DB::table('employee_benefits')->where('userid', $request->userid)->first();
            $deductions = DB::table('employee_deductions')->where('userid', $request->userid)->first();

            if (!$payroll && !$benefits && !$deductions) {
                return response()->json(['success' => false, 'message' => 'No data found.']);
            }

            return response()->json([
                'success' => true,
                'data' => array_merge(
                    (array) $payroll,
                    (array) $benefits,
                    (array) $deductions
                )
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

                // Benefits
                'pera' => 'required|numeric|min:0',
                'rata' => 'required|numeric|min:0',
                'subsistence' => 'required|numeric|min:0',
                'laundry' => 'required|numeric|min:0',
                'longevity' => 'required|numeric|min:0',
                'cellphone_allowance' => 'required|numeric|min:0',

                // Deductions
                'gsis_ps' => 'required|numeric|min:0',
                'gsis_gs' => 'required|numeric|min:0',
                'gsis_ec' => 'required|numeric|min:0',
                'hdmf' => 'required|numeric|min:0',
                'phic' => 'required|numeric|min:0',
                'pagibig_cal' => 'required|numeric|min:0',
                'pagibig_mpl' => 'required|numeric|min:0',
                'pagibig_mp2' => 'required|numeric|min:0',
                'pagibig_mp2_2nd' => 'required|numeric|min:0',
                'pagibig_mp2_3rd' => 'required|numeric|min:0',
                'gsis_consoloan' => 'required|numeric|min:0',
                'gsis_emergency_loan' => 'required|numeric|min:0',
                'gsis_educational_loan' => 'required|numeric|min:0',
                'gsis_policy_loan' => 'required|numeric|min:0',
                'gsis_opt_life_loan' => 'required|numeric|min:0',
                'gsis_gfal' => 'required|numeric|min:0',
                'gsis_mpl' => 'required|numeric|min:0',
                'gsis_cpl' => 'required|numeric|min:0',
                'gsis_mpl_lite' => 'required|numeric|min:0',
                'cfi_coop' => 'required|numeric|min:0',
                'first_valley_bank' => 'required|numeric|min:0',
                'tdhea' => 'required|numeric|min:0',
                'dbp_loan' => 'required|numeric|min:0',
                'ucpb_loan' => 'required|numeric|min:0',
                'meds_payable' => 'required|numeric|min:0',
                'sss_deduction' => 'required|numeric|min:0',
            ]);

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

            DB::table('employee_benefits')->updateOrInsert(
                ['userid' => $request->userid],
                [
                    'pera' => $validatedData['pera'],
                    'rata' => $validatedData['rata'],
                    'subsistence' => $validatedData['subsistence'],
                    'laundry' => $validatedData['laundry'],
                    'longevity' => $validatedData['longevity'],
                    'cellphone_allowance' => $validatedData['cellphone_allowance'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            DB::table('employee_deductions')->updateOrInsert(
                ['userid' => $request->userid],
                [
                    'gsis_ps' => $validatedData['gsis_ps'],
                    'gsis_gs' => $validatedData['gsis_gs'],
                    'gsis_ec' => $validatedData['gsis_ec'],
                    'hdmf' => $validatedData['hdmf'],
                    'phic' => $validatedData['phic'],
                    'pagibig_cal' => $validatedData['pagibig_cal'],
                    'pagibig_mpl' => $validatedData['pagibig_mpl'],
                    'pagibig_mp2' => $validatedData['pagibig_mp2'],
                    'pagibig_mp2_2nd' => $validatedData['pagibig_mp2_2nd'],
                    'pagibig_mp2_3rd' => $validatedData['pagibig_mp2_3rd'],
                    'gsis_consoloan' => $validatedData['gsis_consoloan'],
                    'gsis_emergency_loan' => $validatedData['gsis_emergency_loan'],
                    'gsis_educational_loan' => $validatedData['gsis_educational_loan'],
                    'gsis_policy_loan' => $validatedData['gsis_policy_loan'],
                    'gsis_opt_life_loan' => $validatedData['gsis_opt_life_loan'],
                    'gsis_gfal' => $validatedData['gsis_gfal'],
                    'gsis_mpl' => $validatedData['gsis_mpl'],
                    'gsis_cpl' => $validatedData['gsis_cpl'],
                    'gsis_mpl_lite' => $validatedData['gsis_mpl_lite'],
                    'cfi_coop' => $validatedData['cfi_coop'],
                    'first_valley_bank' => $validatedData['first_valley_bank'],
                    'tdhea' => $validatedData['tdhea'],
                    'dbp_loan' => $validatedData['dbp_loan'],
                    'ucpb_loan' => $validatedData['ucpb_loan'],
                    'meds_payable' => $validatedData['meds_payable'],
                    'sss_deduction' => $validatedData['sss_deduction'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

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

    public function savePayroll(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'payroll_type' => 'nullable|string|max:50',
                'rate_type' => 'nullable|string|max:50',
                'remarks' => 'nullable|string',
            ]);

            DB::table('payrolls')->insert([
                'title' => $validated['title'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'payroll_type' => $validated['payroll_type'] ?? 'Regular',
                'rate_type' => $validated['rate_type'] ?? 'Non Pro-rated',
                'remarks' => $validated['remarks'] ?? null,
                'status' => 'draft',
                'prepared_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json(['message' => 'Payroll created successfully.']);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
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

            $currentStatus = strtolower((string) ($period->status ?? 'draft'));
            if (in_array($currentStatus, ['for_approval', 'approved', 'released'], true)) {
                return response()->json(['error' => 'Payroll is already in approval/released state and cannot be recomputed.'], 422);
            }

            DB::transaction(function () use ($validated, $period) {
                $this->payrollServices->deletePayrollEmployees($validated['payroll_id']);
                DB::table('payroll_approvals')->where('payroll_id', $validated['payroll_id'])->delete();

                $month = date('m', strtotime($period->start_date));
                $year = date('Y', strtotime($period->start_date));
                $totalWorkingDays = $this->payrollServices->getWorkingDays($year, $month);

                foreach ($validated['employees'] as $employee) {
                    $result = $this->dtrServices->getTotalLateAbsent($employee['userid'], $period->start_date, $period->end_date);
                    $totalLateUndertimeMinutes = $result['late_minutes'] + $result['undertime_minutes'];
                    $totalAbsentDays = $result['absences'];

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
