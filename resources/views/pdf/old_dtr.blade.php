<html>
<head><title>DTR</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <style> html, body {
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

        table.employee-sheet {
            width: 100%;
            height: 282mm;
            table-layout: fixed;
        }

        table.employee-sheet > tbody > tr {
            height: 100%;
        }

        table.employee-sheet > tbody > tr > td {
            height: 100%;
        }

        .cut-divider-cell {
            width: 8%;
            padding: 0;
            position: relative;
        }

        .cut-divider-cell::before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 50%;
            border-left: 2px dashed black;
            transform: translateX(-50%);
        }

    </style>
</head>
<body>
@foreach($employees as $employee)
    @php
        $formatDuration = function ($minutes) {
            $minutes = (int)($minutes ?? 0);
            if ($minutes <= 0) return '0m';

            $hours = intdiv($minutes, 60);
            $mins = $minutes % 60;

            if ($hours > 0 && $mins > 0) return $hours . 'h ' . $mins . 'm';
            if ($hours > 0) return $hours . 'h';
            return $mins . 'm';
        };

        $tableBaseHours = collect($employee['dtrs'] ?? [])->sum(function ($dtr) {
            return is_numeric($dtr['undertime_hours'] ?? null) ? (int)$dtr['undertime_hours'] : 0;
        });
        $tableBaseMinutes = collect($employee['dtrs'] ?? [])->sum(function ($dtr) {
            return is_numeric($dtr['undertime_minutes'] ?? null) ? (int)$dtr['undertime_minutes'] : 0;
        });
        $tableTotalHours = $tableBaseHours + intdiv(max($tableBaseMinutes, 0), 60);
        $tableTotalMinutes = max($tableBaseMinutes, 0) % 60;

        $formatRegDays = function ($value) {
            $value = trim((string) ($value ?? ''));
            if ($value === '') return '';
            return preg_replace('/\s*,\s*/', "\n", $value);
        };
    @endphp
    <table class="employee-sheet">
        <tr> <!-- First Column -->
            <td style="width: 46%; vertical-align: top; padding: 0; border: 1px solid black;">
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
                    <div style="margin-top: 5px;font-size: 14px"><span>Official hours of arrival and</span> <span
                            style="margin-left: 25px">Reg. days
                            @if(!empty($employee['reg_days_am']))
                                <span style="display:inline-block; min-width: 40px; text-align: center; border-bottom: 1px solid black; font-weight: bold; line-height: 1.1;">{!! nl2br(e($formatRegDays($employee['reg_days_am']))) !!}</span>
                            @else
                                _____
                            @endif
                            AM
                            @if(!empty($employee['reg_days_pm']))
                                <span style="display:inline-block; min-width: 40px; text-align: center; border-bottom: 1px solid black; font-weight: bold; line-height: 1.1;">{!! nl2br(e($formatRegDays($employee['reg_days_pm']))) !!}</span>
                            @else
                                _____
                            @endif
                            PM</span></div>
                    <div style="font-size: 14px"><span>departure</span> <span style="margin-left: 132px">Saturdays _______________</span>
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
                            <!-- Day # -->
                            <td style="font-weight: bold">
                                {{ $dtr['day'] }}
                            </td>

                            <!-- AM Arrival -->
                            <td style="font-size: 11px">
                                @if(!empty($dtr['date_timein_am']) && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $dtr['date_timein_am']))
                                    {{ $dtr['date_timein_am'] }}
                                @elseif($dtr['isEmpty'] && $dtr['isInsideCutoff'])
                                    {{ $dtr['day_name'] }}
                                @endif
                            </td>

                            <!-- AM Departure -->
                            <td style="font-size: 11px">
                                @if(!empty($dtr['date_timeout_am']) && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $dtr['date_timeout_am']))
                                    {{ $dtr['date_timeout_am'] }}
                                @elseif($dtr['isEmpty'] && $dtr['isInsideCutoff'])
                                    {{ $dtr['day_name'] }}
                                @endif
                            </td>

                            <!-- PM Arrival -->
                            <td style="font-size: 11px">
                                @if(!empty($dtr['date_timein_pm']) && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $dtr['date_timein_pm']))
                                    {{ $dtr['date_timein_pm'] }}
                                @elseif($dtr['isEmpty'] && $dtr['isInsideCutoff'])
                                    {{ $dtr['day_name'] }}
                                @endif
                            </td>

                            <!-- PM Departure -->
                            <td style="font-size: 11px">
                                @if(!empty($dtr['date_timeout_pm']) && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $dtr['date_timeout_pm']))
                                    {{ $dtr['date_timeout_pm'] }}
                                @elseif($dtr['isEmpty'] && $dtr['isInsideCutoff'])
                                    {{ $dtr['day_name'] }}
                                @endif
                            </td>

                            <!-- UNDERTIME (same rule as before) -->
                            @if(!empty($dtr['off_label']) && empty($dtr['suppress_off_label']))
                                <td style="font-size: 11px; font-weight: bold; line-height: 1.1;" colspan="2">
                                    {{ $dtr['off_label'] }}
                                </td>
                            @elseif(!empty($dtr['day_marker']))
                                @php
                                    $isLateAndOt = str_contains((string) $dtr['day_marker'], 'L:') && str_contains((string) $dtr['day_marker'], 'OT:');
                                @endphp
                                <td style="font-size: {{ $isLateAndOt ? '8px' : '11px' }}; font-weight: bold; line-height: 1.1;" colspan="2">
                                    {{ $dtr['day_marker'] }}
                                </td>
                            @else
                                 @if($employee['division'] == '2')
                                <td style="font-size: 9.5px">{{ $dtr['undertime_hours'] }}</td>
                                <td style="font-size: 9.5px">{{ $dtr['undertime_minutes'] }}</td>
                                @else
                                <td style="font-size: 9.5px"></td>
                                <td style="font-size: 9.5px"></td>
                                @endif
                            @endif
                        </tr>
                    @endforeach

                    <tr>
                        <td style="border: none" colspan="4"></td>
                        <td style="border: none">TOTAL</td>
                        <td style="font-weight: bold">{{ max($tableTotalHours, 0) }}</td>
                        <td style="font-weight: bold">{{ max($tableTotalMinutes, 0) }}</td>
                    </tr>
                </table>
                <div style="border-top: 1px solid black;padding: 6.5px;font-size: 11.5px; position: relative;">
                    <div style="position: absolute; right: 30px; top: 50px; font-weight: bold; font-size: 12px; text-align: start;">
                          @if($employee['division'] == '2')
                                <div>U - {{ $employee['undertime_count'] }} ({{ $formatDuration($employee['undertime_total_minutes'] ?? 0) }})</div>
                                <div>L - {{ $employee['late_count'] }} ({{ $formatDuration($employee['late_total_minutes'] ?? 0) }})</div>
                          @endif
                    </div>
                    <div style="margin-top: 5px">I CERTIFY on my honor that the above is a true and correct report</div>
                    <div>of the hours of work performed, record which was made daily at the</div>
                    <div>time of arrival in and departure from office.</div>
                    <table style="margin-top: 20px">
                        <tr>
                            <td style="width: 18%"></td>
                            <td style="border-bottom: 1px solid black;font-weight: bold;text-align: center"> {{ $employee['name'] }} </td>
                            <td style="width: 18%"></td>
                        </tr>
                        <tr>
                            <td></td>
                            <td style="text-align: center;font-size: 12.5px">Verified as to the prescribed office hours
                            </td>
                            <td></td>
                        </tr>
                        <tr style="border-top: 20px solid transparent;"> <!-- Margin added here -->
                            <td></td>
                            <td style="border-bottom: 1px solid black;text-align: center"> {{ $employee['in_charged'] }} </td>
                            <td></td>
                        </tr>
                        <tr>
                            <td></td>
                            <td style="text-align: center;font-size: 12.5px">In Charge</td>
                            <td></td>
                        </tr>
                    </table>
                </div>
            </td>
            <td class="cut-divider-cell"></td>
            <td style="width: 46%; vertical-align: top; padding: 0; border: 1px solid black;">
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
                    <div style="margin-top: 5px;font-size: 14px"><span>Official hours of arrival and</span> <span
                            style="margin-left: 25px">Reg. days
                            @if(!empty($employee['reg_days_am']))
                                <span style="display:inline-block; min-width: 40px; text-align: center; border-bottom: 1px solid black; font-weight: bold; line-height: 1.1;">{!! nl2br(e($formatRegDays($employee['reg_days_am']))) !!}</span>
                            @else
                                _____
                            @endif
                            AM
                            @if(!empty($employee['reg_days_pm']))
                                <span style="display:inline-block; min-width: 40px; text-align: center; border-bottom: 1px solid black; font-weight: bold; line-height: 1.1;">{!! nl2br(e($formatRegDays($employee['reg_days_pm']))) !!}</span>
                            @else
                                _____
                            @endif
                            PM</span></div>
                    <div style="font-size: 14px"><span>departure</span> <span style="margin-left: 132px">Saturdays _______________</span>
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
                            <!-- Day # -->
                            <td style="font-weight: bold">
                                {{ $dtr['day'] }}
                            </td>

                            <!-- AM Arrival -->
                            <td style="font-size: 11px">
                                @if(!empty($dtr['date_timein_am']) && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $dtr['date_timein_am']))
                                    {{ $dtr['date_timein_am'] }}
                                @elseif($dtr['isEmpty'] && $dtr['isInsideCutoff'])
                                    {{ $dtr['day_name'] }}
                                @endif
                            </td>

                            <!-- AM Departure -->
                            <td style="font-size: 11px">
                                @if(!empty($dtr['date_timeout_am']) && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $dtr['date_timeout_am']))
                                    {{ $dtr['date_timeout_am'] }}
                                @elseif($dtr['isEmpty'] && $dtr['isInsideCutoff'])
                                    {{ $dtr['day_name'] }}
                                @endif
                            </td>

                            <!-- PM Arrival -->
                            <td style="font-size: 11px">
                                @if(!empty($dtr['date_timein_pm']) && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $dtr['date_timein_pm']))
                                    {{ $dtr['date_timein_pm'] }}
                                @elseif($dtr['isEmpty'] && $dtr['isInsideCutoff'])
                                    {{ $dtr['day_name'] }}
                                @endif
                            </td>

                            <!-- PM Departure -->
                            <td style="font-size: 11px">
                                @if(!empty($dtr['date_timeout_pm']) && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $dtr['date_timeout_pm']))
                                    {{ $dtr['date_timeout_pm'] }}
                                @elseif($dtr['isEmpty'] && $dtr['isInsideCutoff'])
                                    {{ $dtr['day_name'] }}
                                @endif
                            </td>

                            <!-- UNDERTIME (same rule as before) -->
                            @if(!empty($dtr['off_label']) && empty($dtr['suppress_off_label']))
                                <td style="font-size: 11px; font-weight: bold; line-height: 1.1;" colspan="2">
                                    {{ $dtr['off_label'] }}
                                </td>
                            @elseif(!empty($dtr['day_marker']))
                                @php
                                    $isLateAndOt = str_contains((string) $dtr['day_marker'], 'L:') && str_contains((string) $dtr['day_marker'], 'OT:');
                                @endphp
                                <td style="font-size: {{ $isLateAndOt ? '8px' : '11px' }}; font-weight: bold; line-height: 1.1;" colspan="2">
                                    {{ $dtr['day_marker'] }}
                                </td>
                            @else
                                @if($employee['division'] == '2')
                                <td style="font-size: 9.5px">{{ $dtr['undertime_hours'] }}</td>
                                <td style="font-size: 9.5px">{{ $dtr['undertime_minutes'] }}</td>
                                @else
                                <td style="font-size: 9.5px"></td>
                                <td style="font-size: 9.5px"></td>
                                @endif
                            @endif
                        </tr>
                    @endforeach
                    <tr>
                        <td style="border: none" colspan="4"></td>
                        <td style="border: none">TOTAL</td>
                        <td style="font-weight: bold">{{ max($tableTotalHours, 0) }}</td>
                        <td style="font-weight: bold">{{ max($tableTotalMinutes, 0) }}</td>
                    </tr>
                </table>
                <div style="border-top: 1px solid black;padding: 6.5px;font-size: 12px; position: relative;">
                    <div style="position: absolute; right: 20px; top: 50px; font-weight: bold; font-size: 11.5px; text-align: start;">
                         @if($employee['division'] == '2')
                        <div>U - {{ $employee['undertime_count'] }} ({{ $formatDuration($employee['undertime_total_minutes'] ?? 0) }})</div>
                        <div>L - {{ $employee['late_count'] }} ({{ $formatDuration($employee['late_total_minutes'] ?? 0) }})</div>
                        @endif
                    </div>
                    <div style="margin-top: 5px">I CERTIFY on my honor that the above is a true and correct report</div>
                    <div>of the hours of work performed, record which was made daily at the</div>
                    <div>time of arrival in and departure from office.</div>
                    <table style="margin-top: 20px">
                        <tr>
                            <td style="width: 18%"></td>
                            <td style="border-bottom: 1px solid black;font-weight: bold;text-align: center"> {{ $employee['name'] }} </td>
                            <td style="width: 18%"></td>
                        </tr>
                        <tr>
                            <td></td>
                            <td style="text-align: center;font-size: 14px">Verified as to the prescribed office hours
                            </td>
                            <td></td>
                        </tr>
                        <tr style="border-top: 20px solid transparent;"> <!-- Margin added here -->
                            <td></td>
                            <td style="border-bottom: 1px solid black;text-align: center"> {{ $employee['in_charged'] }} </td>
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
@endforeach </body>
</html>
