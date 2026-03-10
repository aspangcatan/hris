<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payroll Payslip</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #111827;
        }

        .payslip {
            border: 1px solid #d1d5db;
            padding: 16px;
        }

        .header {
            margin-bottom: 10px;
        }

        .header h2 {
            margin: 0;
            font-size: 18px;
        }

        .header p {
            margin: 2px 0 0;
            color: #4b5563;
            font-size: 11px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            border: 1px solid #e5e7eb;
            padding: 6px 8px;
        }

        th {
            background: #f3f4f6;
            text-align: left;
        }

        .label {
            width: 32%;
            font-weight: 600;
            background: #f9fafb;
        }

        .amount {
            text-align: right;
        }

        .summary {
            margin-top: 12px;
        }

        .summary td {
            font-weight: 600;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
@foreach($employees as $employee)
    @php
        $employeeName = trim(implode(' ', array_filter([
            ($employee->lname ?? '') . ',',
            $employee->fname ?? '',
            !empty($employee->mname) ? strtoupper(substr($employee->mname, 0, 1)) . '.' : null,
            $employee->suffix ?? null,
        ])));
    @endphp

    <div class="payslip">
        <div class="header">
            <h2>Payroll Payslip</h2>
            <p>Payroll: {{ $payroll->title ?? 'N/A' }}</p>
            <p>Period: {{ date('M d, Y', strtotime($payroll->start_date)) }} to {{ date('M d, Y', strtotime($payroll->end_date)) }}</p>
        </div>

        <table>
            <tr>
                <td class="label">Employee</td>
                <td>{{ $employeeName }}</td>
                <td class="label">Designation</td>
                <td>{{ $employee->designation ?? '' }}</td>
            </tr>
            <tr>
                <td class="label">Division</td>
                <td>{{ $employee->division ?? '' }}</td>
                <td class="label">Section</td>
                <td>{{ $employee->section ?? '' }}</td>
            </tr>
        </table>

        <table>
            <thead>
            <tr>
                <th>Description</th>
                <th class="amount">Amount (PHP)</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>Basic Salary</td>
                <td class="amount">{{ number_format((float) ($employee->basic_salary ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td>Allowances (PERA + RATA + Subsistence + Laundry + Longevity + Cellphone)</td>
                <td class="amount">
                    {{ number_format((float) (($employee->pera ?? 0) + ($employee->rata ?? 0) + ($employee->subsistence ?? 0) + ($employee->laundry ?? 0) + ($employee->longevity ?? 0) + ($employee->cellphone_allowance ?? 0)), 2) }}
                </td>
            </tr>
            <tr>
                <td>Late/Absent Deduction</td>
                <td class="amount">{{ number_format((float) ($employee->late_absent ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td>Gross Pay</td>
                <td class="amount">{{ number_format((float) ($employee->gross_pay ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td>Total Deductions</td>
                <td class="amount">{{ number_format((float) ($employee->deductions ?? 0), 2) }}</td>
            </tr>
            </tbody>
        </table>

        <table class="summary">
            <tr>
                <td class="label">Net Pay</td>
                <td class="amount">{{ number_format((float) ($employee->net_pay ?? 0), 2) }}</td>
                <td class="label">1st Half (10th)</td>
                <td class="amount">{{ number_format((float) ($employee->first_half_10th ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td class="label">2nd Half (25th)</td>
                <td class="amount">{{ number_format((float) ($employee->second_half_25th ?? 0), 2) }}</td>
                <td class="label">Generated At</td>
                <td>{{ now()->format('M d, Y h:i A') }}</td>
            </tr>
        </table>
    </div>

    @if(!$loop->last)
        <div class="page-break"></div>
    @endif
@endforeach
</body>
</html>
