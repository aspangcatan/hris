<html>
<head>
    <title>DTR</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <style>
        html, body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
        }

        table {
            margin: 0 auto;
            width: 100%;
            border-collapse: collapse; /* Prevent extra spacing between cells */
            table-layout: fixed; /* Ensures equal column width */
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
<table>
    <tr>
        <!-- First Column -->
        <td style="width: 46%; vertical-align: top; padding: 0;  border: 1px solid black;">
            <div class="dtr-container">
                <div class="form-title">Civil Service Form 48</div>
                <table style="width: 100%">
                    <tr>
                        <td style="width: 10%">
                            <img src="{{ url('img/logo.png') }}" width="75"/>
                        </td>
                        <td style="width: 13%"></td>
                        <td style="vertical-align: top;width: 50%">
                            <div class="form-header" style="padding-top: 15px">DAILY TIME RECORD</div>
                            <div class="employee-name">{{ $name }}</div>
                            <div style="text-align: center;font-weight: bold;font-size: 13px">(Name)</div>
                        </td>
                        <td></td>
                    </tr>
                </table>
                <div style="margin-top: 10px">For the month of: <strong>{{ $date }}</strong></div>
                <div style="margin-top: 5px;">
                    <span>Official hours of arrival and</span>
                    <span style="margin-left: 25px">Reg. days _____ AM _____ PM</span>
                </div>
                <div>
                    <span>departure</span>
                    <span style="margin-left: 108px">Saturdays _______________</span>
                </div>
            </div>
            <table class="logs" style="margin-top: 10px;margin-bottom: 10px">
                <tr class="header">
                    <td style="width: 8%;border-left: none" rowspan="2">DAY</td>
                    <td colspan="2">A.M</td>
                    <td colspan="2">P.M</td>
                    <td colspan="2">UNDERTIME</td>
                </tr>
                <tr class="header">
                    <td>Arrival</td>
                    <td>Departure</td>
                    <td>Arrival</td>
                    <td>Departure</td>
                    <td style="width: 13%">Hours</td>
                    <td style="width: 13%">Minutes</td>
                </tr>
                @foreach($dtrs as $dtr)
                    <tr>
                        <td style="font-weight: bold">{{ $dtr['day'] }}</td>
                        <td style="font-size: 10px">{{ $dtr['date_timein'] }}</td>
                        <td style="font-size: 10px">
                            @if(!is_numeric(substr($dtr['date_timein'], 0, 1)))
                                {{ $dtr['date_timein'] }}
                            @endif
                        </td>
                        <td style="font-size: 10px">
                            @if(!is_numeric(substr($dtr['date_timein'], 0, 1)))
                                {{ $dtr['date_timein'] }}
                            @endif
                        </td>
                        <td style="font-size: 10px">{{ $dtr['date_timeout'] }}</td>
                        <td></td>
                        <td></td>
                    </tr>

                @endforeach
                <tr>
                    <td style="border: none" colspan="4"></td>
                    <td style="border: none">TOTAL</td>
                    <td></td>
                    <td></td>
                </tr>
            </table>
            <div style="border-top: 1px solid black;padding: 6.5px">
                <div style="margin-top: 5px">I CERTIFY on my honor that the above is a true and correct report</div>
                <div>of the hours of work performed, record which was made daily at the</div>
                <div>time of arrival in and departure from office.</div>
                <table style="margin-top: 20px">
                    <tr>
                        <td style="width: 18%"></td>
                        <td style="border-bottom: 1px solid black;font-weight: bold;text-align: center">
                            {{ $name }}
                        </td>
                        <td style="width: 18%"></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td style="text-align: center">Verified as to the prescribed office hours</td>
                        <td></td>
                    </tr>

                    <tr style="border-top: 20px solid transparent;"> <!-- Margin added here -->
                        <td></td>
                        <td style="border-bottom: 1px solid black;text-align: center">
                            {{ $in_charged }}
                        </td>
                        <td></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td style="text-align: center">In Charged</td>
                        <td></td>
                    </tr>
                </table>

            </div>
        </td>
        <td style="width: 8%"></td>
        <!-- Second Column -->
        <td style="width: 46%; vertical-align: top; padding: 0;  border: 1px solid black;">
            <div class="dtr-container">
                <div class="form-title">Civil Service Form 48</div>
                <table style="width: 100%">
                    <tr>
                        <td style="width: 10%">
                            <img src="{{ url('img/logo.png') }}" width="75"/>
                        </td>
                        <td style="width: 13%"></td>
                        <td style="vertical-align: top;width: 50%">
                            <div class="form-header" style="padding-top: 15px">DAILY TIME RECORD</div>
                            <div class="employee-name">{{ $name }}</div>
                            <div style="text-align: center;font-weight: bold;font-size: 13px">(Name)</div>
                        </td>
                        <td></td>
                    </tr>
                </table>
                <div style="margin-top: 10px">For the month of: <strong>{{ $date }}</strong></div>
                <div style="margin-top: 5px;">
                    <span>Official hours of arrival and</span>
                    <span style="margin-left: 25px">Reg. days _____ AM _____ PM</span>
                </div>
                <div>
                    <span>departure</span>
                    <span style="margin-left: 108px">Saturdays _______________</span>
                </div>
            </div>
            <table class="logs" style="margin-top: 10px;margin-bottom: 10px">
                <tr class="header">
                    <td style="width: 8%;border-left: none" rowspan="2">DAY</td>
                    <td colspan="2">A.M</td>
                    <td colspan="2">P.M</td>
                    <td colspan="2">UNDERTIME</td>
                </tr>
                <tr class="header">
                    <td>Arrival</td>
                    <td>Departure</td>
                    <td>Arrival</td>
                    <td>Departure</td>
                    <td style="width: 13%">Hours</td>
                    <td style="width: 13%">Minutes</td>
                </tr>
                @foreach($dtrs as $dtr)
                    <tr>
                        <td style="font-weight: bold">{{ $dtr['day'] }}</td>
                        <td style="font-size: 10px">{{ $dtr['date_timein'] }}</td>
                        <td style="font-size: 10px">
                            @if(!is_numeric(substr($dtr['date_timein'], 0, 1)))
                                {{ $dtr['date_timein'] }}
                            @endif
                        </td>
                        <td style="font-size: 10px">
                            @if(!is_numeric(substr($dtr['date_timein'], 0, 1)))
                                {{ $dtr['date_timein'] }}
                            @endif
                        </td>
                        <td style="font-size: 10px">{{ $dtr['date_timeout'] }}</td>
                        <td></td>
                        <td></td>
                    </tr>

                @endforeach
                <tr>
                    <td style="border: none" colspan="4"></td>
                    <td style="border: none">TOTAL</td>
                    <td></td>
                    <td></td>
                </tr>
            </table>
            <div style="border-top: 1px solid black;padding: 6.5px">
                <div style="margin-top: 5px">I CERTIFY on my honor that the above is a true and correct report</div>
                <div>of the hours of work performed, record which was made daily at the</div>
                <div>time of arrival in and departure from office.</div>
                <table style="margin-top: 20px">
                    <tr>
                        <td style="width: 18%"></td>
                        <td style="border-bottom: 1px solid black;font-weight: bold;text-align: center">
                            {{ $name }}
                        </td>
                        <td style="width: 18%"></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td style="text-align: center">Verified as to the prescribed office hours</td>
                        <td></td>
                    </tr>

                    <tr style="border-top: 20px solid transparent;"> <!-- Margin added here -->
                        <td></td>
                        <td style="border-bottom: 1px solid black;text-align: center">
                            {{ $in_charged }}
                        </td>
                        <td></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td style="text-align: center">In Charged</td>
                        <td></td>
                    </tr>
                </table>

            </div>
        </td>
    </tr>
</table>
</body>
</html>

