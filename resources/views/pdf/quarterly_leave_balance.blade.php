<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 20px 28px;
        }
        body {
            font-family: "Helvetica", "Arial", sans-serif;
            color: #000;
            font-size: 10px;
        }
        .header {
            text-align: center;
            line-height: 1.3;
            margin-bottom: 10px;
        }
        .header .title {
            font-weight: 700;
            margin-top: 4px;
        }
        .meta {
            margin: 8px 0 5px;
            font-size: 10px;
        }
        .meta .line {
            margin-bottom: 2px;
        }

        /* ── Main report table ── */
        table.report {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table.report th,
        table.report td {
            border: 1px solid #000;
            padding: 2px 4px;
            vertical-align: middle;
            font-size: 8px;
            word-wrap: break-word;
        }
        table.report thead th {
            background: #d4d4d4;
            font-weight: 700;
            text-align: center;
        }
        table.report td.name {
            text-align: left;
            font-weight: 700;
        }
        table.report td.center {
            text-align: center;
        }
        table.report td.balance {
            text-align: left;
        }
        .neg { color: #c1121f; }

        /* ── Legend ── */
        .legend-wrap {
            margin-top: 14px;
        }
        table.legend {
            font-size: 8px;
            width: 55%;
            border: 1px solid #000;
            border-collapse: collapse;
        }
        table.legend th,
        table.legend td {
            border: 1px solid #000;
            padding: 2px 5px;
        }
        table.legend th {
            text-align: center;
            font-weight: 700;
        }
        table.legend td.code {
            font-weight: 700;
            text-align: center;
            width: 8%;
        }
        table.legend td.desc {
            width: 42%;
        }

        .page-break { page-break-after: always; }
    </style>
</head>
<body>
@foreach($pages as $pageIndex => $pageEmployees)
<div class="header">
    <div>Republic of the Philippines</div>
    <div>Department of Health</div>
    <div>Central Visayas Center for Health Development</div>
    <div><strong>CEBU SOUTH MEDICAL CENTER</strong></div>
    <div>(DR. JACINTO VELEZ SR. MEMORIAL HOSPITAL)</div>
    <div>San Isidro, Talisay City, Cebu</div>
    <div>"A PHIC Accredited and ISO 9001:2015 Certified Healthcare Provider"</div>
    <div class="title">QUARTERLY LEAVE BALANCES</div>
    <div class="title">YEAR: {{ $year }} &nbsp;&bull;&nbsp; {{ $quarter_label }}</div>
</div>

<div class="meta">
    <div class="line"><strong>DIVISION:</strong> {{ $division }}</div>
    <div class="line"><strong>SECTION / DEPARTMENT:</strong> {{ $section }}</div>
</div>

<table class="report">
    <thead>
        <tr>
            <th style="width:22%">NAME/S</th>
            <th style="width:18%">DIVISION</th>
            <th style="width:15%">SECTION / DEPT.</th>
            <th style="width:45%">BALANCE</th>
        </tr>
    </thead>
    <tbody>
        @foreach($pageEmployees as $emp)
        <tr>
            <td class="name">{{ $emp['name'] }}</td>
            <td class="center">{{ $emp['division'] }}</td>
            <td class="center">{{ $emp['section'] }}</td>
            <td class="balance">
                @foreach($emp['parts'] as $i => $part)
                    @if($part['value'] < 0)
                        <span class="neg">({{ $part['code'] }}={{ number_format($part['value'], 3) }})</span>
                    @else
                        ({{ $part['code'] }}={{ number_format($part['value'], 3) }})
                    @endif
                    @if(!$loop->last), @endif
                @endforeach
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- Legend only on the last page --}}
@if($pageIndex === count($pages) - 1 && count($legend) > 0)
@php $legendRows = collect($legend)->chunk(2); @endphp
<div class="legend-wrap">
    <table class="legend">
        <thead>
            <tr>
                <th colspan="4" style="background:#f1c40f">LEGEND</th>
            </tr>
            <tr>
                <th>CODE</th>
                <th>LEAVE TYPE</th>
                <th>CODE</th>
                <th>LEAVE TYPE</th>
            </tr>
        </thead>
        <tbody>
            @foreach($legendRows as $row)
            <tr>
                @foreach($row as $item)
                    <td class="code">{{ $item['code'] }}</td>
                    <td class="desc">{{ $item['description'] }}</td>
                @endforeach
                @if($row->count() < 2)
                    <td class="code">&nbsp;</td>
                    <td class="desc">&nbsp;</td>
                @endif
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

@if($pageIndex < count($pages) - 1)
    <div class="page-break"></div>
@endif
@endforeach
</body>
</html>
