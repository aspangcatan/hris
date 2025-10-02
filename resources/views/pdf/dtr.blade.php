<html>
<head>
    <title>DTR</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <style>
        html, body {
            font-family: Arial, sans-serif;
            font-size: 11px !important;
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
        }

        table {
            margin: 0 auto;
            width: 100%;
            border-collapse: collapse; /* Prevent extra spacing between cells */
        }

        .dtr-container {
            padding: 5px;
        }

        .form-title {
            text-align: right;
            margin: 0;
            font-size: 12.5px;
        }

        .form-header {
            text-align: center;
            font-size: 15px;
            font-weight: bold;
        }

        .employee-name {
            font-weight: bold;
            font-size: 12px;
            border-bottom: 1px solid black;
            text-align: center;
            margin-top: 10px;
        }

        table.logs td {
            font-size: 10px;
            text-align: center;
            vertical-align: middle;
            border: 1px solid black;
            min-height: 10px;
            padding-top: 3px;
            padding-bottom: 3px;
        }

        table.logs td:first-child {
            border-left: none;
        }

        table.logs td:last-child {
            border-right: none;
        }

        table.logs tr.header {
            font-weight: bold;
        }
    </style>
</head>
<body>
@foreach($employees as $employee)
    <table>
        <tr>
            <!-- Left column -->
            <td style="width: 46%; vertical-align: top; padding: 0;  border: 1px solid black;">
                <div class="dtr-container">
                    <div class="form-title">Civil Service Form 48</div>
                    <table style="width: 100%">
                        <tr>
                            <td style="width: 10%"><img src="{{ url('img/logo.png') }}" width="75"/></td>
                            <td style="width: 13%"></td>
                            <td style="vertical-align: top;width: 50%">
                                <div class="form-header" style="padding-top: 15px">DAILY TIME RECORD</div>
                                <div class="employee-name">{{ $employee['name'] }}</div>
                                <div style="text-align: center;font-weight: bold;font-size: 13px">(Name)</div>
                            </td>
                            <td></td>
                        </tr>
                    </table>

                    <div style="margin-top: 15px;font-size: 14px">For the month of:
                        <strong>{{ $employee['date'] }}</strong></div>
                    <div style="margin-top: 5px;font-size: 14px">
                        <span>Official hours of arrival and</span>
                        <span style="margin-left: 25px">Reg. days _____ AM _____ PM</span>
                    </div>
                    <div style="font-size: 14px">
                        <span>departure</span>
                        <span style="margin-left: 132px">Saturdays _______________</span>
                    </div>
                </div>

                <table class="logs" style="margin-top: 10px;margin-bottom: 10px">
                    <tr class="header">
                        <td style="width: 8%;border-left: none;font-size: 12px" rowspan="2">DAY</td>
                        <td colspan="2">A.M</td>
                        <td colspan="2">P.M</td>
                        <td colspan="2">UNDERTIME</td>
                    </tr>
                    <tr class="header">
                        <td style="font-size: 12px">Arrival</td>
                        <td style="font-size: 12px">Departure</td>
                        <td style="font-size: 12px">Arrival</td>
                        <td style="font-size: 12px">Departure</td>
                        <td style="width: 12%;font-size: 12px">Hours</td>
                        <td style="width: 12%;font-size: 12px">Minutes</td>
                    </tr>

                    @foreach($employee['dtrs'] as $dtr)
                        <tr>
                            <td style="font-weight: bold">{{ $dtr['day'] }}</td>
                            <td style="font-size: 11px">{{ $dtr['am_arrival'] ?? '' }}</td>
                            <td style="font-size: 11px">{{ $dtr['am_departure'] ?? '' }}</td>
                            <td style="font-size: 11px">{{ $dtr['pm_arrival'] ?? '' }}</td>
                            <td style="font-size: 11px">{{ $dtr['pm_departure'] ?? '' }}</td>
                            <td style="font-size: 12px"></td>
                            <td style="font-size: 12px"></td>
                        </tr>
                    @endforeach

                    <tr>
                        <td style="border: none" colspan="4"></td>
                        <td style="border: none">TOTAL</td>
                        <td></td>
                        <td></td>
                    </tr>
                </table>

                <div style="border-top: 1px solid black;padding: 6.5px;font-size: 13px">
                    <div style="margin-top: 5px">I CERTIFY on my honor that the above is a true and correct report</div>
                    <div>of the hours of work performed, record which was made daily at the</div>
                    <div>time of arrival in and departure from office.</div>
                    <table style="margin-top: 20px">
                        <tr>
                            <td style="width: 18%"></td>
                            <td style="border-bottom: 1px solid black;font-weight: bold;text-align: center">
                                {{ $employee['name'] }}
                            </td>
                            <td style="width: 18%"></td>
                        </tr>
                        <tr>
                            <td></td>
                            <td style="text-align: center;font-size: 14px">Verified as to the prescribed office hours</td>
                            <td></td>
                        </tr>
                        <tr style="border-top: 20px solid transparent;">
                            <td></td>
                            <td style="border-bottom: 1px solid black;text-align: center">
                                {{ $employee['in_charged'] }}
                            </td>
                            <td></td>
                        </tr>
                        <tr>
                            <td></td>
                            <td style="text-align: center;font-size: 14px">In Charge</td>
                            <td></td>
                        </tr>
                    </table>
                </div>
            </td>

            <td style="width: 8%"></td>

            <!-- Right column (copy of left) -->
            <td style="width: 46%; vertical-align: top; padding: 0;  border: 1px solid black;">
                <div class="dtr-container">
                    <div class="form-title">Civil Service Form 48</div>
                    <table style="width: 100%">
                        <tr>
                            <td style="width: 10%"><img src="{{ url('img/logo.png') }}" width="75"/></td>
                            <td style="width: 13%"></td>
                            <td style="vertical-align: top;width: 50%">
                                <div class="form-header" style="padding-top: 15px">DAILY TIME RECORD</div>
                                <div class="employee-name">{{ $employee['name'] }}</div>
                                <div style="text-align: center;font-weight: bold;font-size: 13px">(Name)</div>
                            </td>
                            <td></td>
                        </tr>
                    </table>

                    <div style="margin-top: 15px;font-size: 14px">For the month of:
                        <strong>{{ $employee['date'] }}</strong></div>
                    <div style="margin-top: 5px;font-size: 14px">
                        <span>Official hours of arrival and</span>
                        <span style="margin-left: 25px">Reg. days _____ AM _____ PM</span>
                    </div>
                    <div style="font-size: 14px">
                        <span>departure</span>
                        <span style="margin-left: 132px">Saturdays _______________</span>
                    </div>
                </div>

                <table class="logs" style="margin-top: 10px;margin-bottom: 10px">
                    <tr class="header">
                        <td style="width: 8%;border-left: none;font-size: 12px" rowspan="2">DAY</td>
                        <td colspan="2">A.M</td>
                        <td colspan="2">P.M</td>
                        <td colspan="2">UNDERTIME</td>
                    </tr>
                    <tr class="header">
                        <td style="font-size: 12px">Arrival</td>
                        <td style="font-size: 12px">Departure</td>
                        <td style="font-size: 12px">Arrival</td>
                        <td style="font-size: 12px">Departure</td>
                        <td style="width: 12%;font-size: 12px">Hours</td>
                        <td style="width: 12%;font-size: 12px">Minutes</td>
                    </tr>

                    @foreach($employee['dtrs'] as $dtr)
                        <tr>
                            <td style="font-weight: bold">{{ $dtr['day'] }}</td>
                            <td style="font-size: 11px">{{ $dtr['am_arrival'] ?? '' }}</td>
                            <td style="font-size: 11px">{{ $dtr['am_departure'] ?? '' }}</td>
                            <td style="font-size: 11px">{{ $dtr['pm_arrival'] ?? '' }}</td>
                            <td style="font-size: 11px">{{ $dtr['pm_departure'] ?? '' }}</td>
                            <td style="font-size: 12px"></td>
                            <td style="font-size: 12px"></td>
                        </tr>
                    @endforeach

                    <tr>
                        <td style="border: none" colspan="4"></td>
                        <td style="border: none">TOTAL</td>
                        <td></td>
                        <td></td>
                    </tr>
                </table>

                <div style="border-top: 1px solid black;padding: 6.5px;font-size: 13px">
                    <div style="margin-top: 5px">I CERTIFY on my honor that the above is a true and correct report</div>
                    <div>of the hours of work performed, record which was made daily at the</div>
                    <div>time of arrival in and departure from office.</div>
                    <table style="margin-top: 20px">
                        <tr>
                            <td style="width: 18%"></td>
                            <td style="border-bottom: 1px solid black;font-weight: bold;text-align: center">
                                {{ $employee['name'] }}
                            </td>
                            <td style="width: 18%"></td>
                        </tr>
                        <tr>
                            <td></td>
                            <td style="text-align: center;font-size: 14px">Verified as to the prescribed office hours</td>
                            <td></td>
                        </tr>
                        <tr style="border-top: 20px solid transparent;">
                            <td></td>
                            <td style="border-bottom: 1px solid black;text-align: center">
                                {{ $employee['in_charged'] }}
                            </td>
                            <td></td>
                        </tr>
                        <tr>
                            <td></td>
                            <td style="text-align: center;font-size: 14px">In Charge</td>
                            <td></td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>
@endforeach
</body>
</html>
