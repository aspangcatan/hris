<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
    <style>
        @page {
            size: 612pt 1008pt !important;
        }

        html, body {
            margin: 0px;
            font-size: 16px;
            font-family: Arial, sans-serif;
        }

        table {
            width: 100%;
            table-layout: fixed !important;
            border-spacing: 0; /* Removes the cell spacing via CSS */
            border-collapse: collapse; /* Optional - if you don't want to have double border where cells touch */
        }

        table td {
            margin: 0px !important;
            height: 15px !important;
            padding: 1px !important;
            vertical-align: middle !important;
        }

        .center {
            text-align: center;
            vertical-align: middle;
        }

        .bt {
            border-top: 1px solid black;
        }

        .bb {
            border-bottom: 1px solid black;
        }

        .bl {
            border-left: 1px solid black;
        }

        .br {
            border-right: 1px solid black;
        }
    </style>
</head>
<body>
<div style="padding:10px">
    <div>Attachment to CS Form No. 212</div>
    <table>
        <tr class="center border bt bl br" style="background: gray">
            <td colspan="2" style="font-size: 18.66px;font-weight: bold;color:white">
                <div style="padding: 5px">
                    WORK EXPERIENCE SHEET
                </div>
            </td>
        </tr>
        <tr class="border bt bl br" style="background: silver">
            <td style="width: 20%">
                <strong style="margin-left: 5px">Instructions: </strong>
            </td>
            <td style="font-style: italic">
                1. Include only the work experiences relevant to the position being applied to.
            </td>
        </tr>
        <tr class="bl br" style="background: silver">
            <td colspan="2"></td>
        </tr>
        <tr class="bb bl br" style="background: silver">
            <td></td>
            <td style="font-style: italic">
                2. The duration should include start and finish dates, if known, month in abbreviated form, if known,
                and year
                in full. For the current position, use the word Present, e.g., 1998-Present. Work experience should be
                listed
                from most recent first
            </td>
        </tr>
        @foreach($related_work as $key)
            <tr class="bl bt bb br">
                <td colspan="2">
                    <ul>
                        <li>Duration: <span style="margin-left: 5px">{{ $key['duration'] }}</span></li>
                        <li>Position: <span style="margin-left: 5px">{{ $key['position'] }}</span></li>
                        <li>Name of Office/Unit: <span style="margin-left: 5px">{{ $key['office_unit'] }}</span></li>
                        <li>Immediate Supervisor: <span style="margin-left: 5px">{{ $key['supervisor'] }}</span></li>
                        <li>Name of Agency/Organization and Location: <span
                                style="margin-left: 5px">{{ $key['agency'] }}</span>
                        </li>
                    </ul>
                    <ul style="margin-left: 15px">
                        <li>List of Accomplishments and Contributions (if any)</li>
                        @foreach($key['accomplishments'] as $accomplishment)
                            <li style="margin-left: 20px">{{ $accomplishment['description'] }}</li>
                        @endforeach
                    </ul>
                    <ul style="margin-left: 15px">
                        <li>Summary of Actual Duties</li>
                        @foreach($key['duties'] as $duty)
                            <li style="margin-left: 20px">{{ $duty['description'] }}</li>
                        @endforeach
                    </ul>
                </td>
            </tr>
        @endforeach
    </table>
    <table style="margin-top: 25px">
        <tr>
            <td style="width: 65%"></td>
            <td class="bb"></td>
            <td style="width: 5%"></td>
        </tr>
        <tr>
            <td></td>
            <td class="center">
                (Signature over Printed Name<br/> of Employee Applicant)
            </td>
            <td></td>
        </tr>
        <tr>
            <td></td>
            <td>
                <div style="padding-top: 20px">
                    Date:&nbsp;&nbsp;&nbsp;________________
                </div>
            </td>
            <td></td>
        </tr>
    </table>
</div>
</body>
</html>
