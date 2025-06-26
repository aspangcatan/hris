<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollController extends Controller
{
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
                'remarks' => 'nullable|string',
            ]);

            // Insert into payroll table (replace 'Payroll' with your actual model or use DB::table)
            DB::table('payrolls')->insert([
                'title' => $validated['title'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
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
                ->select('id', 'title', 'start_date', 'end_date', 'remarks', 'created_at')
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

            foreach ($employees as $employee) {
                $salary = DB::table('hris.payroll_settings')
                    ->where('userid', '=', $employee["userid"])
                    ->first();

                $benefits = DB::table('hris.employee_benefits')
                    ->where('userid', '=', $employee["userid"])
                    ->first();

                $deductions = DB::table('hris.employee_deductions')
                    ->where('userid', '=', $employee["userid"])
                    ->first();


                $basic_salary = $salary->basic_salary ?? 0.00;
                $pera = $benefits->pera ?? 0.00;
                $rata = $benefits->rata ?? 0.00;
                $subsistence = $benefits->subsistence ?? 0.00;
                $laundry = $benefits->laundry ?? 0.00;
                $longevity = $benefits->longevity ?? 0.00;
                $cellphone_allowance = $benefits->cellphone_allowance ?? 0.00;

                $gross_pay = $basic_salary + $pera + $rata + $subsistence + $laundry + $longevity + $cellphone_allowance;


                // Safely extract each deduction with null fallback
                $gsis_ps = $deductions->gsis_ps ?? 0.00;
                $gsis_gs = $deductions->gsis_gs ?? 0.00;
                $gsis_ec = $deductions->gsis_ec ?? 0.00;
                $hdmf = $deductions->hdmf ?? 0.00;
                $phic = $deductions->phic ?? 0.00;
                $pagibig_cal = $deductions->pagibig_cal ?? 0.00;
                $pagibig_mpl = $deductions->pagibig_mpl ?? 0.00;
                $pagibig_mp2 = $deductions->pagibig_mp2 ?? 0.00;
                $pagibig_mp2_2nd = $deductions->pagibig_mp2_2nd ?? 0.00;
                $pagibig_mp2_3rd = $deductions->pagibig_mp2_3rd ?? 0.00;
                $gsis_consoloan = $deductions->gsis_consoloan ?? 0.00;
                $gsis_emergency_loan = $deductions->gsis_emergency_loan ?? 0.00;
                $gsis_educational_loan = $deductions->gsis_educational_loan ?? 0.00;
                $gsis_policy_loan = $deductions->gsis_policy_loan ?? 0.00;
                $gsis_opt_life_loan = $deductions->gsis_opt_life_loan ?? 0.00;
                $gsis_gfal = $deductions->gsis_gfal ?? 0.00;
                $gsis_mpl = $deductions->gsis_mpl ?? 0.00;
                $gsis_cpl = $deductions->gsis_cpl ?? 0.00;
                $gsis_mpl_lite = $deductions->gsis_mpl_lite ?? 0.00;
                $cfi_coop = $deductions->cfi_coop ?? 0.00;
                $first_valley_bank = $deductions->first_valley_bank ?? 0.00;
                $tdhea = $deductions->tdhea ?? 0.00;
                $dbp_loan = $deductions->dbp_loan ?? 0.00;
                $ucpb_loan = $deductions->ucpb_loan ?? 0.00;
                $meds_payable = $deductions->meds_payable ?? 0.00;
                $sss_deduction = $deductions->sss_deduction ?? 0.00;

                $total_deductions = $gsis_ps + $gsis_gs + $gsis_ec + $hdmf + $phic +
                    $pagibig_cal + $pagibig_mpl + $pagibig_mp2 + $pagibig_mp2_2nd + $pagibig_mp2_3rd +
                    $gsis_consoloan + $gsis_emergency_loan + $gsis_educational_loan +
                    $gsis_policy_loan + $gsis_opt_life_loan + $gsis_gfal + $gsis_mpl +
                    $gsis_cpl + $gsis_mpl_lite + $cfi_coop + $first_valley_bank +
                    $tdhea + $dbp_loan + $ucpb_loan + $meds_payable + $sss_deduction;


                DB::table('hris.payroll_employees')->insert([
                    'payroll_id' => $request->payroll_id,
                    'employee_id' => $employee['userid'],
                    'division' => 1,
                    'section' => 2,
                    'basic_salary' => $basic_salary,
                    'pera' => $pera,
                    'rata' => $rata,
                    'subsistence' => $subsistence,
                    'laundry' => $laundry,
                    'longevity' => $longevity,
                    'cellphone_allowance' => $cellphone_allowance,
                    'gross_pay' => $gross_pay,
                    'gsis_ps' => $gsis_ps,
                    'gsis_gs' => $gsis_gs,
                    'gsis_ec' => $gsis_ec,
                    'hdmf' => $hdmf,
                    'phic' => $phic,
                    'pagibig_cal' => $pagibig_cal,
                    'pagibig_mpl' => $pagibig_mpl,
                    'pagibig_mp2' => $pagibig_mp2,
                    'pagibig_mp2_2nd' => $pagibig_mp2_2nd,
                    'pagibig_mp2_3rd' => $pagibig_mp2_3rd,
                    'gsis_consoloan' => $gsis_consoloan,
                    'gsis_emergency_loan' => $gsis_emergency_loan,
                    'gsis_educational_loan' => $gsis_educational_loan,
                    'gsis_policy_loan' => $gsis_policy_loan,
                    'gsis_opt_life_loan' => $gsis_opt_life_loan,
                    'gsis_gfal' => $gsis_gfal,
                    'gsis_mpl' => $gsis_mpl,
                    'gsis_cpl' => $gsis_cpl,
                    'gsis_mpl_lite' => $gsis_mpl_lite,
                    'cfi_coop' => $cfi_coop,
                    'first_valley_bank' => $first_valley_bank,
                    'tdhea' => $tdhea,
                    'dbp_loan' => $dbp_loan,
                    'ucpb_loan' => $ucpb_loan,
                    'meds_payable' => $meds_payable,
                    'sss_deduction' => $sss_deduction,
                    'deductions' => $total_deductions,
                    'created_at' => now()
                ]);

            }

            return response()->json(['message' => "DONE"]);
        } catch (\Exception $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

}
