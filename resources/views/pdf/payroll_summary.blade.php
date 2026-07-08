<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payroll Summary</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
            color: #111827;
        }

        .letterhead {
            width: 700px;
            display: block;
        }

        .summary-title {
            text-align: center;
            font-weight: 700;
            font-size: 13px;
            margin: 10px 0 2px;
        }

        .summary-period {
            text-align: center;
            font-size: 10px;
            color: #4b5563;
            margin-bottom: 10px;
        }

        table {
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #e5e7eb;
            padding: 3px 6px;
            white-space: nowrap;
        }

        th {
            background: #f3f4f6;
            text-align: center;
        }

        .col-name {
            text-align: left;
            white-space: normal;
        }

        .col-group-earnings {
            background: #eefaf0;
        }

        .col-group-deductions {
            background: #fff1f1;
        }

        .amount {
            text-align: right;
        }

        .totals-row td {
            background: #f9fafb;
            font-weight: 700;
        }

        .footer-note {
            text-align: center;
            font-size: 9px;
            color: #6b7280;
            font-style: italic;
            margin-top: 8px;
        }
    </style>
</head>
<body>
@php
    $fmt = fn ($value) => number_format((float) ($value ?? 0), 2);
    $amountFor = fn ($employee, $code) => (float) (($componentAmounts->get($employee->id) ?? collect())[$code] ?? 0);

    $totalBasic = $employees->sum('basic_salary');
    $totalWithholding = $employees->sum('withholding_tax');
    $totalNetPay = $employees->sum('net_pay');
@endphp

<img class="letterhead" src="{{ url('img/letter_head_banner.png') }}"/>
<div class="summary-title">PAYROLL SUMMARY — {{ strtoupper($payroll->title ?? '') }}</div>
<div class="summary-period">
    {{ date('M d, Y', strtotime($payroll->start_date)) }} to {{ date('M d, Y', strtotime($payroll->end_date)) }}
</div>

<table>
    <thead>
    <tr>
        <th rowspan="2">Name</th>
        <th rowspan="2">Designation</th>
        <th rowspan="2">Basic Pay</th>
        <th class="col-group-earnings" colspan="{{ $earningComponents->count() }}">Earnings</th>
        <th class="col-group-deductions" colspan="{{ $deductionComponents->count() }}">Deductions</th>
        <th rowspan="2">Withholding<br>Tax</th>
        <th rowspan="2">Net Pay</th>
    </tr>
    <tr>
        @foreach($earningComponents as $component)
            <th class="col-group-earnings">{{ $component->name }}</th>
        @endforeach
        @foreach($deductionComponents as $component)
            <th class="col-group-deductions">{{ $component->name }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @foreach($employees as $employee)
        @php
            $employeeName = trim(implode(' ', array_filter([
                strtoupper($employee->lname ?? '') . ',',
                strtoupper($employee->fname ?? ''),
                !empty($employee->mname) ? strtoupper(substr($employee->mname, 0, 1)) . '.' : null,
                $employee->suffix ?? null,
            ])));
        @endphp
        <tr>
            <td class="col-name">{{ $employeeName }}</td>
            <td class="col-name">{{ strtoupper($employee->designation ?? '') }}</td>
            <td class="amount">{{ $fmt($employee->basic_salary ?? 0) }}</td>
            @foreach($earningComponents as $component)
                <td class="amount">{{ $fmt($amountFor($employee, $component->code)) }}</td>
            @endforeach
            @foreach($deductionComponents as $component)
                <td class="amount">{{ $fmt($amountFor($employee, $component->code)) }}</td>
            @endforeach
            <td class="amount">{{ $fmt($employee->withholding_tax ?? 0) }}</td>
            <td class="amount">{{ $fmt($employee->net_pay ?? 0) }}</td>
        </tr>
    @endforeach
    <tr class="totals-row">
        <td colspan="2">TOTAL ({{ $employees->count() }} employees)</td>
        <td class="amount">{{ $fmt($totalBasic) }}</td>
        @foreach($earningComponents as $component)
            <td class="amount">{{ $fmt($employees->sum(fn ($employee) => $amountFor($employee, $component->code))) }}</td>
        @endforeach
        @foreach($deductionComponents as $component)
            <td class="amount">{{ $fmt($employees->sum(fn ($employee) => $amountFor($employee, $component->code))) }}</td>
        @endforeach
        <td class="amount">{{ $fmt($totalWithholding) }}</td>
        <td class="amount">{{ $fmt($totalNetPay) }}</td>
    </tr>
    </tbody>
</table>

<div class="footer-note">This payroll summary is electronically generated. Generated {{ now()->format('M d, Y h:i A') }}.</div>
</body>
</html>
