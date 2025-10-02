<?php

namespace App\Http\Controllers;

use App\Services\DTRServices;
use App\Services\PayrollServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;


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
                    (array)$payroll,
                    (array)$benefits,
                    (array)$deductions
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


            // Use validated data instead of $request to ensure safety
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
                'errors' => $e->errors(), // This returns specific field errors
            ], 422);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function savePayroll(Request $request)
    {
        try {
            // Validate request
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'payroll_type' => 'string',
                'rate_type' => 'string',
                'remarks' => 'nullable|string',
            ]);

            // Insert into payroll table (replace 'Payroll' with your actual model or use DB::table)
            DB::table('payrolls')->insert([
                'title' => $validated['title'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'payroll_type' => $validated['payroll_type'],
                'rate_type' => $validated['rate_type'],
                'remarks' => $validated['remarks'],
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
            $payrolls = DB::table('payrolls')
                ->select('id', 'title', 'start_date', 'end_date','payroll_type','rate_type', 'remarks', 'created_at')
                ->orderByDesc('created_at')
                ->get();

            return response()->json($payrolls);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function savePayrollEmployee(Request $request)
    {
        try {
            $employees = $request->employees;
            $period = $this->payrollServices->getPayrollPeriod($request->payroll_id);
            if (!$period) return response()->json(['error' => "Payroll doesn't exists"]);

            $month = date('m', strtotime($period->start_date));
            $year = date('Y', strtotime($period->start_date));
            foreach ($employees as $employee) {
                list($totalLateUndertimeMinutes, $totalAbsentDays) = $this->dtrServices->getTotalLateAbsent($employee["userid"], $period->start_date, $period->end_date);
                $totalWorkingDays = $this->payrollServices->getWorkingDays($year,$month);
                $this->payrollServices->savePayrollEmployee($request->payroll_id, $employee, $totalLateUndertimeMinutes, $totalAbsentDays, $totalWorkingDays);
            }
            return response()->json(['message' => "DONE"]);
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
        // Get employees for payroll
        $employees = $this->payrollServices->getPayrollEmployee($payroll_id);
        if($employees->isEmpty()){
            return back()->with('error', 'No employees found for this payroll.');
        }
        $exportPath = storage_path('app/exports/payroll.xlsx');
        $this->payrollServices->exportRegularPayroll($employees, $exportPath);
        return response()->download($exportPath)->deleteFileAfterSend(true);
    }
}
