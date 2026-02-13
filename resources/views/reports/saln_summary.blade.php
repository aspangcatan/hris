<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SALN Summary List of Filers - {{ $year }}</title>
    <style>
        @media print {
            @page { margin: 0.5in; size: landscape; }
            .no-print { display: none; }
        }
        body { font-family: 'Arial', sans-serif; font-size: 10px; color: #000; line-height: 1.2; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h2 { margin: 0; font-size: 14px; text-transform: none; }
        .header h1 { margin: 0; font-size: 16px; font-weight: bold; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #000; padding: 4px 6px; }
        th { text-align: center; vertical-align: middle; font-weight: bold; background: #fff; height: 25px; }
        
        .name-header { height: 15px; font-size: 9px; }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-uppercase { text-transform: uppercase; }
        .fw-bold { font-weight: bold; }
        
        .stats-section { margin-top: 20px; font-size: 11px; font-weight: bold; }
        .stats-row { margin-bottom: 5px; }
        .stats-row span { border-bottom: 1px solid #000; padding: 0 10px; min-width: 40px; display: inline-block; text-align: center; }
        
        .signatures { margin-top: 30px; width: 100%; border: none; }
        .signatures td { border: none; padding: 0; vertical-align: top; width: 50%; }
        
        .sig-block { margin-top: 40px; }
        .sig-line { border-bottom: 1px solid #000; width: 80%; margin: 0 auto; margin-bottom: 2px; }
        .sig-name { font-weight: bold; text-decoration: none; border-bottom: none !important; }
        .sig-info { font-style: normal; margin-top: 2px; }
        
        .btn-print {
            background: #444; color: white; border: none; padding: 10px 20px; border-radius: 4px;
            cursor: pointer; font-weight: bold; margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: right;">
        <button class="btn-print" onclick="window.print()">PRINT REPORT</button>
    </div>

    <div class="header">
        <h1>Cebu South Medical Center</h1>
        <h2>Summary List of Filers</h2>
        <h2>Statement of Assets, Liabilities and Networth</h2>
        <div style="font-weight: bold; font-size: 12px; margin-top: 5px;">Calendar Year {{ $year }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2" style="width: 30px;">No.</th>
                <th colspan="3">NAME OF EMPLOYEE</th>
                <th rowspan="2" style="width: 100px;">TIN</th>
                <th rowspan="2">POSITION</th>
                <th rowspan="2" style="width: 100px;">NET WORTH</th>
            </tr>
            <tr class="name-header">
                <th style="width: 120px;">Lastname</th>
                <th style="width: 120px;">Firstname</th>
                <th style="width: 100px;">Middlename</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $index => $record)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="text-uppercase">{{ $record->lname }}</td>
                <td class="text-uppercase">{{ $record->fname }} @if($record->suffix) {{ $record->suffix }} @endif</td>
                <td class="text-uppercase">{{ $record->mname }}</td>
                <td class="text-center">{{ $record->tin_no }}</td>
                <td>{{ $record->position }}</td>
                <td class="text-right">
                    @if($record->net_worth < 0)
                        ({{ number_format(abs($record->net_worth), 2) }})
                    @else
                        {{ number_format($record->net_worth, 2) }}
                    @endif
                </td>
            </tr>
            @endforeach
            @if(count($records) == 0)
            <tr>
                <td colspan="7" class="text-center py-4">No compliant records found for this year.</td>
            </tr>
            @endif
        </tbody>
    </table>

    <div class="stats-section">
        <div class="stats-row">Total Number of Filers: <span>{{ $total_filers }}</span></div>
        <div class="stats-row">Total Number of Personnel Complement: <span>{{ $total_personnel }}</span></div>
    </div>

    <table class="signatures">
        <tr>
            <td>
                <div class="sig-block" style="text-align: left; padding-left: 20px;">
                    <p>Prepared by:</p>
                    <div style="margin-top: 40px; text-align: center; width: 300px;">
                        <div class="sig-line" style="width: 100%;">
                            <span class="sig-name">RACHEL C. JALUAG</span>
                        </div>
                        <div class="sig-info">
                            <strong>Person In-charge of SALN</strong><br>
                            Position: Supervising Administrative Officer<br>
                            Email Address: hrms@csmc.doh.gov.ph<br>
                            Contact No.: (032) 265-5986 Local 118
                        </div>
                        <div style="margin-top: 15px; text-align: left;">
                            Date: {{ \Carbon\Carbon::now()->format('F d, Y') }}
                        </div>
                    </div>
                </div>
            </td>
            <td>
                <div class="sig-block" style="text-align: left; padding-left: 50px;">
                    <p>Noted by:</p>
                    <div style="margin-top: 40px; text-align: center; width: 350px;">
                        <div class="sig-line" style="width: 100%;">
                            <span class="sig-name">AGUSTIN D. AGOS, JR., MD, FPSGS, FPCS, MA Psy, DODT, PhD-OD, RODC, APRM™</span>
                        </div>
                        <div class="sig-info">
                            <strong>Head of Agency</strong><br>
                            Position: Medical Chief II<br>
                            Mailing Address: San Isidro, Talisay City, Cebu<br>
                            Contact No.: (032) 265-5986 Local 101
                        </div>
                        <div style="margin-top: 15px; text-align: left;">
                            Date: {{ \Carbon\Carbon::now()->format('F d, Y') }}
                        </div>
                    </div>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
