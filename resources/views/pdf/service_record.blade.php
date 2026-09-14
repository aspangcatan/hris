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
            size: 612pt 792pt !important;
            margin-top: 0 !important;
            margin-right: 0 !important;
            margin-bottom: 118pt !important;
            margin-left: 0 !important;
        }

        .letterhead-bg {
            position: fixed;
            z-index: -2;
            top: 0;
            left: 0;
            width: 612pt;
            height: 792pt;
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
<img class="letterhead-bg" src="img/service_records_letterhead.jpg"/>
@include('pdf.service_records_header');
<div style="padding-left: 10px;padding-right: 10px">
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
        @foreach($service_records as $data)
            <tr class="text-center text-v-center">
                <td class="b-b b-r">{{ date('m/d/Y', strtotime($data['date_from'])) }}</td>
                <td class="b-b b-r">{{ date('m/d/Y', strtotime($data['date_to'])) }}</td>
                <td class="b-b b-r">{{ $data['designation'] }}</td>
                <td class="b-b b-r">{{ $data['appointment_status'] }}</td>
                <td class="b-b b-r">{{ number_format($data['monthly_salary'],2) }}</td>
                <td class="b-b b-r">{{ $data['office_assignment'] }}</td>
                <td class="b-b b-r">{!! nl2br(e($data['leave_absence'])) !!}</td>
                <td class="b-b">{{ $data['separation'] }}</td>
            </tr>
        @endforeach
    </table>
    @include('pdf.service_records_footer')
    @if(count($next_page_service) > 0)
        @for($i=0; $i<count($next_page_service); $i++)
            <div style="page-break-before: always !important;"></div>
            @include('pdf.service_records_body',['service_records'=>$next_page_service[$i]])
        @endfor
    @endif
</div>
</body>
</html>
