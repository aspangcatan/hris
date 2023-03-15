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

        html {
            padding: 0px;
            margin: 0px;
            font-size: 11px;
        }

        img {
            width: 100%;
        }

        .text-center {
            text-align: center;
        }

        .text-left {
            text-align: left !important;
        }

        .text-v-center {
            vertical-align: middle;
        }

        .text-underline {
            text-decoration: underline solid black !important;
        }

        .fw-bold {
            font-weight: bold;
        }

        table {
            width: 100%;
            table-layout: fixed !important;
            border-spacing: 0; /* Removes the cell spacing via CSS */
            border-collapse: collapse; /* Optional - if you don't want to have double border where cells touch */
        }

        table td {
            margin: 0px !important;
            height: 5px !important;
            padding: 1px !important;
            vertical-align: middle !important;
        }

        .text-name {
            font-size: 16px;
            font-weight: bold;
        }

        .title {
            font-size: 20px;
        }

        .mt-1 {
            margin-top: 20px;
        }

        .b-l {
            border-left: solid 1px black;
        }

        .b-t {
            border-top: solid 1px black;
        }

        .b-r {
            border-right: solid 1px black;
        }

        .b-b {
            border-bottom: solid 1px black;
        }

        .border-dark {
            border: solid 1px black;
        }

    </style>
</head>
<body>
<img src="img/letter_head_banner.png"/>
<div class="text-center fw-bold title" style="margin-top:-40px">
    <span class="b-b">SERVICE RECORD</span>
</div>
<div style="margin-left: 10px;margin-right: 10px">
    <table class="text-center">
        <tr>
            <td class="text-left" style="width: 9%">
                <div>NAME:</div>
            </td>
            <td style="width: 21%">
                <div class="text-name">{{ strtoupper($user->lname) }}</div>
                <div>(SURNAME)</div>
            </td>
            <td style="width: 30%">
                <div class="text-name">{{ strtoupper($user->fname) }}</div>
                <div>(GIVEN NAME)</div>
            </td>
            <td style="width: 20%">
                <div class="text-name">{{ strtoupper($user->mname) }}</div>
                <div>(MIDDLE NAME)</div>
            </td>
            <td style="width: 20%"></td>
        </tr>
        <tr>
            <td class="text-left">
                <div class="mt-1">BIRTHDATE:</div>
            </td>
            <td>
                <div class="mt-1">{{ date('F j, Y', strtotime($employee->birthdate)) }}</div>
            </td>
            <td class="text-left">
                <div class="mt-1">
                    PLACE OF BIRTH:
                    <span style="margin-left: 20px">{{ strtoupper($employee->birthplace) }}</span>
                </div>
            </td>
            <td colspan="2"></td>
        </tr>
    </table>
    <div style="margin-top: 40px">
        THIS IS TO CERTIFY that the employee herein actually rendered services in this Office as shown by the Service
        Record below, each line which is supporting<br/>
        appointment and other papers actually issued by this Office and approved by the authorities concerned.
    </div>
</div>
<div style="padding-left: 10px;padding-right: 10px;padding-top: 5px">
    <table class="border-dark">
        <tr class="text-v-center text-center">
            <td class="b-b b-r" colspan="2" style="width: 15%">Services inclusive</br>Dates</td>
            <td class="b-b b-r" rowspan="2" style="width: 15%">RECORD OF <br/>APPOINTMENT<br/>DESIGNATION</td>
            <td class="b-b b-r" rowspan="2" style="width: 10%">STATUS</td>
            <td class="b-b b-r" rowspan="2" style="width: 7%">SALARY<br/>(ANNUM)</td>
            <td class="b-b b-r" rowspan="2" style="width: 18%">OFFICE OF ASSIGNMENT</td>
            <td class="b-b b-r" rowspan="2" style="width: 10%">LEAVE OF<br/>ABSENCES<br/>W/O PAY</td>
            <td class="b-b" rowspan="2" style="width: 23%">SEPARATION</td>
        </tr>
        <tr class="text-v-center text-center">
            <td class="b-b b-r">From</td>
            <td class="b-b b-r">To</td>
        </tr>
        @foreach($records as $data)
            <tr class="text-center text-v-center">
                <td class="b-b b-r">{{ date('m/d/Y', strtotime($data['date_from'])) }}</td>
                <td class="b-b b-r">{{ date('m/d/Y', strtotime($data['date_to'])) }}</td>
                <td class="b-b b-r">{{ $data['designation'] }}</td>
                <td class="b-b b-r">{{ $data['appointment_status'] }}</td>
                <td class="b-b b-r">{{ number_format($data['monthly_salary'],2) }}</td>
                <td class="b-b b-r">{{ $data['office_assignment'] }}</td>
                <td class="b-b b-r">{{ $data['leave_absence'] }}</td>
                <td class="b-b">{{ $data['separation'] }}</td>
            </tr>
        @endforeach
    </table>

    <div class="mt-1">
        Issued in compliance with Executive Order No. 54 dated August 10,1954 and in accordance with Circular No. 58
        dated August 10, 1954 of the system.
    </div>
    <div style="margin-top:20px">DATE ISSUED: February 20, 2023</div>

    <table style="margin-top: 40px">
        <tr>
            <td style="width: 40%">
                <div style="margin-bottom: 30px">Certified Correct:</div>
                <div class="fw-bold">RACHEL C. JALUAG</div>
                <div>Supervising Administrative Officer/HRM Officer</div>
            </td>
            <td style="width: 60%">
                <div style="margin-bottom: 30px">Approved by:</div>
                <div class="fw-bold">AGUSTIN D. AGOS, JR, MD, FPSGS, FPCS, DODT, Ph.D.OD,RODC</div>
                <div>Medical Center Chief II</div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
