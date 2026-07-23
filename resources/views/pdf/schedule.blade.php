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
            font-size: 10px;
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
            padding: 1px 1px;
            vertical-align: middle;
            text-align: center;
            font-size: 8px;
        }
        table.schedule th.name,
        table.schedule td.name {
            text-align: left;
            width: 18%;
            font-weight: 700;
        }
        table.schedule th.name {
            font-size: 9px;
            letter-spacing: 0.5px;
        }
        table.schedule th.day {
            width: 2.5%;
        }
        table.schedule th.remarks,
        table.schedule td.remarks {
            width: 8%;
            font-weight: 700;
        }
        table.schedule thead .day-label {
            font-size: 6px;
            letter-spacing: 0.3px;
        }
        table.schedule thead .day-number {
            font-size: 7px;
        }
        table.schedule thead th.day {
            padding: 0 1px;
        }
        .weekend {
            background: #000;
            color: #fff;
        }
        table.schedule td.code {
            font-weight: 700;
        }
        .legend {
            font-size: 8px;
            width: 40%;
            border: 1px solid #000;
            border-collapse: collapse;
        }
        .legend thead th {
            border: 1px solid #000;
        }
        .legend th,
        .legend td {
            border: 1px solid #000;
            padding: 2px 4px;
            text-align: left;
        }
        .legend th {
            text-align: center;
            font-weight: 700;
        }
        .legend td.code {
            width: 4%;
            font-weight: 700;
            text-align: center;
        }
        .legend td.time {
            text-align: center;
            width: 35%;
        }
        .text-off {
            color: #c1121f;
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
        .legend-wrap {
            margin-top: 12px;
            text-align: center;
        }
        .footer-fixed {
            position: fixed;
            left: 32px;
            right: 32px;
            bottom: 24px;
        }
    </style>
</head>
<body>
@include('pdf.schedule_footer', [
    'prepared_by' => $prepared_by,
    'prepared_title' => $prepared_title,
    'approved_by' => $approved_by,
    'approved_title' => $approved_title,
    'footer_fixed' => true
])
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
            <th class="name" rowspan="2" style="text-align: center">NAME/S</th>
            @foreach($day_numbers as $idx => $day)
                <th class="day day-number">{{ $day }}</th>
            @endforeach
            <th class="remarks" rowspan="2">SIGNATURE</th>
        </tr>
        <tr class="day-label">
            @foreach($day_labels as $idx => $label)
                <th class="day {{ $day_is_weekend[$idx] ? 'weekend' : '' }}">{{ $label }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach($pageEmployees as $employee)
            <tr>
                <td class="name">{{ strtoupper($employee['name']) }}</td>
                @foreach($employee['schedule'] as $idx => $sched)
                    @php
                        $isOff = trim((string) $sched) === '' || strtoupper((string) $sched) === 'RD' || strtoupper((string) $sched) === 'OFF';
                    @endphp
                    <td class="code {{ $isOff ? 'text-off' : '' }}">{{ $sched }}</td>
                @endforeach
                <td class="remarks">{{ $employee['remarks'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="legend-wrap" style="margin-top:20px">
    @php
        $legendRows = $legend_items
            ->filter(function ($item) {
                return strtoupper((string) $item->code) !== 'OFF';
            })
            ->values()
            ->chunk(2);
    @endphp
    <table class="legend">
        <thead>
        <tr>
            <th colspan="4" style="background: #f1c40f">LEGEND</th>
        </tr>
        <tr>
            <th>CODE</th>
            <th>TIME</th>
            <th>CODE</th>
            <th>TIME</th>
        </tr>
        </thead>
        <tbody>
        @foreach($legendRows as $row)
            <tr>
                @foreach($row as $item)
                    @php
                        $start = $item->time_in === $item->time_in_latest
                            ? \Carbon\Carbon::parse($item->time_in)->format('h:i A')
                            : \Carbon\Carbon::parse($item->time_in)->format('h:i A') . '-' . \Carbon\Carbon::parse($item->time_in_latest)->format('h:i A');
                        $end = $item->time_out === $item->time_out_latest
                            ? \Carbon\Carbon::parse($item->time_out)->format('h:i A')
                            : \Carbon\Carbon::parse($item->time_out)->format('h:i A') . '-' . \Carbon\Carbon::parse($item->time_out_latest)->format('h:i A');
                        $time = $start . ' - ' . $end;
                    @endphp
                    <td class="code">{{ strtoupper($item->code) }}</td>
                    <td class="time">{{ $time }}</td>
                @endforeach
                @if($row->count() < 2)
                    <td class="code">&nbsp;</td>
                    <td class="time">&nbsp;</td>
                @endif
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

    @if($pageIndex < count($employees) - 1)
        <div class="page-break"></div>
    @endif
@endforeach
</body>
</html>
