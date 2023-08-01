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

        @font-face {
            font-family: 'Bookman';
            src: url({{ storage_path("fonts/bookman.ttf") }}) format("truetype");
            font-weight: 800;
            font-style: normal;
        }

        html, body {
            padding: 20px;
            margin: 0px;
            font-size: 10.66px;
            font-family: 'Bookman';
        }

        table {
            width: 100%;
            table-layout: fixed !important;
            border-spacing: 0 !important; /* Removes the cell spacing via CSS */
            border-collapse: collapse !important; /* Optional - if you don't want to have double border where cells touch */
        }

        table td {
            margin: 0px !important;
            height: 15px !important;
            padding: 2.5px !important;
            vertical-align: middle;
        }

        .text-center {
            text-align: center;
        }

        .fw-bold {
            font-weight: bold;
        }

        .border-top {
            border-top: 1px solid black;
        }

        .border-bottom {
            border-bottom: solid 1px black;
        }

        .underline {
            text-decoration: underline;
            width: 100%;
        }

        .gray {
            background: gray;
        }
    </style>
</head>
<body>
<div>Civil Service Form No. 6</div>
<div>Revised 2020</div>
<div class="text-center">
    <div>Republic of the Philippines</div>
    <div>CEBU SOUTH MEDICAL CENTER</div>
    <div>San Isidro, Talisay City, Cebu</div>
    <div style="margin-top:20px">APPLICATION FOR LEAVE</div>
</div>
<table style="margin-top:20px;border:1px solid black;font-size: 12px">
    <tr>
        <td colspan="2">1. OFFICE/DEPARTMENT</td>
        <td colspan="4">
            <span>2.   NAME:</span>
            <span style="margin-left: 15%">(Last)</span>
            <span style="margin-left: 15%">(First)</span>
            <span style="margin-left: 15%">(Middle)</span>
        </td>
    </tr>
    <tr class="border-bottom fw-bold text-center">
        <td colspan="2">IMISS/HOPSS</td>
        <td colspan="4">PANGCATAN, ASNAUI O.</td>
    </tr>
    <tr>
        <td style="padding-bottom: 10px !important;">3. DATE OF FILING</td>
        <td style="padding-bottom: 10px !important;">
            <div class="border-bottom text-center fw-bold">August 13, 2023</div>
        </td>
        <td style="padding-bottom: 10px !important;">4. POSITION</td>
        <td style="padding-bottom: 10px !important;">
            <div class="border-bottom text-center fw-bold">CMT II</div>
        </td>
        <td style="padding-bottom: 10px !important;">5. SALARY</td>
        <td style="padding-bottom: 10px !important;">
            <div class="border-bottom text-center fw-bold">36,000</div>
        </td>
    </tr>
    <tr class="fw-bold" style="font-size: 16px">
        <td colspan="6" class="text-center border-top border-bottom">6. DETAILS OF APPLICATION</td>
    </tr>
</table>
<table>
    <tr>
        <td>
            6.A TYPE OF LEAVE TO BE AVAILED OF
        </td>
        <td>6.B DETAILS OF LEAVE</td>
    </tr>
    <tr>
        <td>
            <span><input type="checkbox" style="margin-bottom: -9px"></span>
            <span>Vacation Leave <small>(Sec. 51, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</small></span>
        </td>
        <td>
            <span><input type="checkbox" style="margin-bottom: -9px"></span>
            <span>In case of Vacation/Special Privilege Leave:</span>
        </td>
    </tr>
    <tr>
        <td>
            <span><input type="checkbox" style="margin-bottom: -9px"></span>
            <span>Mandatory/Forced Leave(Sec. 25, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span>
        </td>
        <td>
            <span><input type="checkbox" style="margin-bottom: -9px"></span>
            <span>Within the Philippines __________________________</span>
        </td>
    </tr>
</table>
</body>
</html>
