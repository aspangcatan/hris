<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payroll Payslip</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11.5px;
            color: #111827;
        }

        .payslip {
            border: 1px solid #d1d5db;
            padding: 14px;
        }

        .letterhead {
            width: 100%;
            display: block;
        }

        .payslip-title {
            text-align: center;
            font-weight: 700;
            font-size: 14px;
            margin: 10px 0 6px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        th, td {
            border: 1px solid #e5e7eb;
            padding: 3px 6px;
        }

        th {
            background: #f3f4f6;
            text-align: left;
        }

        .amount {
            text-align: right;
        }

        .section-label {
            font-style: italic;
            color: #555;
            border: none;
            padding: 4px 2px 2px;
        }

        .total-row td {
            background: #f9fafb;
            font-weight: 700;
        }

        .net-row td {
            background: #eefaf0;
            font-weight: 700;
        }

        .info-table td {
            border: none;
            padding: 2px 6px;
        }

        .info-label {
            width: 18%;
            font-weight: 600;
        }

        .footer-note {
            text-align: center;
            font-size: 10px;
            color: #6b7280;
            font-style: italic;
            margin-top: 8px;
        }

        .page-break { page-break-after: always; }
    </style>
</head>
<body>
@php
    $fmt = function ($value) {
        $value = (float) ($value ?? 0);
        return abs($value) < 0.005 ? '-' : number_format($value, 2);
    };
@endphp
@foreach($employees as $employee)
    @php
        $employeeName = trim(implode(' ', array_filter([
            strtoupper($employee->lname ?? '') . ',',
            strtoupper($employee->fname ?? ''),
            !empty($employee->mname) ? strtoupper(substr($employee->mname, 0, 1)) . '.' : null,
            $employee->suffix ?? null,
        ])));
        $salaryGrade = !empty($employee->salary_grade)
            ? $employee->salary_grade . (!empty($employee->step) ? '-' . $employee->step : '')
            : '-';
        $finalBasicPay = (float) ($employee->basic_salary ?? 0) + (float) ($employee->salary_adjustment ?? 0) - (float) ($employee->late_absent ?? 0);
        $totalGrossPay = (float) ($employee->gross_pay ?? 0);
    @endphp

    <div class="payslip">
        @if(!empty($letterhead))<img class="letterhead" src="{{ $letterhead }}"/>@endif
        <div class="payslip-title">{{ strtoupper($payroll->title ?? 'PAYSLIP') }}</div>

        <table class="info-table">
            <tr><td class="info-label">Name</td><td>{{ $employeeName }}</td></tr>
            <tr><td class="info-label">Official Designation</td><td>{{ strtoupper($employee->designation ?? '') }}</td></tr>
            <tr><td class="info-label">Salary Grade</td><td>{{ $salaryGrade }}</td></tr>
        </table>

        <table>
            <tr><th colspan="2">GROSS PAY</th></tr>
            <tr><td>Basic Pay</td><td class="amount">{{ $fmt($employee->basic_salary ?? 0) }}</td></tr>
            <tr><td>Basic Pay Adjustment</td><td class="amount">{{ $fmt($employee->salary_adjustment ?? 0) }}</td></tr>
            <tr><td>Less: Leave w/o pay</td><td class="amount">{{ $fmt($employee->late_absent ?? 0) }}</td></tr>
            <tr class="total-row"><td>Final Basic Pay</td><td class="amount">{{ $fmt($finalBasicPay) }}</td></tr>
            <tr><td>PERA</td><td class="amount">{{ $fmt($employee->pera ?? 0) }}</td></tr>
            <tr><td>Subsistence Allowance</td><td class="amount">{{ $fmt($employee->subsistence ?? 0) }}</td></tr>
            <tr><td>Laundry Allowance</td><td class="amount">{{ $fmt($employee->laundry ?? 0) }}</td></tr>
            <tr><td>Hazard Pay</td><td class="amount">{{ $fmt($employee->hazard ?? 0) }}</td></tr>
            <tr><td>Longevity Pay</td><td class="amount">{{ $fmt($employee->longevity ?? 0) }}</td></tr>
            <tr><td>RATA</td><td class="amount">{{ $fmt($employee->rata ?? 0) }}</td></tr>
            <tr><td>Cellphone Allowance</td><td class="amount">{{ $fmt($employee->cellphone_allowance ?? 0) }}</td></tr>
            <tr class="total-row"><td>TOTAL GROSS PAY</td><td class="amount">{{ $fmt($totalGrossPay) }}</td></tr>
        </table>

        <table>
            <tr><th colspan="2">DEDUCTIONS</th></tr>
            <tr><td colspan="2" class="section-label">Mandatory Deductions:</td></tr>
            <tr><td>Withholding Tax</td><td class="amount">{{ $fmt($employee->withholding_tax ?? 0) }}</td></tr>
            <tr><td>Withholding Tax Adjustment</td><td class="amount">{{ $fmt($employee->withholding_tax_adjustment ?? 0) }}</td></tr>
            <tr><td>GSIS (Retirement and Life Insurance Premium)</td><td class="amount">{{ $fmt($employee->gsis_ps ?? 0) }}</td></tr>
            <tr><td>Pag-IBIG Contribution</td><td class="amount">{{ $fmt($employee->hdmf ?? 0) }}</td></tr>
            <tr><td>PhilHealth Contribution</td><td class="amount">{{ $fmt($employee->phic ?? 0) }}</td></tr>

            <tr><td colspan="2" class="section-label">Government Loans:</td></tr>
            <tr><td>HDMF - MPL</td><td class="amount">{{ $fmt($employee->pagibig_mpl ?? 0) }}</td></tr>
            <tr><td>HDMF - CAL</td><td class="amount">{{ $fmt($employee->pagibig_cal ?? 0) }}</td></tr>
            <tr><td>HDMF - Pag-IBIG MP2</td><td class="amount">{{ $fmt($employee->pagibig_mp2 ?? 0) }}</td></tr>
            <tr><td>HDMF - Pag-IBIG MP2 2nd Account</td><td class="amount">{{ $fmt($employee->pagibig_mp2_2nd ?? 0) }}</td></tr>
            <tr><td>HDMF - Pag-IBIG MP2 3rd Account</td><td class="amount">{{ $fmt($employee->pagibig_mp2_3rd ?? 0) }}</td></tr>
            <tr><td>GSIS - Consoloan</td><td class="amount">{{ $fmt($employee->gsis_consoloan ?? 0) }}</td></tr>
            <tr><td>GSIS - Emergency</td><td class="amount">{{ $fmt($employee->gsis_emergency_loan ?? 0) }}</td></tr>
            <tr><td>GSIS - Policy</td><td class="amount">{{ $fmt($employee->gsis_policy_loan ?? 0) }}</td></tr>
            <tr><td>GSIS (GSIS Financial Assistance Loan)</td><td class="amount">{{ $fmt($employee->gsis_gfal ?? 0) }}</td></tr>
            <tr><td>GSIS MPL</td><td class="amount">{{ $fmt($employee->gsis_mpl ?? 0) }}</td></tr>
            <tr><td>GSIS CPL</td><td class="amount">{{ $fmt($employee->gsis_cpl ?? 0) }}</td></tr>
            <tr><td>GSIS MPL Lite</td><td class="amount">{{ $fmt($employee->gsis_mpl_lite ?? 0) }}</td></tr>
            <tr><td>GSIS GEL</td><td class="amount">{{ $fmt($employee->gsis_educational_loan ?? 0) }}</td></tr>
            <tr><td>GSIS GSL</td><td class="amount">{{ $fmt($employee->gsis_opt_life_loan ?? 0) }}</td></tr>

            <tr><td colspan="2" class="section-label">Other Deductions:</td></tr>
            <tr><td>CFI Coop Loan Deduction</td><td class="amount">{{ $fmt($employee->cfi_coop ?? 0) }}</td></tr>
            <tr><td>1st Valley Bank Loan</td><td class="amount">{{ $fmt($employee->first_valley_bank ?? 0) }}</td></tr>
            <tr><td>TDHEA / Union Dues & Loans</td><td class="amount">{{ $fmt($employee->tdhea ?? 0) }}</td></tr>
            <tr><td>DBP Loan</td><td class="amount">{{ $fmt($employee->dbp_loan ?? 0) }}</td></tr>
            <tr><td>UCPB Loan</td><td class="amount">{{ $fmt($employee->ucpb_loan ?? 0) }}</td></tr>
            <tr><td>SSS Deduction</td><td class="amount">{{ $fmt($employee->sss_deduction ?? 0) }}</td></tr>
            <tr><td>SSS Adjustment</td><td class="amount">{{ $fmt($employee->sss_adjustment ?? 0) }}</td></tr>
            <tr><td>GSIS Premium (LWOP)</td><td class="amount">{{ $fmt(((float)($employee->gsis_ps_lwop_2pct ?? 0)) + ((float)($employee->gsis_gs_lwop_2pct ?? 0))) }}</td></tr>
            <tr><td>Hazard Pay Adjustment</td><td class="amount">{{ $fmt($employee->hazard_adjustment ?? 0) }}</td></tr>
            <tr><td>PERA Adjustment</td><td class="amount">{{ $fmt($employee->pera_adjustment ?? 0) }}</td></tr>
            <tr><td>Subsistence Adjustment</td><td class="amount">{{ $fmt($employee->subsistence_adjustment ?? 0) }}</td></tr>
            <tr><td>Laundry Adjustment</td><td class="amount">{{ $fmt($employee->laundry_adjustment ?? 0) }}</td></tr>
            <tr><td>Subsistence Deduction from Leave</td><td class="amount">{{ $fmt($employee->subsistence_deduction_from_leave ?? 0) }}</td></tr>

            <tr class="total-row"><td>TOTAL DEDUCTION</td><td class="amount">{{ $fmt($employee->deductions ?? 0) }}</td></tr>
        </table>

        <table>
            <tr class="net-row">
                <td>NET PAY</td><td class="amount">{{ $fmt($employee->net_pay ?? 0) }}</td>
                <td>1st Half (10th)</td><td class="amount">{{ $fmt($employee->first_half_10th ?? 0) }}</td>
            </tr>
            <tr>
                <td>2nd Half (25th)</td><td class="amount">{{ $fmt($employee->second_half_25th ?? 0) }}</td>
                <td>Generated</td><td>{{ now()->format('M d, Y h:i A') }}</td>
            </tr>
        </table>

        <div class="footer-note">This payslip is electronically generated.</div>
    </div>

    @if(!$loop->last)
        <div class="page-break"></div>
    @endif
@endforeach
</body>
</html>
