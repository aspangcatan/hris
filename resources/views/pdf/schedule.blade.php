<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 28px 32px;
        }
        body {
            font-family: "Helvetica", "Arial", sans-serif;
            color: #000;
            font-size: 11px;
        }
        .header {
            text-align: center;
            line-height: 1.25;
            margin-bottom: 10px;
        }
        .header .title {
            font-weight: 700;
            margin-top: 4px;
        }
        .meta {
            margin: 12px 0 6px;
            font-size: 11px;
        }
        .meta .line {
            margin-bottom: 2px;
        }
        table.schedule {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table.schedule th,
        table.schedule td {
            border: 1px solid #000;
            padding: 6px 4px;
            vertical-align: middle;
            text-align: center;
            font-size: 10px;
        }
        table.schedule th.name,
        table.schedule td.name {
            text-align: left;
            width: 18%;
            font-weight: 700;
        }
        table.schedule th.day {
            width: 11%;
        }
        table.schedule th.total,
        table.schedule td.total {
            width: 10%;
            font-weight: 700;
        }
        .signatures {
            margin-top: 22px;
            display: table;
            width: 100%;
        }
        .sig-block {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            font-size: 11px;
        }
        .sig-label {
            margin-bottom: 6px;
        }
        .sig-name {
            font-weight: 700;
            margin-top: 2px;
            text-decoration: underline;
            text-align: center;
        }
        .sig-title {
            margin-top: 2px;
            font-size: 10px;
            text-align: center;
        }
        .sig-space {
            border-bottom: 1px solid #000;
            height: 18px;
            margin: 4px auto 6px;
            width: 70%;
        }
        .sig-indent {
            padding-left: 24px;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
@foreach($employees as $pageIndex => $pageEmployees)
    <div class="header">
        <div>Republic of the Philippines</div>
        <div>Department of Health</div>
        <div>Central Visayas Center for Health Development</div>
        <div><strong>CEBU SOUTH MEDICAL CENTER</strong></div>
        <div>(DR. JACINTO VELEZ SR. MEMORIAL HOSPITAL)</div>
        <div>San Isidro, Talisay City, Cebu</div>
        <div>"A PHIC Accredited and ISO 9001:2015 Certified Healthcare Provider"</div>
        <div class="title">SCHEDULE OF DUTY</div>
        <div class="title">MONTH: {{ $month_label }}</div>
    </div>

    <div class="meta">
        <div class="line"><strong>DIVISION:</strong> {{ $division }}</div>
        <div class="line"><strong>SECTION/ DEPARTMENT:</strong> {{ $section }}</div>
    </div>

    <table class="schedule">
        <thead>
        <tr>
            <th class="name">NAME/S</th>
            <th class="day">MON</th>
            <th class="day">TUE</th>
            <th class="day">WED</th>
            <th class="day">THUR</th>
            <th class="day">FRI</th>
            <th class="day">SAT</th>
            <th class="day">SUN</th>
            <th class="total">TOTAL NO. OF<br>HOURS</th>
        </tr>
        </thead>
        <tbody>
        @foreach($pageEmployees as $employee)
            <tr>
                <td class="name">{{ strtoupper($employee['name']) }}</td>
                @foreach($employee['schedule'] as $sched)
                    <td>{{ $sched }}</td>
                @endforeach
                <td class="total">{{ $employee['total_hours'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="signatures">
        <div class="sig-block sig-indent">
            <div class="sig-label">Prepared by:</div>
            <div class="sig-space"></div>
            <div class="sig-name">{{ strtoupper($prepared_by) }}</div>
            <div class="sig-title">{{ $prepared_title }}</div>
        </div>
        <div class="sig-block sig-indent">
            <div class="sig-label">Approved by:</div>
            <div class="sig-space"></div>
            <div class="sig-name">{{ strtoupper($approved_by) }}</div>
            <div class="sig-title">{{ $approved_title }}</div>
        </div>
    </div>

    @if($pageIndex < count($employees) - 1)
        <div class="page-break"></div>
    @endif
@endforeach
</body>
</html>
