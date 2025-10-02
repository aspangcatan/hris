<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollServices
{
    public function getPayroll($id)
    {
        return DB::table('hris.payroll')->where('id', $id)->first();
    }

    public function exportRegularPayroll($payrollEmployees, $exportPath)
    {
        // Load template
        $templatePath = storage_path('app/templates/payroll_template.xlsx');
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Define column mapping according to your desired order
        $columns = [
            'No.' => null,
            'NAME' => 'name',
            'FAMILY NAME' => 'lname',
            'FIRST NAME' => 'fname',
            'MIDDLE NAME' => 'mname',
            'SUFFIX' => 'suffix',
            'MI' => 'mi',
            'STAT' => 'stat',
            'DIVISION' => 'division',
            'DESIGNATION' => 'designation',
            'zz' => 'zz',
            'SG' => 'sg',
            'Step' => 'step',
            'SG B' => 'sg_b',
            'SSL 6 BASIC PAY' => 'basic_salary',
            'BASIC PAY ADJ' => 'salary_adjustment',
            'BASIC PAY LWOP' => 'late_absent',
            'FINAL BASIC PAY' => 'final_basic_pay',
            'PERA' => 'pera',
            'RATA' => 'rata',
            'SUBSISTENCE' => 'subsistence',
            'LAUNDRY' => 'laundry',
            'HAZARD' => 'hazard',
            'LONGEVITY' => 'longevity',
            'QUARTERS ALLOWANCE' => 'quarters_allowance',
            'CELLPHONE ALLOWANCE' => 'cellphone_allowance',
            'GROSS PAY' => 'gross_pay',
            'WITHHOLDING TAX' => 'withholding_tax',
            'WITHHOLDING TAX (adjustment)' => 'withholding_adjustment',
            'GSIS PS' => 'gsis_ps',
            'GSIS GS' => 'gsis_gs',
            'GSIS EC' => 'gsis_ec',
            'HDMF' => 'hdmf',
            'PHIC' => 'phic',
            'PAGIBIG CAL' => 'pagibig_cal',
            'PAGIBIG MPL' => 'pagibig_mpl',
            'PAG-IBIG MP2' => 'pagibig_mp2',
            'PAG-IBIG MP2 2ND ACCOUNT' => 'pagibig_mp2_2nd',
            'PAG-IBIG MP2 3RD ACCOUNT' => 'pagibig_mp2_3rd',
            'GSIS CONSO LOAN' => 'gsis_consoloan',
            'GSIS EMERGENCY LOAN' => 'gsis_emergency_loan',
            'GSIS EDUCATIONAL LOAN' => 'gsis_educational_loan',
            'GSIS POLICY LOAN' => 'gsis_policy_loan',
            'GSIS OPT_LIFE LOAN' => 'gsis_opt_life_loan',
            'GSIS GFAL' => 'gsis_gfal',
            'GSIS MPL' => 'gsis_mpl',
            'GSIS CPL' => 'gsis_cpl',
            'GSIS MPL Lite' => 'gsis_mpl_lite',
            'CFI COOP' => 'cfi_coop',
            '1ST VALLEY BANK' => 'first_valley_bank',
            'TDHEA' => 'tdhea',
            'DBP LOAN' => 'dbp_loan',
            'UCPB LOAN' => 'ucpb_loan',
            'MEDS PAYABLE' => 'meds_payable',
            'PHIC CONTRI ADJ' => 'phic_contri_adj',
            'PHIC REFUND FROM E.O.' => 'phic_refund_eo',
            'HAZARD PAY ADJ' => 'hazard_adjustment',
            'ADJ PERA' => 'pera_adjustment',
            'ADJ SUBSISTENCE' => 'subsistence_adjustment',
            'ADJ LAUNDRY' => 'laundry_adjustment',
            'SSS DEDUCTION' => 'sss_deduction',
            'SSS ADJUSTMENT' => 'sss_adjustment',
            'GSIS PS LWOP (2%)' => 'gsis_ps_lwop',
            'GSIS GS LWOP (2%)' => 'gsis_gs_lwop',
            'GSIS PLREG REFUND' => 'gsis_plreg_refund',
            'GSIS MPL REFUND' => 'gsis_mpl_refund',
            'GSIS EMERGENCY REFUND' => 'gsis_emergency_refund',
            'GSIS CONSOLOAN REFUND' => 'gsis_consoloan_refund',
            'SUBSISTENCE DEDUCTION FROM LEAVE' => 'subsistence_deduction_leave',
            '2nd half adjustment for resigned employee' => 'resigned_adjustment',
            'TOTAL DEDUCTION' => 'deductions',
            'NET PAY' => 'net_pay',
            '1ST HALF 10TH' => 'first_half_10th',
            '2ND HALF 25TH' => 'second_half_25th',
        ];

        $row = 2; // Start from row 2 (headers in row 1)

        foreach ($payrollEmployees as $index => $item) {
            $colIndex = 0;
            foreach ($columns as $header => $field) {
                $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1) . $row;

                if ($field === null) {
                    // Auto number column
                    $sheet->setCellValue($cell, $index + 1);
                } elseif ($header === 'zz') {
                    // Generate acronym for designation
                    $sheet->setCellValue($cell, $this->generateAcronym($item->designation ?? ''));
                } else {
                    $sheet->setCellValue($cell, $item->$field ?? 0);
                }

                $colIndex++;
            }

            $row++;
        }


        // Save to export path
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($exportPath);
    }

    public function exportJobOrderPayroll($payrollEmployees, $exportPath)
    {
        // Load template
        $templatePath = storage_path('app/templates/jo_payroll_template.xlsx');
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Define column mapping according to your new desired order
        $columns = [
            'Serial No.' => null,
            'LAST NAME' => 'lname',
            'FIRST NAME' => 'fname',
            'MIDDLE INITIAL' => 'mi',
            'POSITION' => 'designation',
            'RATE/DAY' => 'rate_per_day',
            '# OF DAYS WORK' => 'days_worked',
            'GROSS AMOUNT' => 'gross_amount',
            'OVERTIME MINUTES' => 'overtime_minutes',
            'AMOUNT (OT)' => 'overtime_amount',
            'TARDINESS MINUTES' => 'tardiness_minutes',
            'AMOUNT (Tardiness)' => 'tardiness_amount',
            'SALARY ADJUSTMENT' => 'salary_adjustment',
            'GROSS AMOUNT EARNED' => 'gross_amount_earned',
            'SSS DEDUCTION' => 'sss_deduction',
            'INCOME TAX (3%)' => 'income_tax',
            '5% TAX DEDUCTION FROM PREVIOUS PAYROLL' => 'prev_payroll_tax_deduction',
            'PERCENTAGE TAX (3%)' => 'percentage_tax',
            'PROFESSIONAL FEE (10%)' => 'professional_fee',
            'TOTAL DEDUCTION' => 'total_deduction',
            'NET AMOUNT DUE' => 'net_amount_due',
        ];

        $row = 3; // Start from row 2 (headers in row 1)

        foreach ($payrollEmployees as $index => $item) {
            $colIndex = 0;
            foreach ($columns as $header => $field) {
                $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1) . $row;

                if ($field === null) {
                    // Auto number column
                    $sheet->setCellValue($cell, $index + 1);
                } else {
                    $sheet->setCellValue($cell, $item->$field ?? 0);
                }

                $colIndex++;
            }

            $row++;
        }

        // Save to export path
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($exportPath);
    }


    public function getPayrollEmployee($payroll_id)
    {
        return $list = DB::table('hris.payroll_employees as e')
            ->join('tdh_user.users as u', 'u.id', '=', 'e.employee_id')
            ->join('tdh_user.designation as de', 'u.designation', '=', 'de.id')
            ->join('tdh_user.division as d', 'd.id', '=', 'u.division')
            ->join('tdh_user.section as s', 's.id', '=', 'u.section')
            ->where('e.payroll_id', '=', $payroll_id)
            ->select(
                'e.*',
                'u.fname',
                'u.mname',
                'u.lname',
                'u.suffix',
                'd.code as division',
                's.code as section',
                'de.description as designation'
            )
            ->get();
    }

    private function computeWithholdingTax($gross)
    {
        // Simplified sample monthly BIR tax table (2023 TRAIN Law)
        if ($gross <= 20833) {
            return 0;
        } elseif ($gross <= 33333) {
            return ($gross - 20833) * 0.15;
        } elseif ($gross <= 66667) {
            return 1875 + ($gross - 33333) * 0.20;
        } elseif ($gross <= 166667) {
            return 8541.8 + ($gross - 66667) * 0.25;
        } elseif ($gross <= 666667) {
            return 33541.8 + ($gross - 166667) * 0.30;
        } else {
            return 183541.8 + ($gross - 666667) * 0.35;
        }
    }

    private function generateAcronym($designation)
    {
        if (!$designation) return '';

        $words = explode(' ', $designation);
        $rank = array_pop($words); // last word is rank

        // Convert Roman numeral I–VII to number
        $romanToNumber = [
            'I' => '1', 'II' => '2', 'III' => '3', 'IV' => '4',
            'V' => '5', 'VI' => '6', 'VII' => '7'
        ];
        $rankNumber = $romanToNumber[strtoupper($rank)] ?? $rank;

        // Get first letters of remaining words
        $acronym = '';
        foreach ($words as $word) {
            if (preg_match('/[A-Za-z]/', $word[0])) {
                $acronym .= strtoupper($word[0]);
            }
        }

        return $acronym . $rankNumber;
    }


    public function getWorkingDays($year, $month)
    {
        $startDate = Carbon::createFromDate($year, $month, 1);
        $endDate = $startDate->copy()->endOfMonth();
        $count = 0;

        // Get holidays for the month/year
        $holidays = DB::table('hris.calendar_holidays')
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->pluck('date')
            ->map(function ($date) {
                return Carbon::parse($date)->toDateString();
            })
            ->toArray();

        while ($startDate <= $endDate) {
            if ($startDate->isWeekday() && !in_array($startDate->toDateString(), $holidays)) {
                $count++;
            }
            $startDate->addDay();
        }

        return $count; // only integer
    }

    public function getPayrollPeriod($id)
    {
        return DB::table('hris.payrolls')
            ->where('id', '=', $id)
            ->first();
    }

    public function savePayrollEmployee($payroll_id, $employee, $totalLateUndertimeMinutes, $totalAbsentDays, $totalWorkingDays)
    {
        $salary = DB::table('hris.payroll_settings')
            ->where('userid', '=', $employee["userid"])
            ->first();

        $benefits = DB::table('hris.employee_benefits')
            ->where('userid', '=', $employee["userid"])
            ->first();

        $deductions = DB::table('hris.employee_deductions')
            ->where('userid', '=', $employee["userid"])
            ->first();

        // ---- Basic Salary & Rates ----
        $basic_salary = $salary->basic_salary ?? 0.00;
        $daily_rate = $basic_salary / $totalWorkingDays;
        $hourly_rate = $daily_rate / 8;
        $minute_rate = $hourly_rate / 60;

        $lateUndertimeDeduction = $totalLateUndertimeMinutes * $minute_rate;
        $absentDeduction = $totalAbsentDays * $daily_rate;
        $totalTardinessDeductions = $lateUndertimeDeduction + $absentDeduction;

        // ---- Benefits ----
        $pera = $benefits->pera ?? 0.00;
        $rata = $benefits->rata ?? 0.00;
        $subsistence = $benefits->subsistence ?? 0.00;
        $laundry = $benefits->laundry ?? 0.00;
        $longevity = $benefits->longevity ?? 0.00;
        $cellphone_allowance = $benefits->cellphone_allowance ?? 0.00;

        // ---- Gross Pay (before tardiness deduction) ----
        $gross_pay = ($basic_salary + $pera + $rata + $subsistence + $laundry + $longevity + $cellphone_allowance);

        // ---- Withholding Tax ----
        $withholding_tax = $this->computeWithholdingTax($gross_pay - $totalTardinessDeductions);

        // ---- Deductions (safe defaults) ----
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

        // ---- Loans & Others ----
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

        // ---- Adjustments & Refunds ----
        $withholding_tax_adjustment = $employee['withholding_tax_adjustment'] ?? 0.00;
        $phic_contri_adj = $employee['phic_contri_adj'] ?? 0.00;
        $phic_refund_from_eo = $employee['phic_refund_from_eo'] ?? 0.00;
        $sss_adjustment = $employee['sss_adjustment'] ?? 0.00;
        $gsis_ps_lwop_2pct = $employee['gsis_ps_lwop_2pct'] ?? 0.00;
        $gsis_gs_lwop_2pct = $employee['gsis_gs_lwop_2pct'] ?? 0.00;
        $gsis_plreg_refund = $employee['gsis_plreg_refund'] ?? 0.00;
        $gsis_mpl_refund = $employee['gsis_mpl_refund'] ?? 0.00;
        $gsis_emergency_refund = $employee['gsis_emergency_refund'] ?? 0.00;
        $gsis_consoloan_refund = $employee['gsis_consoloan_refund'] ?? 0.00;
        $subsistence_deduction_from_leave = $employee['subsistence_deduction_from_leave'] ?? 0.00;
        $second_half_adjustment_resigned_employee = $employee['second_half_adjustment_resigned_employee'] ?? 0.00;


        // ---- Final Deductions ----
        $total_deductions =
            $withholding_tax + $withholding_tax_adjustment +
            $gsis_ps + $gsis_gs + $gsis_ec +
            $hdmf + $phic + $phic_contri_adj - $phic_refund_from_eo +
            $pagibig_cal + $pagibig_mpl + $pagibig_mp2 + $pagibig_mp2_2nd + $pagibig_mp2_3rd +
            $gsis_consoloan + $gsis_emergency_loan + $gsis_educational_loan +
            $gsis_policy_loan + $gsis_opt_life_loan + $gsis_gfal + $gsis_mpl + $gsis_cpl + $gsis_mpl_lite +
            $cfi_coop + $first_valley_bank + $tdhea + $dbp_loan + $ucpb_loan + $meds_payable +
            $sss_deduction + $sss_adjustment +
            $gsis_ps_lwop_2pct + $gsis_gs_lwop_2pct -
            ($gsis_plreg_refund + $gsis_mpl_refund + $gsis_emergency_refund + $gsis_consoloan_refund) +
            $subsistence_deduction_from_leave +
            $second_half_adjustment_resigned_employee +
            $totalTardinessDeductions;

        // ---- Net Pay ----
        $net_pay = ($gross_pay - $totalTardinessDeductions) - $total_deductions;
        $first_half_10th = $net_pay / 2;
        $second_half_25th = $net_pay / 2;
        // ---- Insert ----
        return DB::table('hris.payroll_employees')->insertGetId([
            'payroll_id' => $payroll_id,
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
            'late_absent' => $totalTardinessDeductions,
            'gross_pay' => $gross_pay - $totalTardinessDeductions,

            // Contributions & Loans
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

            // Adjustments & Refunds
            'withholding_tax' => $withholding_tax,
            'withholding_tax_adjustment' => $withholding_tax_adjustment,
            'phic_contri_adj' => $phic_contri_adj,
            'phic_refund_from_eo' => $phic_refund_from_eo,
            'sss_adjustment' => $sss_adjustment,
            'gsis_ps_lwop_2pct' => $gsis_ps_lwop_2pct,
            'gsis_gs_lwop_2pct' => $gsis_gs_lwop_2pct,
            'gsis_plreg_refund' => $gsis_plreg_refund,
            'gsis_mpl_refund' => $gsis_mpl_refund,
            'gsis_emergency_refund' => $gsis_emergency_refund,
            'gsis_consoloan_refund' => $gsis_consoloan_refund,
            'subsistence_deduction_from_leave' => $subsistence_deduction_from_leave,
            'second_half_adjustment_resigned_employee' => $second_half_adjustment_resigned_employee,

            // Split pay
            'first_half_10th' => $first_half_10th,
            'second_half_25th' => $second_half_25th,

            // Final
            'deductions' => $total_deductions,
            'net_pay' => $net_pay,

            // Other adjustments
            'salary_adjustment' => $employee['salary_adjustment'] ?? 0.00,
            'hazard_adjustment' => $employee['hazard_adjustment'] ?? 0.00,
            'pera_adjustment' => $employee['pera_adjustment'] ?? 0.00,
            'subsistence_adjustment' => $employee['subsistence_adjustment'] ?? 0.00,
            'laundry_adjustment' => $employee['laundry_adjustment'] ?? 0.00,

            'created_at' => now(),
            'updated_at' => now()
        ]);
    }


}
